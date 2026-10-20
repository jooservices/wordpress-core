# JOOWT-288 G6-G9 Evidence

- Machine: WSL (`wsl`)
- Date: 2026-10-20
- Branch: `JOOWT-288-core-r11-4-gates-g6-to-g9-for-starter-an`
- Rebased base: `origin/develop` at `7ffa49a`
- Full two-theme gate-run source commit: `078d47b0be01e4720bd51036090abc31d7da0b5f`
- Final source commit: `78d3c5ffa9468a313e6b2543bf67e41a50d0a71e`
- Runtime: WordPress 7.1.3, PHP 8.3.30, MariaDB 12.3.3
- Database: isolated `wp_JOOWT_288`

The final source commit removes duplicate copies of the existing Jooservices terminal dock width declarations. Jooservices G6-G9 and the quality checks were rerun after that cleanup. Starter source was not changed by it.

## Results

| Theme | Gate | Command | Result |
| --- | --- | --- | --- |
| starter | G6 | `make demo THEME=starter` | PASS, 17/17 health checks |
| starter | G6 | `make health THEME=starter` | PASS, 17/17 checks |
| starter | G7 | `make perf THEME=starter` | PASS, all five pages within budgets |
| starter | G8 | `MATRIX_VARIANT=joowt288-final make test-matrix THEME=starter` | PASS, 23 pass, 0 fail, 2 unsupported |
| starter | G9 | `make package THEME=starter` | PASS, 275 files, 477 KB |
| jooservices | G6 | `make demo THEME=jooservices` | PASS, 17/17 health checks; rerun on final source |
| jooservices | G6 | `make health THEME=jooservices` | PASS, 17/17 checks; rerun on final source |
| jooservices | G7 | `make perf THEME=jooservices` | PASS, all five pages within budgets; rerun on final source |
| jooservices | G8 | `MATRIX_VARIANT=joowt288-final make test-matrix THEME=jooservices` | PASS, 23 pass, 0 fail, 2 unsupported; rerun on final source |
| jooservices | G9 | `make package THEME=jooservices` | PASS, 311 files, 836 KB; rerun on final source |

`wp-rocket` and `wpml` are unsupported because their licensed ZIPs are absent from `tests/matrix/vendor/`. All testable matrix rows pass. The WooCommerce row records a known third-party early text-domain notice; the matrix allowlist applies only to that exact notice in the WooCommerce row. WordPress.org TLS messages remain environment warnings.

## Lighthouse detail

All scores are 0-1. Every row has an empty `problems` array. Search is noindex and correctly reports `crawlable: 0`; its SEO score is therefore not judged against the normal minimum.

| Theme | Page | Perf | A11y | CLS | TBT (ms) | LCP (ms) | CSS / JS / fonts (B) |
| --- | --- | ---: | ---: | ---: | ---: | ---: | --- |
| starter | home | 1.00 | 0.98 | 0.002 | 0 | 1,732 | 4,450 / 23,624 / 0 |
| starter | single | 1.00 | 1.00 | 0.000 | 0 | 1,655 | 8,765 / 25,928 / 0 |
| starter | category | 1.00 | 1.00 | 0.007 | 0 | 1,506 | 4,157 / 22,101 / 0 |
| starter | search | 1.00 | 1.00 | 0.000 | 0 | 1,511 | 3,506 / 22,102 / 0 |
| starter | about | 1.00 | 0.96 | 0.015 | 0 | 1,506 | 5,628 / 22,100 / 0 |
| jooservices | home | 0.96 | 0.98 | 0.011 | 0 | 2,482 | 16,213 / 27,077 / 100,140 |
| jooservices | single | 0.96 | 0.97 | 0.000 | 0 | 2,481 | 20,523 / 27,977 / 100,140 |
| jooservices | category | 0.97 | 0.98 | 0.004 | 0 | 2,332 | 15,921 / 25,554 / 100,140 |
| jooservices | search | 0.97 | 0.98 | 0.004 | 0 | 2,256 | 15,268 / 25,554 / 100,140 |
| jooservices | about | 0.98 | 0.98 | 0.014 | 0 | 2,104 | 13,796 / 22,705 / 100,140 |

The shared font byte budget is 102,400 B (100 KiB). This accounts for the two Latin typefaces and the Vietnamese IBM Plex subset while preserving Vietnamese glyph coverage. The observed transfer is 100,140 B.

## Package checksums

| Artifact | Size | SHA-256 |
| --- | ---: | --- |
| `artifacts/package/starter-0.1.0.zip` | 488,845 bytes | `295e33c0892c986363a270c678a4c0d3d7745a0e75f03f5e18dc462ca4822b3a` |
| `artifacts/package/jooservices-0.1.0.zip` | 856,171 bytes | `ee90ae9a9b6d1d5f729f2246b6ef6c525d535a8992903a24a8acaa1db7b0d1bb` |
| `artifacts/package/jooservices-resume-0.1.0.zip` | 92,832 bytes | `c0da51d1677ce82e9c28e9a90adca4a29ac244e9f98a8ab2159b655f4aab5f54` |

## Supporting checks

`make lint`, `make analyse`, `make test-tooling`, and `make test-unit` pass on final source. PHPUnit: 284 tests, 3,258 assertions. Vitest: 48 files, 361 tests.

The final run manifest is `artifacts/reports/JOOWT-288/run-manifest.md`. Final matrix JSON/Markdown and Lighthouse JSON are under `artifacts/reports/matrix/` and `artifacts/reports/perf/` respectively. This ticket records G6-G9 only; unrelated G2-G5 evidence was not rerun.
