# Core 0.1.0 coverage evidence (R1.4)

- Source report: [JOOWT-234 coverage by module](../../../../docs/dev-reports/JOOWT-234-coverage.md).
- Evidence recorded by commit: `21cda6eb8ecaddd1d7f7382491d3fff4f5e96848` (`chore(core): Report per-module coverage and add Admin tests (JOOWT-234)`).
- Aggregate report preparation base (not a tested revision): `8171ef9212997ca2fe85009dbe0bcf508de5cd0b` (`develop`, as checked for R1.5).
- Command: `make coverage`.
- Result: **Pass**. Merged PHPUnit unit and integration coverage reported 93.80% core line coverage (6,655 / 7,095), above the 85% gate. All 15 listed `packages/theme-core/src` modules meet the 80% module threshold.
- Coverage definition: executable statement lines in Clover; each file belongs to its first module directory below `packages/theme-core/src`, with root-level files grouped under `Module`.

| Module | Line coverage | Covered / executable lines |
|---|---:|---:|
| Admin | 88.14% | 565 / 641 |
| Assets | 90.27% | 232 / 257 |
| Blocks | 95.74% | 1,125 / 1,175 |
| Compat | 91.53% | 810 / 885 |
| Config | 96.57% | 338 / 350 |
| Container | 100.00% | 24 / 24 |
| Content | 96.90% | 718 / 741 |
| Http | 100.00% | 22 / 22 |
| I18n | 93.75% | 300 / 320 |
| Lazy | 98.49% | 327 / 332 |
| Module | 90.23% | 194 / 215 |
| Performance | 90.20% | 368 / 408 |
| Seo | 93.38% | 1,170 / 1,253 |
| Support | 96.00% | 168 / 175 |
| Terminal | 98.99% | 294 / 297 |

The same `make coverage` run reported 284 PHPUnit unit tests / 3,258 assertions, 425 PHPUnit integration tests / 2,601 assertions with no failures or errors, and 366 Vitest tests across 48 files. The report also records `make lint`, `make analyse`, and `make docs-check` as passing.

This file transcribes the existing R1.4 report; it records no new measurement.
