# PHP 8.3 and 8.5 Test Evidence

- Ticket: JOOWT-238
- Machine: WSL (`wsl`)
- Date: 2026-10-20
- Branch: `JOOWT-238-core-r2-2-run-unit-and-integration-suite`

## Runtime and setup

- Shared PHP CLI runtimes: PHP 8.3.30 and PHP 8.5.11 (the default `php` runtime is 8.5).
- PHPUnit 9.6.38; Vitest 5.0.4; WordPress 7.1.3.
- Installed locked Composer and npm dependencies with `make deps`.
- Integration tests used the ticket-scoped `wp_JOOWT_238` database and this worktree's WordPress test site.
- Integration runs used `PHP_ERROR_REPORTING=32767` (`E_ALL`) so PHP deprecations were visible.

## Unit suites

| Runtime | Command | Result |
| --- | --- | --- |
| PHP 8.3.30 | `WP_PHP_CLI=8.3 make test-unit` | PASS: PHPUnit 280 tests / 1,442 assertions; Vitest 45 files / 318 tests. |
| PHP 8.5.11 | `make test-unit PHP='bash ./docker/scripts/runtime.sh php -d error_reporting=32767'` | PASS: PHPUnit 280 tests / 1,442 assertions; Vitest 45 files / 318 tests. No PHP deprecation notices. |

## WordPress integration suites

| Runtime | Command | Result |
| --- | --- | --- |
| PHP 8.3.30 | `WP_PHP_VERSION=8.3 PHP_ERROR_REPORTING=32767 WP_CLI_COLOR=0 make test-integration` | PASS: kit 8 tests / 26 assertions; core 325 / 1,638; plugin 43 / 392; theme 21 / 258, with 7 skipped. |
| PHP 8.5.11 | `WP_PHP_VERSION=8.5 PHP_ERROR_REPORTING=32767 WP_CLI_COLOR=0 make test-integration` | PASS: kit 8 tests / 26 assertions; core 325 / 1,638; plugin 43 / 392; theme 21 / 258, with 7 skipped. |

## PHP 8.5 deprecation findings

- Removed `ReflectionProperty::setAccessible()` and `ReflectionMethod::setAccessible()` from the theme-core integration tests. PHP 8.5 deprecates these methods because they have no effect since PHP 8.1.
- `TerminalTest::tear_down()` now restores `$_SERVER['REQUEST_URI']` to the empty string used by the WordPress PHPUnit harness. Previously it unset the key, which triggered an upstream WordPress cron warning and `str_contains(null)` deprecation during shutdown.
- The final PHP 8.5 PHPUnit run has no deprecation from theme-core or WordPress cron.
- Site setup still prints a tooling-only PHP 8.5 deprecation from the shared WP-CLI Phar: `Using null as an array offset is deprecated` in `php-cli-tools/lib/cli/Colors.php:95`. This is outside the repository's core code and the shared runtime tool was left unchanged.

## Other checks

- `make lint` — PASS (headers, PHPCS, ESLint, Stylelint, Prettier).
- `bash -n` on the modified shell scripts — PASS.
- `git diff --check` — PASS.
