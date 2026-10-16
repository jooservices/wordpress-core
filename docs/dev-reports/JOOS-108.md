# JOOS-108 — Shared Docker infrastructure for theme development

## Changes

- Added shared `php-app` (PHP 8.5.11 CLI, Composer 2.10.3, mysqli, zip, PCOV 1.0.12, Node 24 CLI) and Playwright 1.64.1 runtimes to the Workspaces `runtimes` profile, with scoped lifecycle commands in `scripts/docker/shared`.
- Routed PHP, Composer, Node, npm/npx, and Playwright worktree commands through one-off containers mounted at `/workspace`.
- Moved the themes test stack to external network `workspaces-shared` and shared MariaDB. The ticket branch creates only `wp_JOOS_108`; the matching database account is scoped to that schema.
- Added one serialized WordPress test stack, a Workspaces-wide lock, per-ticket uploads volume, and port selection derived from `WP_SITE_URL` (ticket default `127.0.0.1:8082`). The owner demo migration remains documented and was not executed.
- Set PHPStan and PHPCS language checks to PHP 8.3, made critical CSS host-independent, and split WordPress integration runs so fixture suites do not inherit the selected theme's boot hooks.

## Docker network finding

The previous JOOS-86 work recorded Docker's predefined bridge address pools as exhausted when it tried to create another isolated project network; it used and later removed a separate `10.255.86.0/24` workaround. This ticket attaches to the already-running external `workspaces-shared` network (`192.168.16.0/20`), so Compose does not request another per-project bridge subnet. The shared MariaDB path was verified; no ticket-specific MariaDB service or network is created.

## Verification

| Check | Result |
|---|---|
| Shared Compose configuration | Pass; `php-app` and Playwright images built and reported healthy. PHP 8.5.11, mysqli, zip, PCOV, Composer 2.10.3, and Node 24 were verified. |
| `make lint` | Pass: headers, PHPCS, ESLint, Stylelint, and Prettier. |
| `make analyse` | Pass: both PHPStan configurations, TypeScript, and Deptrac; zero violations, warnings, or errors. PHPStan used `phpVersion: 80300`. |
| `make test-unit` | Pass: PHPUnit 256 tests / 1,316 assertions; Vitest 195 tests across 31 files. |
| Latest WordPress integration | Pass on WordPress 7.1.3 / PHP 8.3.35: kit 8 tests, core 322 tests, plugin 15 tests, theme suite 15 tests (7 starter-only skips). |
| WordPress 6.6 integration | Pass on WordPress 6.6.2 / PHP 8.3.13: kit 8 tests, core 322 tests, plugin 15 tests, theme suite 15 tests (7 starter-only skips). |
| `make demo`, `make health` | Pass: deterministic content seeded; health 17/17 at `http://127.0.0.1:8082`. |
| Shared Playwright specs | Pass: 90 tests across phone, tablet, and desktop in 36.1 seconds. The first run had 68 passes / 22 missing-media failures; a per-ticket uploads volume fixed the persistence issue and the full rerun passed. |
| Owner main demo | Pass: `http://127.0.0.1:8080/` returned HTTP 200 after the ticket tests. The main stack was not stopped, recreated, reset, or migrated. |
| Final code checks | Pass: `git diff --check`, project Compose config, shell syntax, integration harness PHP syntax/PHPCS, and Prettier; the final `make lint`, `make analyse`, and `make test-unit` reruns all passed. |

## Runtime note

Docker CLI operations and both host endpoints stalled briefly during the first reseed attempt, then recovered without a Docker daemon restart. The health and E2E commands completed after recovery. Repeated WordPress setup initially retried a language-pack download even when Vietnamese files were present; `install-wp.sh` now skips that network request when the core language pack already exists. The final test endpoint returned HTTP 200 on port 8082, and the owner endpoint returned HTTP 200 on port 8080.

After verification, `make reset CONFIRM=yes` stopped only the shared test WordPress/Mailpit project, dropped only `wp_JOOS_108` and its matching user, and removed its uploads volume. The new long-lived PHP and Playwright runtimes remain running for subsequent worktrees.

## Compatibility and scope

- The main demo's isolated MariaDB/network were not changed. Its migration procedure is documented in `docs/guides/docker.md` and remains pending owner approval.
- The shared test web service is a single container selected under a lock. PHP/Node unit and quality tasks remain one-off worktree runs and can execute concurrently.
- No remote was configured for the themes repository; neither repository branch has been pushed or merged.
