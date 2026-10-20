# JOOWT-288 G6-G9 Run Manifest

Date: 2026-10-20 (UTC)
Machine: wsl
Branch: JOOWT-288-core-r11-4-gates-g6-to-g9-for-starter-an
Source commit tested: 1bd6483881aae786a2c5219e3b535d66c781d336
Runtime: WordPress 7.1.3, PHP 8.3.30, MariaDB 12.3.3
Database: ticket-scoped wp_JOOWT_288

## Gate exits

| Theme | Command | Exit | Result |
| --- | --- | ---: | --- |
| starter | make demo | 0 | 17/17 health checks |
| starter | make health | 0 | 17/17 checks |
| starter | make perf | 2 | Missing themes/starter/budgets.json |
| starter | initial make test-matrix (MATRIX_VARIANT=joowt288) | 2 | Invalid run: report directory EACCES; excluded from final result |
| starter | retry make test-matrix (MATRIX_VARIANT=joowt288-retry) | 2 | 22 pass, 1 fail, 2 unsupported |
| starter | make package | 0 | 275 files, 477 KB |
| jooservices | make demo | 0 | 17/17 health checks |
| jooservices | make health | 0 | 17/17 checks |
| jooservices | make perf | 2 | Shared-runtime Chromium executable missing |
| jooservices | make test-matrix (MATRIX_VARIANT=joowt288-retry) | 2 | 0 pass, 23 fail, 2 unsupported |
| jooservices | make package | 0 | 311 files, 836 KB |

## Retained evidence

- Release summary, tested commit, gate results, and package checksums: `artifacts/reports/release/core-0.1.0/JOOWT-288.md`.
- Final matrix reports: `artifacts/reports/matrix/starter-joowt288-retry-2026-10-20.{md,json}` and `artifacts/reports/matrix/jooservices-joowt288-retry-2026-10-20.{md,json}`.
- Built package archives: `artifacts/package/starter-0.1.0.zip`, `artifacts/package/jooservices-0.1.0.zip`, and `artifacts/package/jooservices-resume-0.1.0.zip`.
- Tracked release overview: `docs/dev-reports/core-0.1.0-release.md`.

## Supporting checks

- `make deps`: PASS; installed locked Composer/npm dependencies through shared runtimes.
- `make lint`: PASS.
