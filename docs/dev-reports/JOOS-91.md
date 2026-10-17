# JOOS-91 — Showcase content matrix

## Changes

- Added a human-readable matrix and machine-readable `tests/showcase/matrix.json` with 146 unique rows and content keys. It covers the requested post blocks/states, comments, authors, taxonomies, pages, company records, locales/RTL, menus, pagination, terminal data, and all 18 editorial categories.
- Mapped all 68 specification ids currently present in `themes/jooservices/design/checklist.md` to at least one row. Every row records its expected URL/status, content requirements, planned JOOS-97 test id, Product Owner manual-check owner/status, and LTR/RTL verification profiles. The Chinese sample also uses the `zh_CN` profile.
- Added a Node validator that checks row structure, unique ids/keys, source spec coverage, browser/viewport/scheme/locale profiles, implemented test file references, and synchronization of the Markdown table with the JSON.
- Added optional generated-inventory validation for JOOS-93. Its inventory must report every row key as present and match the expected URL and observed HTTP status.

## How to run

```sh
bash docker/scripts/runtime.sh node tools/validate-showcase-matrix.mjs
bash docker/scripts/runtime.sh node node_modules/vitest/vitest.mjs run tools/validate-showcase-matrix.test.mjs --configLoader native --no-cache --maxWorkers=1
```

When JOOS-93 provides its inventory, pass it to the checker:

```sh
bash docker/scripts/runtime.sh node tools/validate-showcase-matrix.mjs --inventory <generated-inventory.json>
```

## Evidence

| Check | Result |
| --- | --- |
| Static matrix validator | Pass: 146 rows and 68 source spec ids. It explicitly reports that generated-site content is not checked without an inventory. |
| Focused Vitest unit tests | Pass: 17 tests. |
| Focused validator coverage | 91.14% lines, 100% functions, 87.28% statements, 82.37% branches (V8; validator module only). |
| `make lint` | Pass: headers, PHPCS, ESLint, Stylelint, and Prettier. |
| `make analyse` | Pass: both PHPStan configurations, TypeScript, and deptrac (0 violations). |
| `git diff --check` | Pass before commit. |

Dependencies were installed once with `make deps`. The repository Node runtime reported v24.21.0.

## Known limits and follow-up

- Product Owner approval and all manual checks remain pending; this report does not mark the matrix approved.
- T2 / JOOS-85 is still in Backlog and has not added per-area spec files under `docs/spec/theme/`. The matrix currently maps every checked-in design-checklist id. Any additional JOOS-85 ids must be mapped before approval.
- The showcase site and generated-content inventory do not exist yet. Actual fixture presence and observed route status therefore remain for JOOS-93; per-row browser tests remain planned for JOOS-97. Current E2E references are labelled partial baselines.
- No browser or WordPress integration run was performed for this contract-only ticket.
