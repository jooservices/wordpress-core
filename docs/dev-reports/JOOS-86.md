# JOOS-86 — JOOservices Resume rename and productization

## Changes

- Renamed the plugin directory and main file to `plugins/jooservices-resume/jooservices-resume.php`; updated its product header, slug, text domain, PHP namespace, constants, options, script/style handles, language files, package configuration, Composer mappings, Docker mounts, Make targets, naming registry, tests, and docs.
- Kept `joo_company`, `_joo_company_*`, and `joo/v1` stable as data and REST contracts. Updated theme content-provider detection to `JOO_RESUME_FILE`.
- Grouped profile, topic/category, and featured-post fields in the named `ProfileAndEditorialFields` module. Added Site Health copy, plugin readme, bilingual catalogs, and a data-preserving uninstall policy with an explicit delete opt-in.
- Added a mechanical rename script and a grep-based Vitest guard that rejects old identifiers except for the single documented compatibility note. Added packaging, plugin integration, and reusable Playwright admin coverage.
- Made captured critical CSS use same-origin asset paths so it remains portable between local ports and production hosts.
- No old-slug data migration was added, per the owner decision that no public installs exist.

## Verification

| Command | Result |
| --- | --- |
| `make lint` | Pass: headers, PHPCS, ESLint, Stylelint, and Prettier. |
| `make analyse` | Pass: PHPStan, TypeScript, and Deptrac; 0 violations, warnings, or errors. |
| `node tools/check-registry.mjs` | Pass: 128 names, 60 settings keys, 280 source files scanned. |
| `node tools/validate-schemas.mjs` | Pass: schemas, examples, block descriptors, and settings descriptor. |
| `make i18n` on WordPress 7.1.3 and 6.6.2 | Pass: core 190 strings, Resume 45, starter 27, jooservices 44. |
| `node tools/i18n.mjs --check` | Pass: every shipped string is translated in all four catalogs. |
| `make test-unit` | Pass: PHPUnit 256 tests / 1,316 assertions; Vitest 198 tests across 33 files. |
| `make test-integration TEST_THEME=''` on WordPress 7.1.3 | Pass: 363 tests / 1,744 assertions; 15 skipped. |
| Plugin and theme integration suites with `TEST_THEME=jooservices` on WordPress 7.1.3 | Pass: plugin 18 tests / 113 assertions; theme 15 tests / 198 assertions, 7 starter-only skips. |
| Plugin and theme integration suites with `TEST_THEME=jooservices` on WordPress 6.6.2 | Pass: plugin 18 tests / 113 assertions; theme 15 tests / 198 assertions, 7 starter-only skips. |
| WordPress 6.6.2 full integration suite | 362 tests / 1,724 assertions / 15 skips pass when excluding `test_boot_stays_within_the_budget`. The unfiltered run has one timing failure: the 25 ms module boot limit measured 76.066 ms for i18n; a focused retry measured 78.412 ms for compat. |
| Playwright admin plus shared content API, smoke, and views specs on WordPress 7.1.3 | Pass after `make seed-reset THEME=jooservices`: 59 passed, 1 intentional phone skip across phone/tablet/desktop. |
| `wp plugin check jooservices-resume` on WordPress 6.6.2 | Pass with no errors. Remaining warnings are the WordPress.org discouraged `load_plugin_textdomain()` call (kept for bundled catalogs) and unprefixed test-only globals/hooks/constants. |
| `git diff --check` | Pass. |

### Coverage

- Changed executable PHP lines: **91/91 (100%)** from merged PHPUnit Clover data.
- New feature code: `ProfileAndEditorialFields.php` **3/3 (100%)**; `uninstall.php` **19/19 (100%)**.
- Merged PHP package coverage: theme core **92.54% (6,228/6,730 statements)**; Resume plugin **88.24% (548/621 statements)**. The repository package gate is 85% for core and 80% for the plugin; changed-line coverage is above the requested 90% target.
- PHPUnit coverage data used 251 unit tests / 1,296 assertions (the five `BuildAssetsTest` cases need a writable build directory, unavailable in the read-only Docker source mount) plus 362 WordPress 6.6 integration tests / 1,724 assertions (excluding only the boot-time budget test).
- Vitest coverage: **97.74% lines (736/753)**, 96.55% statements, 95.70% functions, and 86.32% branches. The new critical CSS URL helper has a direct test; tooling sources are outside the repository's configured Vitest coverage include, so this aggregate describes the configured package/theme JavaScript sources.

## Risks and remaining verification

- The WordPress 6.6 module boot-time budget test is sensitive to runtime speed and exceeded its fixed 25 ms ceiling twice (76–78 ms); all other tests passed when that single test was filtered. Latest WordPress passed the unfiltered full suite.
- Running every integration suite in one themed process (`TEST_THEME=jooservices` without a suite selector) has a known cross-suite bootstrap/reset issue; the plugin and theme suites pass separately on both WordPress versions. The unthemed full suite passes on latest and passes on 6.6 with only the timing test excluded.
- A first E2E run used a newly installed site without the expected view fixtures. Seeding via the documented `make seed-reset` resolved the missing routes and all relevant E2E specs passed. The critical CSS host URLs were made same-origin; final browser tests targeted only port 8380. The owner's main stack was not changed.
- Docker initially reported that its predefined address pools were exhausted. The isolated `joowt-wt-86` project used a dedicated `10.255.86.0/24` network; the project was stopped and reset after verification, including removal of its volumes. The `jooservices-wp-themes` project was left running and untouched.

## Proposed backlog

- Stabilize the module boot-time test on the WordPress 6.6 Docker runner and isolate the combined themed integration suites from cross-suite bootstrap state.
- Submit the product listing and prepare marketplace assets after QA review; no publication work was part of this ticket.

QA review remains with Claude. This branch is intentionally unmerged and unpushed.
