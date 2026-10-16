# JOOS-102 — Licence-clean buyer demo importer

## Changes

- Added deterministic Tier B demo package generation from the synthetic WXR seed, with generated PNG media, fictionalized author/company data, SHA-256 checksums, structure counts, menu and theme settings, language/widget/pattern declarations, licence terms, and per-file attribution.
- Added the buyer ZIP at `artifacts/package/jooservices-demo-content.zip` and included it in the ThemeForest package under `Demo-Content/`.
- Added WP-CLI commands through JOOservices Resume: `wp joo demo import <zip> --dry-run`, `wp joo demo import <zip> [--batch-size=N]`, `wp joo demo diff <zip>`, and `wp joo demo rollback [--yes]`.
- The importer validates safe ZIP paths, checksums, WXR 1.2, Tier B licence metadata, PNG media and the 40 MB limits; it refuses conflicting users, slugs, terms, menus, or unsupported content before writing.
- Imported users, posts, media, terms, comments, menus, and managed options are recorded in a package-scoped ledger. Re-imports are no-ops; rollback only removes matching marked objects and restores managed settings that have not been changed since import.
- Added unit/integration coverage, a serialized shared-stack E2E command, and buyer documentation for dry-run, progress, structural diff, rollback, fallback import, and reuse terms.

## Verification

| Check | Result |
|---|---|
| `make lint` | Pass: headers, PHPCS (288 files), ESLint, Stylelint, and Prettier. |
| `make analyse` | Pass: both PHPStan configurations, both TypeScript checks, and Deptrac; zero errors or violations. |
| `make test-unit` | PHP: pass (280 tests, 1,426 assertions). Vitest: first run failed under concurrent E2E load (22/23 files and 104/105 tests passed; the font subset test hit its 30-second timeout and 17 worker starts timed out). A single-worker retry is pending. |
| `make test-integration THEME=jooservices ARGS='--filter DemoImporterTest'` | Initial run found pre-existing `news` and `php` terms, so the fixture now uses unique term slugs. The next acquired run exposed a missing `</wp:comment>` in fixture WXR (2 errors, 1 failure across 4 tests); fixed and locally parsed as WXR 1.2. The corrected retry then timed out after 15 minutes waiting for JOOS-89's live shared lock, so importer integration still needs a shared-stack run. |
| `make test-e2e-demo THEME=jooservices` | Failed safely at dry-run because the existing seeded site has conflicting author, category, and tag slugs. Import stopped before writes, so no demo content was added; this gate needs a clean WordPress site. |
| `make demo-package` | Pass: 763,032 bytes compressed, 25 files, below the 40 MB limit. |
| `make release THEME=jooservices` | Pass: generated a 2,623 KB ThemeForest archive containing the 763,032-byte demo ZIP; two builds compared byte-for-byte reproducible. |
| `tools/release.test.mjs` | Pass: 13 tests, including buyer package contents and licensing metadata. |
| Targeted PHPCS, shell syntax, and `git diff --check` | Pass. |
| Merge `develop` | Pass: merged latest local `develop` (including JOOS-118/120 docs) with no conflicts; the earlier merge had also brought in the JOOS-116 buyer Resume guide. |

## Risks

- The generated content and PNGs are documented as CC BY 4.0 with attribution to “JOOservices Demo Content.” The package is prepared for the selected ThemeForest channel; owner review and approval of the licence statement remains a release acceptance item.
- The importer path is WP-CLI. Buyers without WP-CLI can use the documented WordPress Importer fallback; that fallback covers WXR content and requires manual media, menu, and settings steps.

## Proposed backlog

No out-of-scope findings identified during implementation.
