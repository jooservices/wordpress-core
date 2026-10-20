# JOOWT-234 Core coverage by module

- Date: 2026-10-21
- Branch: `JOOWT-234-core-r1-4-coverage-per-module-and-the-mi`
- Base: `d81f6f8` (`origin/develop`)
- WordPress integration runtime: 7.1.3

## Coverage results

The final `make coverage` run merged PHPUnit unit and integration coverage into `artifacts/reports/unit/clover.xml`. Percentages count executable statement lines in Clover. A file is assigned to the first module directory below `packages/theme-core/src`; root-level source files, including `Kernel.php`, are grouped under `Module`.

Core coverage is **93.80% (6,655 / 7,095)** against the unchanged 85% gate. All 15 modules named in the ticket meet 80%.

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

Admin was the only module below 80% in the merged baseline. The new tests raise it to 88.14%, so no R1 follow-up bug is required.

## Commands and results

| Command | Result |
|---|---|
| `make coverage` | Pass. PHPUnit unit: 284 tests / 3,258 assertions. PHPUnit integration: 425 tests / 2,601 assertions, 0 failures/errors. Core gate: 93.80% (required 85%). Vitest: 48 files / 366 tests passed. |
| `make lint` | Pass. Headers, i18n scan, PHPCS, ESLint, Stylelint, and Prettier checks passed. |
| `make analyse` | Pass. PHPStan, TypeScript checks, and Deptrac reported no errors. |
| `make docs-check` | Pass. Registry, generated references, and buyer documentation links are valid. |

The same coverage gate run reported 93.15% for `plugins/jooservices-resume/src` (required 80%) and 76.19% for `themes/jooservices/inc` (required 70%). `themes/starter/inc` was skipped because it has no executable PHP statements.

## Coverage setup correction

The unit coverage target previously filtered pcov to `/workspace`, an empty directory in the shared runtime, and the gate called host `php`, which is unavailable on WSL. `Makefile` now filters unit coverage to `$(CURDIR)`, and `tools/coverage-gate.sh` runs the checker through the repository's shared PHP runtime and fails on checker errors instead of reporting them as skipped coverage. No thresholds changed.
