// Ajv configuration shared by schema tooling and the differential report.
import Ajv2020 from 'ajv/dist/2020.js';

/** Create the strict JSON Schema validator used by tools/validate-schemas.mjs. */
export function createSchemaValidator() {
  return new Ajv2020({ strict: true, allErrors: true });
}
