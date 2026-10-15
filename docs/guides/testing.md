# Testing

| Level | Command | Where it runs | Notes |
|---|---|---|---|
| PHP unit | `make test-unit` | host PHP, PHPUnit 9.6 + Brain Monkey | no WordPress; fast |
| JS unit | `make test-unit` (Vitest, jsdom) | host Node | helpers in `packages/test-kit/js/dom.js` |
| Integration | `make test-integration` | Docker `wordpress` container, wp-phpunit on schema `wp_tests` | `WP_VERSION=6.6` stack for the minimum version |
| E2E | `make test-e2e THEME=jooservices` | Playwright against the demo (3 viewports: phone 390, tablet 820, desktop 1440) | axe accessibility scan included |
| Tooling self-tests | `make test-tooling` | host | proves every linter fails on a violation |

PHPUnit 9.6 is intentional: the WordPress test suite does not support PHPUnit 10+.

## Base classes and helpers
- `JOOservices\TestKit\UnitTestCase`: Brain Monkey + Mockery, common escape/i18n stubs. Brain Monkey records hooks, it does not run callbacks: assert with `Filters\expectApplied()` / `Actions\expectAdded()`; real hook execution is tested in integration tests.
- `JOOservices\TestKit\IntegrationTestCase` (extends `WP_UnitTestCase`): `capture('wp_head')`, `request($url)`, `assertHeadContains()`.
- `packages/test-kit/e2e/helpers.ts`: `openView`, `expectStatus`, `collectConsoleErrors`, `runAxe`, `noHorizontalScroll`, `tapTargets`, `setScheme`.
- `tests/e2e/pages/*`: page objects built on roles and landmarks, never theme-specific classes.

## Reports
`artifacts/reports/<suite>/` (git-ignored): `unit/` (Clover, HTML, summary), `vitest/`, `integration/junit-<wp-version>.xml`, `e2e/` (JUnit + HTML). Coverage gates: `tools/coverage-gate.sh` (core 85%, plugin 80%, themes 70%) and Vitest thresholds in `vitest.config.js`.

## Integration test library
`docker/scripts/phpunit-integration.sh` picks the wp-phpunit release that matches the running WordPress series (the repository ships the latest; for 6.6 it installs `wp-phpunit/wp-phpunit:6.6.*` into `/tmp` inside the container, network needed).
