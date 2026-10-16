# JOOS-100 — ThemeForest marketplace compliance

## Changes

- Added `make test-marketplace`, which installs/activates the WordPress Theme
  Check and Plugin Check plugins in the serialized shared WordPress test site
  and runs their WP-CLI commands.
- Added a GitHub Actions `marketplace` job using an isolated `wp-env` site. It
  builds the installable theme and checks the theme and JOOservices Resume
  plugin with the official WordPress.org tools.
- Added a reusable E2E privacy check for third-party requests, cookies, and
  tracking-like storage keys on the public theme homepage.
- Added `make capture-marketplace-preview` to seed the shared test site with
  synthetic content and capture the 1200×900 theme screenshot plus five
  preview drafts only after checking for third-party requests, then remove
  only the JOOS-100 test database and uploads volume under the shared lock.
- Added Envato-focused release checklist and draft listing/preview materials,
  product license inventories, and credits. Added GPL license copies to both
  installable product directories and copyright headers to the theme/plugin.

## Verification

| Check | Result | Evidence |
| --- | --- | --- |
| `make lint` | Pass | Header check, PHPCS (261 files; one skipped), ESLint, Stylelint, and Prettier. |
| `make analyse` | Pass | Both PHPStan runs report no errors; TypeScript checks pass; Deptrac reports 0 violations, 0 uncovered. |
| `make test-unit` | Pass | PHPUnit: 256 tests / 1,316 assertions; Vitest: 198 tests across 33 files. |
| Theme package | Pass | `make NODE='bash docker/scripts/runtime.sh php-node' package THEME=jooservices`; 297 files, 726 KB. Archive inventory includes GPL files and both OFL font licenses. |
| Resume plugin package | Pass | `tools/build-plugin.mjs --plugin jooservices-resume`; 17 files, 32 KB. |
| `make plugins-package` | Partial | Resume archive built; command then failed because optional WordPress MCP plugin source is unavailable (`MCP_PLUGIN_SRC` unset). |
| `make test-marketplace` | In progress | The first source-directory run failed because it scanned excluded development tests/design and reported Theme Check required items. The target now builds and mounts the installable archive for a rerun. |
| Integration/E2E | Pending | Attempts timed out waiting 15 minutes for the shared WordPress lock held by JOOS-107; no JOOS-100 integration or browser tests started. |
| GitHub Actions marketplace job | Not run | This repository has no remote; no push was made. YAML/Markdown formatting passes Prettier. |

## Risks and remaining review gates

- ThemeForest requires a TGMPA-based install flow for the bundled Resume
  plugin, with ThemeForest-generated TGMPA source and user-controlled
  installation. The official generator is currently unreachable from the
  available browser/network path; no generic upstream TGMPA file was
  substituted. This acceptance item remains open.
- `themes/jooservices/screenshot.png` is 1200×900 but shows a personal name
  and real employer names. It is documented as uncleared and must be replaced
  with an owner-reviewed showcase image before listing or sale.
- Theme metadata and package metadata currently label the theme
  GPL-2.0-or-later. Owner/legal review must reconcile this with Envato's
  split-license statement for presentation assets.
- The proposed support period, response time, public demo URL, listing copy,
  and marketing preview images still need owner approval. Do not publish the
  draft copy as a commitment.
- Formal trademark-register review for the theme and plugin names is still
  an owner/legal release gate.
- The compatibility matrix records the agreed targets, but versioned checks
  on every desktop browser plus iOS Safari and Android Chrome remain pending.
- The ThemeForest-specific Envato Theme Check run is distinct from the
  WordPress.org Theme Check job and still needs a recorded pre-submission
  report.
- The first source-based WordPress Theme Check also reported a missing
  `readme.txt`, two theme-territory SEO `remove_action` calls, and the
  `dark-mode` tag. The package-based rerun will show which notices remain in
  the actual archive.

## Proposed backlog

- [JOOS-110](https://jooservices.atlassian.net/browse/JOOS-110): harden
  `wp-task.sh` lock acquisition so a runner waits through the brief interval
  between creating the shared lock directory and writing its PID file. This
  gap caused one immediate attempt to fail with “lock exists without a
  readable owner”; the later retry correctly waited for JOOS-107.

## Release disposition

Documentation and automated WordPress.org checks are prepared for review,
but this ticket is not ready for marketplace submission until the TGMPA,
Envato Theme Check, screenshot, license decision, and owner-review gates above
have evidence.
