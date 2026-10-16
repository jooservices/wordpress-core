# JOOS-87 — Resume company data model (release 1.0)

## Changes

- New `Model\Period`, `Model\Repeaters` (pure PHP, unit-tested), `Model\CompanyRecord`, `Model\CompanyRepository`, `Model\Keys` and `Modules\CompanyDataModel` in `jooservices-resume`.
- Fields: location (city, country), employment type, industry, remote flag, derived `current` and duration text, several roles per company (inside the company period, no overlaps), limited rich-text description, card summary, achievements (text, metric, unit, link), technologies, owner-defined custom fields (definitions in an option, values per company), compensation and equity as private fields with a per-field switch to public.
- REST: writes are validated with clear 400 messages; visitors never receive private meta or private custom values (`rest_prepare_joo_company`). The theme-core content provider still reads only the old keys, so nothing new reaches the front end until JOOS-89.
- `schema/company.schema.json` (public projection) with an example and two invalid fixtures, validated by `tools/validate-schemas.mjs`; naming registry updated (14 meta keys, 1 option); module documentation updated.
- Backward compatible: companies with only the old keys read as before (new fields empty, tested).

## Verification

| Check | Result |
|---|---|
| `make lint`, `make analyse` | pass |
| `make test-unit` | PHPUnit 265 tests / 1,368 assertions (new: `PeriodTest`, `RepeatersTest`); Vitest 247 |
| Integration, plugin suite | WordPress 7.1.3 and 6.6.2: 24 tests / 184 assertions (6 new in `CompanyDataModelTest`: fields round trip, rejected data with messages, private fields for visitors and switched public, ordering and legacy companies, definitions, messages) |
| `node tools/validate-schemas.mjs`, `check-registry.mjs` | pass (143 names) |
| Coverage (merged) | new code 99-100% lines (`Period` 100, `Repeaters` 100, `CompanyRecord` 100, `CompanyRepository` 98.4, `CompanyDataModel` 99.4); repository gate passes |

## Risks and not done

- No admin screens yet: the classic company box still edits only the old fields; the new fields are stored and validated through REST (JOOS-88 builds the repeater screens, reorder, custom-field builder, import/export).
- The front end, shortcode and schema.org output of the new fields are JOOS-89.
- Technologies are free tags; linking them to a Skills module is later work.
- Compensation and equity are plain text; a currency or period structure can be added if a buyer asks.
