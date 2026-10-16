# JOOS-88 — Resume admin screens (release 1.0)

## Decisions
- **Classic meta boxes + vanilla JS** (no build step) instead of block-editor panels: the company screen is classic, controls are native labelled fields, and every action is keyboard operable.
- **Reorder with up/down buttons** (and live-region announcements) instead of drag and drop; this is the keyboard alternative the ticket asks for and is fully testable.
- **Import is all or nothing**: the file is validated as a whole first (dry run) and nothing is created if any company has an error.

## Changes
- `Admin\CompanyEditor`, `CompanyList`, `Settings`, `ImportExport`; `Transfer\Mapper` (own JSON, JSON Resume `work`, CSV); `Model\CompanyWriter`; assets `company-editor.js` and `admin.css`.
- Editor: profile and location, roles, achievements, technologies, custom fields, private details with the per-field public switch, manual order; unsaved-changes warning; per-group validation messages.
- List: logo, current, visibility, order columns; filter; bulk pin, hide, show.
- Settings: display defaults and the custom-field builder with preview. Import and export pages with nonce, capability, size and type limits, formula-safe CSV.
- Fix: `Company::adminOrder` no longer assumes `orderby` is a string (it warned when a list filter or another hook had set an array).
- Registry and uninstall updated (`joo_resume_settings`); module docs updated.

## Verification
| Check | Result |
|---|---|
| `make lint`, `make analyse` | pass |
| `make test-unit` | PHPUnit 272 tests (new `MapperTest`), Vitest 247 |
| Plugin integration (WordPress 7.1.3) | 34 tests / 297 assertions: new `AdminScreensTest` (editor render and save, settings, list, bulk, exports, dry-run import, upload limits, permissions) |
| Plugin integration (WordPress 6.6.2) | 34 tests pass |
| Regression Playwright `specs/admin` and `specs/shared` | 130 passed, 11 skipped by design (1.6 min on a quiet host; the same run took 30 min with failures while the Docker VM was out of memory) |
| Playwright `resume-admin.spec.ts` | 16 passed (phone, tablet, desktop axe and no horizontal scroll on the four screens; desktop keyboard-only flow: custom field, company with 2 roles, reorder, validation message, export, dry run, import, filter, visitor redirect) |
| Merged coverage of new code | about 94% lines (`Mapper` 100, `CompanyRecord` 100, `CompanyEditor` 96, `CompanyList` 98, `Settings` 94, `ImportExport` and `CompanyWriter` 80 to 82: the redirect and download branches end the request and are covered by the Playwright flow) |

## Risks and not done
- The spec cleans its own companies and custom-field definitions through REST (the list bulk-action UI proved unreliable) at the start and the end of the desktop flow.
- Docker host memory: the Docker VM has 16 GB and was at 13.4 GB used; Chromium crashed several times (`Target crashed`) until retried. Raising the Docker Desktop memory limit (the host has 36 GB) would remove this flakiness; vitest timeouts were raised to 30 s for the same reason.
- Quick edit of the order in the list is not provided (the order field is on the edit screen and the Order column is sortable).
- No drag and drop (buttons only). No Arabic/RTL review of these screens (WordPress admin handles `dir`; not verified here).
- The settings UI is a dedicated page, not the theme-core schema-driven UI.
- The acceptance scenario (6 companies, 3 roles, 10 achievements, 3 custom fields, export and re-import with no loss) is unit-tested as a round trip (`MapperTest::test_a_full_resume_round_trips_without_loss`); the UI flow is exercised with 2 roles and 1 achievement.
