# Core 0.1.0 Release Gate Evidence

Date: 2026-10-20. Machine: WSL (`wsl`). Ticket: JOOWT-288.

The complete G6-G9 gate run on the rebased source used commit `078d47b0be01e4720bd51036090abc31d7da0b5f`. Final cleanup commit `78d3c5ffa9468a313e6b2543bf67e41a50d0a71e` removes duplicate copies of the existing Jooservices dock-width declarations; the computed layout is unchanged. Jooservices G6-G9 and all quality checks were rerun on the final cleanup commit. Starter code was not changed by that cleanup.

Review follow-up commit `7d05b85d8ea272193e453a029855fb65beb99cf3` scopes this Jooservices product document to Jooservices plugin-matrix reports, so Starter results cannot replace its evidence. The compatibility documentation check, lint, analysis, tooling tests and unit tests pass on the follow-up tree. Both package gates were rerun and pass; G6-G8 evidence remains from the final gate run because the follow-up changes documentation selection and its regression test only.

The runs used the shared WordPress runtime (WordPress 7.1.3, PHP 8.3.30, MariaDB 12.3.3) and isolated `wp_JOOWT_288` database.

| Gate | Starter | Jooservices |
| --- | --- | --- |
| G6 Demo and health | PASS: `make demo` and `make health`, 17/17 checks each | PASS: `make demo` and `make health`, 17/17 checks each; rerun on final source |
| G7 Budgets | PASS: all five pages; font transfer 0 B | PASS: all five pages; font transfer 100,140 B under the 102,400 B (100 KiB) limit; rerun on final source |
| G8 Matrix | PASS: 23 pass, 0 fail, 2 unsupported | PASS: 23 pass, 0 fail, 2 unsupported; rerun on final source |
| G9 Package | PASS: 275 files, 477 KB; rerun on review follow-up | PASS: 311 files, 836 KB; rerun on review follow-up |

The two unsupported matrix rows are WP Rocket and WPML; their licensed ZIPs are absent from `tests/matrix/vendor/`. They are recorded as unsupported, not as passing tests. The WooCommerce row passes with a recorded notice from WooCommerce's early text-domain load. WordPress.org TLS warnings are recorded as environment warnings and do not fail rows.

The Jooservices font allowance is 100 KiB so the required IBM Plex Sans and Vietnamese subset can be served alongside the Latin display fonts. The measured transfer is 100,140 bytes on each page. Search is a noindex page; its SEO score is below the normal minimum by design, and the crawlability audit confirms `noindex` (`crawlable: 0`).

## Evidence

- Run manifest, commands, exits, runtime and source commits: `artifacts/reports/JOOWT-288/run-manifest.md`.
- Detailed G6-G9 results and package checksums: `artifacts/reports/release/core-0.1.0/JOOWT-288.md`.
- Final matrix reports: `artifacts/reports/matrix/starter-joowt288-final-2026-10-20.{md,json}` and `artifacts/reports/matrix/jooservices-joowt288-final-2026-10-20.{md,json}`.
- Lighthouse results: `artifacts/reports/perf/starter.json` and `artifacts/reports/perf/jooservices.json`.
- Packages: `artifacts/package/starter-0.1.0.zip`, `artifacts/package/jooservices-0.1.0.zip`, and `artifacts/package/jooservices-resume-0.1.0.zip`.
- The compatibility overview was refreshed in `docs/compatibility.md`.

`make docs-check`, `make lint`, `make analyse`, `make test-tooling`, and `make test-unit` pass on the review follow-up tree. PHPUnit reports 284 tests and 3,258 assertions; Vitest reports 48 files and 362 tests.

This report covers JOOWT-288's G6-G9 scope. It does not rerun unrelated G2-G5 release evidence or claim that every licensed matrix plugin was available.
