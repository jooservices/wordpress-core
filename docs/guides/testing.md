# Testing

Install dependencies in the shared developer runtimes with `make deps`. `make lint`, `make analyse`, and `make test-unit` keep their existing names and use the shared PHP and Node runtimes.

| Level | Command | Where it runs | Notes |
|---|---|---|---|
| PHP lint and analysis | `make lint`, `make analyse` | `docker exec` in the shared `workspaces-cli-php-8.5` container (PHP 8.5 CLI), the worktree as working directory | Includes Node 24 CLI for PHP tooling subprocesses; PHPStan targets PHP 8.1 (`phpVersion: 80100`) for core and PHP 8.3 for other code; PHPCS/PHPCompatibility targets `8.3-`. |
| JavaScript checks | `make lint`, `make analyse` | the same shared container (Node 24 and npm included) | ESLint, Stylelint, Prettier, and TypeScript use the lockfile dependencies. |
| PHP unit | `make test-unit` | The same shared container | PHPUnit 9.6 + Brain Monkey; PCOV is enabled only for coverage runs. |
| JavaScript unit | `make test-unit` | The same shared container | Vitest and jsdom. Helpers live in `packages/test-kit/js/dom.js`. |
| Integration | `make test-integration`; `WP_VERSION=6.6 WP_PHP_VERSION=8.1 make test-integration` | This worktree's site on the shared `wp-web` (PHP tests in the shared PHP 8.3 CLI container) | Uses the current ticket's `wp_JOOS_<n>` schema and `wptests_` table prefix. `WP_PHP_VERSION` accepts 8.1 through 8.5. Shared WordPress test operations use the recovery lock described below. |
| E2E | `make test-e2e THEME=jooservices` | Playwright 1.64.1: the browser on the Dev VM, the test process a `docker exec` into `wp-web` | Browser tests use the site's own host `http://<ticket>.jooservices-wp-themes.localhost:8082` and the shared WordPress test lock. Set `E2E_ENGINES=webkit` or `firefox` with `E2E_ARTIFACT_DIR=<absolute path under artifacts/evidence>` to capture that engine's reports and screenshots from `wp-web` into the worktree. |
| Compatibility matrix | `make test-matrix THEME=jooservices` | Host orchestrator, shared WordPress (`wp-web`) and the Dev VM | Tests Tier 1 and basic Tier 3 plugins. Use `ARGS="--runtime-only"` for a runtime smoke, `ARGS="--child-theme"` to repeat plugin checks with a temporary child theme, or `ARGS="--only yoast,cf7"` to select plugins. The operation uses the shared WordPress test lock. |
| Tooling self-tests | `make test-tooling` | Host | Proves configured quality tools fail on a violation. |

The shared PHP container runs PHP 8.5 for current tooling and coverage support. PHPStan enforces PHP 8.1 compatibility for core and PHP 8.3 compatibility for the remaining analyzed code; PHPCS/PHPCompatibility targets `8.3-`. PHP 8.5 is not the source compatibility target. WordPress tasks build the active theme and core assets before the site is created, so integration and browser runs do not depend on generated files from a previous checkout. Integration runs each test suite in its own PHPUnit process: core and plugin fixtures do not inherit the selected theme's boot hooks, while the theme suite runs with the selected theme.

`WP_VERSION` selects the WordPress core of the worktree's site (6.6, 6.7, 6.8, latest, a full version) and `WP_PHP_VERSION` the PHP CLI container for PHP tests (8.1 to 8.5; the web server runs PHP 8.3). For example, `WP_VERSION=6.6 WP_PHP_VERSION=8.1 make test-matrix THEME=jooservices ARGS="--runtime-only"` records a runtime-only check. `MATRIX_VARIANT` names the report.

### WooCommerce early text-domain notice

The plugin matrix recognizes one narrow third-party exception: the WordPress `_load_textdomain_just_in_time` notice stating that translations for the `woocommerce` domain were triggered before `init`. It is noted in the WooCommerce row and excluded from that row's PHP problem count; the same notice still fails every other row, and all other PHP errors still fail WooCommerce. This rule is based on the JOOWT-239 reports, which recorded the notice with WooCommerce 11.2.1 under both starter and JOOservices on WordPress 7.1.3 / PHP 8.3.30. Source review found no `woocommerce` text-domain translation calls in `themes/starter` or `packages/theme-core`; the core adapter reports its own `joo-core` text and adds WooCommerce support on `after_setup_theme`. The exact original and passing rerun are linked in [the JOOWP-302 evidence report](../dev-reports/JOOWP-302.md). The matcher and row-level behavior are covered by `tools/matrix.test.mjs`.

The `docker/scripts/phpunit-integration.sh` runner selects the matching `wp-phpunit` series. When the lockfile library does not match the running WordPress series, the matching release is installed under `artifacts/wp-tests-lib` of the worktree. Both test suites use the ticket's isolated schema; the `wptests_` table prefix separates the WordPress test tables from the demo site's `wp_` tables.

## Base classes and helpers

- `JOOservices\TestKit\UnitTestCase`: Brain Monkey + Mockery, common escape/i18n stubs. Brain Monkey records hooks, it does not run callbacks: assert with `Filters\expectApplied()` / `Actions\expectAdded()`; real hook execution is tested in integration tests.
- `JOOservices\TestKit\IntegrationTestCase` (extends `WP_UnitTestCase`): `capture('wp_head')`, `request($url)`, `assertHeadContains()`.
- `packages/test-kit/e2e/helpers.ts`: `openView`, `expectStatus`, `collectConsoleErrors`, `runAxe`, `noHorizontalScroll`, `tapTargets`, `setScheme`.
- `tests/e2e/pages/*`: page objects built on roles and landmarks, never theme-specific classes.

## Reports

`artifacts/reports/` (git-ignored): `unit/` (Clover, HTML, summary), `vitest/`, `integration/junit-<wp-version>-<suite>.xml`, `e2e/` (JUnit + HTML), and `matrix/` (variant-specific JSON and Markdown). Coverage gates: `tools/coverage-gate.sh` (core 85%, plugin 80%, themes 70%) and Vitest thresholds in `vitest.config.js`.

Every worktree has its own site (folder, database, vhost) on the shared `wp-web`. Operations that exercise the shared WordPress web runtime (`test-migrations`, `integration`, `e2e`, `e2e-demo`, `matrix`, `marketplace`, `screenshots`, `critical`, and `health`) serialize through `Workspaces/.tmp/jooservices-wp-shared-test.lock`; this prevents web-host contention while each site's data stays isolated. The lock records its owner PID and removes and logs a marker left by a dead owner or an empty or invalid PID. Waiters stop after 20 minutes with a clear timeout message, and each operation is capped at 20 minutes after acquiring the lock. A capped operation is terminated, reports which operation exceeded the limit, and releases its lock through exit cleanup. INT, TERM and HUP signals are forwarded to the capped process group so an interrupted operation does not continue in the background. The lock guard file uses `flock` and is left in place so all worktrees continue to coordinate on the same file. PHP, static-analysis and JavaScript jobs are `docker exec` calls in the shared CLI containers and do not use this lock. See [Docker development setup](docker.md) for the shared services, the site per worktree, seed snapshots and the VM browser.

The lock requires host `flock` and GNU `timeout`. On macOS, install both with Homebrew (`brew install flock coreutils`); Homebrew Coreutils exposes GNU timeout as `gtimeout`, which the helper detects automatically. Ensure Homebrew's command directory is on `PATH` before running locked WordPress tasks or `make test-tooling`.
