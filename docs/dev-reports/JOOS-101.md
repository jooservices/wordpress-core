# JOOS-101 verification report

## Changes

- Added the English buyer guide for installation, profile and company setup, customization, SEO and performance, languages, plugin compatibility, updates and uninstall, troubleshooting, credits, changelog, and developer extension points.
- Added a five-minute video script and storyboard. The actual recording and buyer-facing hosting remain release work.
- Added reproducible Playwright screenshots for the buyer guide, serialized through the shared WordPress lock.
- Added a buyer Markdown link, heading, and screenshot-name checker to CI and `make docs-check`.
- Limited Vitest's default worker count to four to keep unit runs predictable in the shared runtime.

## Verification

| Check | Result |
|---|---|
| Worktree setup and cleanup | Pass: initialized dependencies and `wp_JOOS_101`, then `make reset CONFIRM=yes` removed the JOOS-101 test database and left shared MariaDB/runtime services running. |
| `make demo THEME=jooservices` | Pass: 17/17 health checks on the JOOS-101 test site. |
| `make lint` | Pass. |
| `make analyse` | Pass: PHPStan clean; dependency analysis reports zero violations. |
| `make test-unit` | Pass: PHPUnit 256 tests / 1,316 assertions; Vitest 212 tests across 35 files. |
| Vitest line coverage | Pass: 96.97% overall; `tools/docs-check.mjs` 90.9%, `tools/screenshots.mjs` 92.2%. |
| `make docs-check` | Pass: generated references and buyer guide links/anchors are valid. |
| Shell syntax and whitespace | Pass: `bash -n docker/scripts/wp-task.sh`; `git diff --check`. |
| Buyer screenshot regeneration | Pass: regenerated and visually inspected both desktop dark-mode screenshots with the fictional showcase profile and companies. |

## Acceptance status

- **Pass:** buyer and developer pages, generated API references, screenshot runner, and local link/anchor plus generated-reference freshness checks. CI wiring is added but was not run on the remote CI service.
- **Pending dependency:** the under-15-minute new-buyer dry run cannot be completed without the buyer-facing demo importer and licence-clean package; existing issue JOOS-102 tracks that work.
- **Pending publication:** searchable help-site hosting and version navigation are not in the Markdown source; JOOS-109 tracks those release requirements.

## Risks and follow-up

- The current theme has no buyer demo importer or licence-clean import package. The under-15-minute first-install dry run is therefore not complete. JOOS-102 already tracks the importer; no duplicate issue was filed.
- A published, searchable, versioned help site is not included in the repository Markdown source. JOOS-109 was filed under JOOS-57 to track publication, site-wide search, and version selection.
- The walkthrough script is ready, but a buyer-facing recording and hosted video link remain outstanding.
- The browser support matrix follows the owner's decision. This task's automated screenshot capture uses the shared Chromium runner; it does not certify the full Safari, Firefox, iOS Safari, and Android Chrome matrix.
- The full WordPress integration and theme E2E suites were not rerun for this documentation and tooling change. Other tickets currently own or queue on the shared WordPress lock; no PHP behavior changed here.
- Two initial showcase sanitizer attempts reached the shared lock's 15-minute timeout without acquiring the lock or making database changes. A later serialized run sanitized every test-site user and company record, and the refreshed screenshots were visually checked for personal data. JOOS-111 tracks the lock wait limit.

## Proposed backlog

- Track the demo importer and buyer dry run in existing issue JOOS-102.
- Track public help-site publication, site-wide search, and release-version navigation in JOOS-109.
- Track long shared WordPress test-lock waits in JOOS-111.
- Record and publish the five-minute setup walkthrough before the ThemeForest release.
