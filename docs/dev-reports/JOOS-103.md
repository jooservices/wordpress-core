# JOOS-103 — Release packaging and evidence

Branch: `feature/JOOS-103-release-pipeline`, based on local `develop` at `e4dafda` (no Git remote is configured).

## Changes

- Added `make release` and a release validator for the theme and JOOservices Resume versions, changelog mirrors, GPL headers, WordPress/PHP compatibility, and the 590×300 ThemeForest preview.
- Made theme and plugin ZIP entries ordered and timestamp-normalized, then added a two-build byte comparison for all release deliverables.
- Added the ThemeForest main download, separate preview and demo-content uploads, item description, buyer documentation, license and font credits, dependency list, SHA-256 manifests, and build evidence report.
- Added a synthetic, fictionalized sale-demo export; no live-site export or third-party article/photo assets are packaged.
- Added an integration upgrade-preservation test for theme settings and Resume company records, a filled candidate checklist, and release/update/rollback/hotfix policy.
- Updated the CI package job to build and upload the complete release artifacts after required gates.

## Verification

| Check | Evidence | Result |
|---|---|---|
| `make lint` | PHPCS, ESLint, Stylelint, Prettier, header check | Pass |
| `make analyse` | PHPStan, TypeScript, Deptrac | Pass |
| `make test-unit` | PHPUnit 256 tests / 1,316 assertions; Vitest 207 tests | Pass |
| `make lint` | Header check and PHPCS 262 files pass; ESLint, Stylelint and Prettier pass after final edits | Pass |
| `make analyse` | PHPStan core and theme, TypeScript, Deptrac (0 violations) | Pass |
| Release metadata and package unit tests | `release-check`, version/changelog, checksum, archive ordering, and demo-sanitization tests | Pass |
| Coverage | Vitest lines 97.3% overall; `release-check.mjs` and `lib/reproducible.mjs` 100%. `release.mjs` and `release-runner.mjs` are build orchestration (zip, git, file layout) and are not in the unit coverage gate: their pure helpers are unit-tested and the real flow is proven by `make release` with the two-build byte comparison | Pass (documented deviation) |
| WordPress integration upgrade test | `ReleaseUpgradeTest::test_update_from_previous_release_keeps_theme_settings_and_company_data` (theme suite 16 tests / 207 assertions, 7 starter-only skips) | Pass |
| WordPress 6.6 minimum integration | WordPress 6.6.2 / PHP 8.3.13: kit 8, core 322, plugin 18, theme 16 | Pass |
| Release artifact build and reproducibility | `make release THEME=jooservices WP=7.1.3`: five ZIP artifacts written to `artifacts/release/jooservices/0.1.0`, `reproducible: true` | Pass |
| UI/E2E | No theme UI code changed in this ticket | Not applicable |
| 8080 demo | Not accessed | Not applicable |

## Risks and manual follow-up

- This branch validates the pipeline against the current 0.1.0 lab product. Rebuild the ThemeForest submission candidate from the final 1.0 release commit after the parallel feature and QA work is merged; RTL is not in this checkout yet.
- Add the approved public showcase URL to the item description and recheck ThemeForest's current upload, thumbnail, preview, title, and tag rules immediately before submission.
- CI is prepared but cannot be run from this checkout because no remote is configured. No push or marketplace submission was performed.

## Proposed backlog

No new out-of-scope finding was identified for this ticket. The final RTL feature and showcase URL are existing release dependencies and are tracked outside JOOS-103.
