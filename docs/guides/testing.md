# Testing

Install dependencies in the shared developer runtimes with `make deps`. `make lint`, `make analyse`, and `make test-unit` keep their existing names and use the shared PHP and Node runtimes.

| Level | Command | Where it runs | Notes |
|---|---|---|---|
| PHP lint and analysis | `make lint`, `make analyse` | Worktree mounted at `/workspace` in shared `php-8.5-app` (PHP 8.5 CLI) | Includes Node 24 CLI for PHP tooling subprocesses; PHPStan targets PHP 8.3 (`phpVersion: 80300`); PHPCS/PHPCompatibility targets `8.3-`. |
| JavaScript checks | `make lint`, `make analyse` | Worktree mounted at `/workspace` in shared `php-8.5-app` (Node 24 and npm included) | ESLint, Stylelint, Prettier, and TypeScript use the lockfile dependencies. |
| PHP unit | `make test-unit` | Shared `php-8.5-app` | PHPUnit 9.6 + Brain Monkey; PCOV is enabled only for coverage runs. |
| JavaScript unit | `make test-unit` | Shared `node-runtime` | Vitest and jsdom. Helpers live in `packages/test-kit/js/dom.js`. |
| Integration | `make test-integration`; `WP_VERSION=6.6 WP_PHP_VERSION=8.1 make test-integration` | One shared `wp-web` container, selected under the Workspaces-wide lock | Uses the current ticket's `wp_JOOS_<n>` schema and `wptests_` table prefix. `WP_PHP_VERSION` accepts 8.1 through 8.5. |
| E2E | `make test-e2e THEME=jooservices` | Playwright 1.64.1 on the Dev VM, against the shared WordPress through a reverse tunnel | Browser tests use `WP_SITE_URL` (default `http://127.0.0.1:8082`) and run under the same lock. |
| Compatibility matrix | `make test-matrix THEME=jooservices` | Host orchestrator, shared WordPress (`wp-web`) and the Dev VM | Tests Tier 1 and basic Tier 3 plugins. Use `ARGS="--runtime-only"` for a runtime smoke, `ARGS="--child-theme"` to repeat plugin checks with a temporary child theme, or `ARGS="--only yoast,cf7"` to select plugins. |
| Tooling self-tests | `make test-tooling` | Host | Proves configured quality tools fail on a violation. |

The PHP app container runs PHP 8.5 for current tooling and coverage support. PHPStan and PHPCS enforce PHP 8.3 compatibility, so syntax or APIs introduced after 8.3 must fail the gates. PHP 8.5 is not the source compatibility target. WordPress tasks build the active theme and core assets before selecting the worktree mount, so integration and browser runs do not depend on generated files from a previous checkout. Integration runs each test suite in its own PHPUnit process: core and plugin fixtures do not inherit the selected theme's boot hooks, while the theme suite runs with the selected theme.

WordPress tasks select the matching Apache image from `WP_VERSION` and `WP_PHP_VERSION`; supported runtime selections are WordPress 6.6, 6.7, 6.8, latest, and beta, with PHP 8.1–8.5. For example, `WP_VERSION=6.6 WP_PHP_VERSION=8.1 make test-matrix THEME=jooservices ARGS="--runtime-only"` records a runtime-only check. `MATRIX_VARIANT` supplies an optional report label, while `WP_SITE_URL` and `E2E_BASE_URL` select the showcase host URL and the in-network browser URL. `make test-matrix` seeds only the current ticket's showcase schema, captures the installed WordPress/PHP/database versions, then writes JSON and Markdown reports in `artifacts/reports/matrix/`. `MATRIX_VARIANT`, `WP_VERSION`, and `WP_PHP_VERSION` are passed to the shared browser runner where applicable.

The `docker/scripts/phpunit-integration.sh` runner selects the matching `wp-phpunit` series. When the lockfile library does not match the running WordPress series, the matching release is installed under `/tmp` in the container. Both test suites use the ticket's isolated schema; the `wptests_` table prefix separates the WordPress test tables from the demo site's `wp_` tables.

## Base classes and helpers

- `JOOservices\TestKit\UnitTestCase`: Brain Monkey + Mockery, common escape/i18n stubs. Brain Monkey records hooks, it does not run callbacks: assert with `Filters\expectApplied()` / `Actions\expectAdded()`; real hook execution is tested in integration tests.
- `JOOservices\TestKit\IntegrationTestCase` (extends `WP_UnitTestCase`): `capture('wp_head')`, `request($url)`, `assertHeadContains()`.
- `packages/test-kit/e2e/helpers.ts`: `openView`, `expectStatus`, `collectConsoleErrors`, `runAxe`, `noHorizontalScroll`, `tapTargets`, `setScheme`.
- `tests/e2e/pages/*`: page objects built on roles and landmarks, never theme-specific classes.

## Reports

`artifacts/reports/` (git-ignored): `unit/` (Clover, HTML, summary), `vitest/`, `integration/junit-<wp-version>-<suite>.xml`, `e2e/` (JUnit + HTML), and `matrix/` (variant-specific JSON and Markdown). Coverage gates: `tools/coverage-gate.sh` (core 85%, plugin 80%, themes 70%) and Vitest thresholds in `vitest.config.js`.

The shared WordPress container is serialized by `docker/scripts/wp-task.sh` using a lock under `Workspaces/.tmp`. PHP, static-analysis, and JavaScript unit jobs run in one-off containers with the active worktree mounted at `/workspace`; those jobs can run across worktrees in parallel. See [Docker development setup](docker.md) for shared database setup and the unexecuted main-demo migration procedure.
