# JOOWT-288 G6-G9 Run Manifest

Date: 2026-10-20 (UTC)
Machine: wsl
Branch: JOOWT-288-core-r11-4-gates-g6-to-g9-for-starter-an
Rebased base: `origin/develop` at `7ffa49a`
Full gate-run source commit: `078d47b0be01e4720bd51036090abc31d7da0b5f`
Final source commit: `78d3c5ffa9468a313e6b2543bf67e41a50d0a71e` (removes duplicate identical dock-width declarations)
Runtime: WordPress 7.1.3, PHP 8.3.30, MariaDB 12.3.3
Database: ticket-scoped `wp_JOOWT_288`

The full two-theme gate batch ran after rebasing onto the current `develop`. After the duplicate-only CSS cleanup, jooservices demo, health, perf, full matrix, and package were rerun on the final source commit. The cleanup retains the existing 40 px dock width and changes no computed layout; starter files were not changed by it.

## Gate exits

| Theme | Command | Exit | Result |
| --- | --- | ---: | --- |
| starter | `make demo THEME=starter` | 0 | 17/17 health checks |
| starter | `make health THEME=starter` | 0 | 17/17 checks |
| starter | `make perf THEME=starter` | 0 | Five pages within score, metric and byte budgets |
| starter | `MATRIX_VARIANT=joowt288-final make test-matrix THEME=starter` | 0 | 23 pass, 0 fail, 2 unsupported |
| starter | `make package THEME=starter` | 0 | 275 files, 477 KB |
| jooservices | `make demo THEME=jooservices` | 0 | 17/17 health checks; rerun on final source |
| jooservices | `make health THEME=jooservices` | 0 | 17/17 checks; rerun on final source |
| jooservices | `make perf THEME=jooservices` | 0 | Five pages within budgets; fonts 100,140 / 102,400 B; rerun on final source |
| jooservices | `MATRIX_VARIANT=joowt288-final make test-matrix THEME=jooservices` | 0 | 23 pass, 0 fail, 2 unsupported; rerun on final source |
| jooservices | `make package THEME=jooservices` | 0 | 311 files, 836 KB; rerun on final source |

The unsupported matrix rows are licensed WP Rocket and WPML packages that are not present in `tests/matrix/vendor/`. The WooCommerce row passes and records its isolated third-party early text-domain notice.

## Supporting checks on final source

- `make lint`: PASS.
- `make analyse`: PASS; 0 violations, 0 errors.
- `make test-tooling`: PASS.
- `make test-unit`: PASS; PHPUnit 284 tests / 3,258 assertions, Vitest 48 files / 361 tests.

## Retained evidence

- Release summary, source commits, gate results and package checksums: `artifacts/reports/release/core-0.1.0/JOOWT-288.md`.
- Final matrix reports: `artifacts/reports/matrix/starter-joowt288-final-2026-10-20.{md,json}` and `artifacts/reports/matrix/jooservices-joowt288-final-2026-10-20.{md,json}`.
- Lighthouse results: `artifacts/reports/perf/starter.json` and `artifacts/reports/perf/jooservices.json`.
- Built archives: `artifacts/package/starter-0.1.0.zip`, `artifacts/package/jooservices-0.1.0.zip`, and `artifacts/package/jooservices-resume-0.1.0.zip`.
- Refreshed compatibility overview: `docs/compatibility.md`.

Older `rebased` and `retry` matrix files remain as historical evidence. The `joowt288-final` reports are the final gate results.
