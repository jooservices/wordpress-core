# Core 0.1.0 gate evidence

Date: 2026-10-21. Commit: `8171ef9212997ca2fe85009dbe0bcf508de5cd0b` (the `develop` snapshot when this report was prepared; not a tested revision). [Report base and R1.4 coverage record](../../artifacts/reports/release/core-0.1.0/coverage.md). Remote pushes: none.

This report aggregates recorded R1.1–R1.4 results and the existing targeted core-block E2E report. The individual source revisions and limits are listed below; this is not a claim that all gates were rerun at the report commit. [Static](../../artifacts/reports/release/core-0.1.0/static.md) · [Unit](../../artifacts/reports/release/core-0.1.0/unit.md) · [Integration](../../artifacts/reports/release/core-0.1.0/integration.md) · [Coverage](../../artifacts/reports/release/core-0.1.0/coverage.md) · [Core-block E2E](../../artifacts/reports/release/core-0.1.0/e2e-core-blocks.md).

## Definition of done

| Gate | Evidence (command, result, source) |
|---|---|
| G1 Lint | **Pass.** `make lint`; R1.1 recorded a successful final run, and R1.4 also records lint passing. [Static gate evidence](../../artifacts/reports/release/core-0.1.0/static.md) · [R1.4 coverage and final checks](../../artifacts/reports/release/core-0.1.0/coverage.md) |
| G2 Static analysis | **Pass.** `make analyse`; R1.1 recorded a successful final run, and R1.4 also records analysis passing. [Static gate evidence](../../artifacts/reports/release/core-0.1.0/static.md) · [R1.4 coverage and final checks](../../artifacts/reports/release/core-0.1.0/coverage.md) |
| G3 Unit and coverage | **Pass.** `make test-unit` passed at source commit `684999b92b30b5904a6611dce5c59efc94fd6fa6` (280 PHPUnit tests / 1,442 assertions; 318 Vitest tests across 45 files). The later R1.4 `make coverage` run also passed and reports the current merged coverage below. [Unit test evidence](../../artifacts/reports/release/core-0.1.0/unit.md) · [R1.4 coverage evidence](../../artifacts/reports/release/core-0.1.0/coverage.md) |
| G4 Integration | **Pass on minimum and latest WordPress.** `make test-integration THEME=starter WP_VERSION=6.6` and `make test-integration THEME=starter WP_VERSION=latest`; each endpoint recorded 418 tests, 2,512 assertions, zero failures, and 15 documented skips. [Integration evidence](../../artifacts/reports/release/core-0.1.0/integration.md) |
| G5 Core-block E2E | **Targeted pass; full gate not claimed.** The recorded `make test-e2e` run selected `core-accessibility.spec.ts` and passed 12 tests across phone, tablet, and desktop. [Core-block E2E evidence and scope](../../artifacts/reports/release/core-0.1.0/e2e-core-blocks.md) |

## Core coverage by module

All values below come from the R1.4 merged coverage run. [Coverage evidence and command details](../../artifacts/reports/release/core-0.1.0/coverage.md).

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
| **Core total** | **93.80%** | **6,655 / 7,095** |

## Defects found and fixed during the work

- R1.4 added Admin tests; Admin coverage is now 88.14%, and no listed core module is below 80%. [R1.4 coverage evidence](../../artifacts/reports/release/core-0.1.0/coverage.md)
- The core-block accessibility audit fixed its reported serious color-contrast finding; the final targeted E2E result found no remaining axe issues. [Core-block E2E evidence](../../artifacts/reports/release/core-0.1.0/e2e-core-blocks.md)

## Known limits and manual checks still open

- G4 used the `starter` theme. Its 15 skips on each WordPress version are documented Jooservices-specific tests, so this run does not establish Jooservices theme integration. [Integration evidence and skip reasons](../../artifacts/reports/release/core-0.1.0/integration.md)
- G5 is limited to the core accessibility spec; the entire configured E2E suite is not claimed. The source report does not record the tested Git commit, so it does not establish G5 at `8171ef9212997ca2fe85009dbe0bcf508de5cd0b`. [Core-block E2E evidence and scope](../../artifacts/reports/release/core-0.1.0/e2e-core-blocks.md)
- The evidence records use multiple revisions: G1/G2 at R1.1 and R1.4, G3 at R1.2 and R1.4, and G4 at R1.3. This report does not represent a fresh all-gates run at the listed `develop` snapshot. [Static gate evidence](../../artifacts/reports/release/core-0.1.0/static.md) · [Unit test evidence](../../artifacts/reports/release/core-0.1.0/unit.md) · [Integration evidence](../../artifacts/reports/release/core-0.1.0/integration.md) · [R1.4 coverage evidence](../../artifacts/reports/release/core-0.1.0/coverage.md)
