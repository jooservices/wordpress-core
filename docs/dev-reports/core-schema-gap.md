# Core schema keyword and runtime gap inventory

## Scope and method

This report inventories the JSON Schema resources validated by `tools/validate-schemas.mjs`: the theme manifest, block, and resume company schemas. `packages/theme-core/schema/settings.schema.json` is a settings descriptor consumed by the settings tooling, not a JSON Schema resource; its descriptor fields are checked separately by `tools/validate-schemas.mjs` and are not counted as JSON Schema keywords. The runtime `SchemaValidator` validates only the manifest schema.

The Ajv side uses the same strict Ajv 2020 configuration as `tools/validate-schemas.mjs`. The runtime side calls `SchemaValidator::validate()` after PHP's production-style associative `json_decode`. The input set contains every spec example and theme manifest discovered by the schema tool, every invalid manifest fixture, and two focused representation edge cases.

## Keyword inventory

| JSON Schema keyword | Schema resources using it | Runtime validator handling |
| --- | --- | --- |
| `$defs` | `plugins/jooservices-resume/schema/company.schema.json` | Not implemented |
| `$id` | `packages/theme-core/schema/manifest.schema.json`<br>`packages/theme-core/schema/block.schema.json`<br>`plugins/jooservices-resume/schema/company.schema.json` | Ignored (no validation effect) |
| `$ref` | `plugins/jooservices-resume/schema/company.schema.json` | Not implemented |
| `$schema` | `packages/theme-core/schema/manifest.schema.json`<br>`packages/theme-core/schema/block.schema.json`<br>`plugins/jooservices-resume/schema/company.schema.json` | Ignored (no validation effect) |
| `additionalProperties` | `packages/theme-core/schema/manifest.schema.json`<br>`packages/theme-core/schema/block.schema.json`<br>`plugins/jooservices-resume/schema/company.schema.json` | Implemented |
| `const` | `packages/theme-core/schema/block.schema.json` | Not implemented |
| `description` | `packages/theme-core/schema/manifest.schema.json`<br>`plugins/jooservices-resume/schema/company.schema.json` | Ignored (no validation effect) |
| `enum` | `packages/theme-core/schema/manifest.schema.json`<br>`packages/theme-core/schema/block.schema.json`<br>`plugins/jooservices-resume/schema/company.schema.json` | Implemented |
| `items` | `packages/theme-core/schema/manifest.schema.json`<br>`packages/theme-core/schema/block.schema.json`<br>`plugins/jooservices-resume/schema/company.schema.json` | Implemented |
| `maximum` | `packages/theme-core/schema/manifest.schema.json` | Implemented |
| `maxItems` | `packages/theme-core/schema/block.schema.json`<br>`plugins/jooservices-resume/schema/company.schema.json` | Not implemented |
| `maxLength` | `packages/theme-core/schema/manifest.schema.json`<br>`plugins/jooservices-resume/schema/company.schema.json` | Implemented |
| `minimum` | `packages/theme-core/schema/manifest.schema.json`<br>`plugins/jooservices-resume/schema/company.schema.json` | Implemented |
| `minItems` | `packages/theme-core/schema/manifest.schema.json`<br>`packages/theme-core/schema/block.schema.json` | Implemented |
| `minLength` | `packages/theme-core/schema/manifest.schema.json`<br>`packages/theme-core/schema/block.schema.json`<br>`plugins/jooservices-resume/schema/company.schema.json` | Implemented |
| `not` | `packages/theme-core/schema/block.schema.json` | Not implemented |
| `oneOf` | `plugins/jooservices-resume/schema/company.schema.json` | Not implemented |
| `pattern` | `packages/theme-core/schema/manifest.schema.json`<br>`packages/theme-core/schema/block.schema.json`<br>`plugins/jooservices-resume/schema/company.schema.json` | Implemented |
| `patternProperties` | `packages/theme-core/schema/manifest.schema.json` | Partial: runtime only recognizes the ^x- pattern |
| `properties` | `packages/theme-core/schema/manifest.schema.json`<br>`packages/theme-core/schema/block.schema.json`<br>`plugins/jooservices-resume/schema/company.schema.json` | Implemented |
| `propertyNames` | `plugins/jooservices-resume/schema/company.schema.json` | Not implemented |
| `required` | `packages/theme-core/schema/manifest.schema.json`<br>`packages/theme-core/schema/block.schema.json`<br>`plugins/jooservices-resume/schema/company.schema.json` | Implemented |
| `title` | `packages/theme-core/schema/manifest.schema.json`<br>`packages/theme-core/schema/block.schema.json`<br>`plugins/jooservices-resume/schema/company.schema.json` | Ignored (no validation effect) |
| `type` | `packages/theme-core/schema/manifest.schema.json`<br>`packages/theme-core/schema/block.schema.json`<br>`plugins/jooservices-resume/schema/company.schema.json` | Implemented |
| `uniqueItems` | `plugins/jooservices-resume/schema/company.schema.json` | Not implemented |

The manifest schema uses no unimplemented validation keywords; its only partial keyword use is `patternProperties: ^x-`, which matches the runtime validator's special-case implementation.

## Differential results

| Input set | Manifest | Ajv | Runtime | Different? |
| --- | --- | --- | --- | --- |
| Spec example | `docs/spec/examples/jooservices.json` | Accept | Accept | No |
| Spec example | `docs/spec/examples/magazine.json` | Accept | Accept | No |
| Spec example | `docs/spec/examples/portfolio.json` | Accept | Accept | No |
| Spec example | `docs/spec/examples/starter.json` | Accept | Accept | No |
| Theme manifest | `themes/jooservices/joo-theme.json` | Accept | Accept | No |
| Theme manifest | `themes/starter/joo-theme.json` | Accept | Accept | No |
| Invalid fixture | `packages/theme-core/tests/Fixtures/manifest-invalid/bad-core-range.json` | Reject | Reject | No |
| Invalid fixture | `packages/theme-core/tests/Fixtures/manifest-invalid/bad-enum.json` | Reject | Reject | No |
| Invalid fixture | `packages/theme-core/tests/Fixtures/manifest-invalid/bad-slug.json` | Reject | Reject | No |
| Invalid fixture | `packages/theme-core/tests/Fixtures/manifest-invalid/bad-type.json` | Reject | Reject | No |
| Invalid fixture | `packages/theme-core/tests/Fixtures/manifest-invalid/missing-required.json` | Reject | Reject | No |
| Invalid fixture | `packages/theme-core/tests/Fixtures/manifest-invalid/unknown-key.json` | Reject | Reject | No |
| Invalid edge case | `Synthetic edge case: array for object-valued features` | Reject | Accept | Yes: Ajv reject, runtime accept |
| Valid edge case | `Synthetic edge case: numeric key in object-valued modules` | Accept | Reject | Yes: Ajv accept, runtime reject |

Observed differences:

- **Synthetic edge case: array for object-valued features:** PHP associative decoding represents both an empty JSON array and empty JSON object as an empty array.
- **Synthetic edge case: numeric key in object-valued modules:** PHP associative decoding converts the numeric object key to integer key 0, which the runtime list check classifies as an array.

The two edge-case differences are recorded as expected findings. Other differences, or a change to either edge-case decision, fail the differential check so the report and test cannot silently drift.
