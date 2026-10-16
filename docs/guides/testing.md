# Testing

Install dependencies in the shared developer runtimes with `make deps`. `make lint`, `make analyse`, and `make test-unit` keep their existing names and use the shared PHP and Node runtimes.

| Level | Command | Where it runs | Notes |
|---|---|---|---|
| PHP lint and analysis | `make lint`, `make analyse` | Worktree mounted at `/workspace` in shared `php-app` (PHP 8.5 CLI) | Includes Node 24 CLI for PHP tooling subprocesses; PHPStan targets PHP 8.3 (`phpVersion: 80300`); PHPCS/PHPCompatibility targets `8.3-`. |
| JavaScript checks | `make lint`, `make analyse` | Worktree mounted at `/workspace` in shared `node-runtime` | ESLint, Stylelint, Prettier, and TypeScript use the lockfile dependencies. |
| PHP unit | `make test-unit` | Shared `php-app` | PHPUnit 9.6 + Brain Monkey; PCOV is enabled only for coverage runs. |
| JavaScript unit | `make test-unit` | Shared `node-runtime` | Vitest and jsdom. Helpers live in `packages/test-kit/js/dom.js`. |
| Integration | `make test-integration`; `WP_VERSION=6.6 make test-integration` | One shared PHP 8.3 WordPress container | Uses the current ticket's `wp_JOOS_<n>` schema and `wptests_` table prefix. The Workspaces-wide lock selects one worktree at a time. |
| E2E | `make test-e2e THEME=jooservices` | Shared Playwright 1.64.1 runner on `workspaces-shared` | Browser tests use `http://wordpress` by default and run under the same lock. |
| Tooling self-tests | `make test-tooling` | Host | Proves configured quality tools fail on a violation. |

The PHP app container runs PHP 8.5 for current tooling and coverage support. PHPStan and PHPCS enforce PHP 8.3 compatibility, so syntax or APIs introduced after 8.3 must fail the gates. PHP 8.5 is not the source compatibility target. WordPress tasks build the active theme and core assets before selecting the worktree mount, so integration and browser runs do not depend on generated files from a previous checkout. Integration runs each test suite in its own PHPUnit process: core and plugin fixtures do not inherit the selected theme's boot hooks, while the theme suite runs with the selected theme.

WordPress integration uses the PHP 8.3 Apache images for latest and 6.6. The `docker/scripts/phpunit-integration.sh` runner selects the matching `wp-phpunit` series. When the lockfile library does not match the running WordPress series, the matching release is installed under `/tmp` in the container. Both test suites use the ticket's isolated schema; the `wptests_` table prefix separates the WordPress test tables from the demo site's `wp_` tables.

## Base classes and helpers

- `JOOservices\TestKit\UnitTestCase`: Brain Monkey + Mockery, common escape/i18n stubs. Brain Monkey records hooks, it does not run callbacks: assert with `Filters\expectApplied()` / `Actions\expectAdded()`; real hook execution is tested in integration tests.
- `JOOservices\TestKit\IntegrationTestCase` (extends `WP_UnitTestCase`): `capture('wp_head')`, `request($url)`, `assertHeadContains()`.
- `packages/test-kit/e2e/helpers.ts`: `openView`, `expectStatus`, `collectConsoleErrors`, `runAxe`, `noHorizontalScroll`, `tapTargets`, `setScheme`.
- `tests/e2e/pages/*`: page objects built on roles and landmarks, never theme-specific classes.

## Reports

`artifacts/reports/` (git-ignored): `unit/` (Clover, HTML, summary), `vitest/`, `integration/junit-<wp-version>-<suite>.xml`, `e2e/` (JUnit + HTML). Coverage gates: `tools/coverage-gate.sh` (core 85%, plugin 80%, themes 70%) and Vitest thresholds in `vitest.config.js`.

The shared WordPress container is serialized by `docker/scripts/wp-task.sh` using a lock under `Workspaces/.tmp`. PHP, static-analysis, and JavaScript unit jobs run in one-off containers with the active worktree mounted at `/workspace`; those jobs can run across worktrees in parallel. See [Docker development setup](docker.md) for shared database setup and the unexecuted main-demo migration procedure.
