// Compare the Ajv manifest checks with the runtime SchemaValidator and write the gap inventory.
import { spawnSync } from 'node:child_process';
import { readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { fromRoot } from './lib/paths.mjs';
import { createSchemaValidator } from './lib/schema-validator.mjs';

const manifestSchemaPath = 'packages/theme-core/schema/manifest.schema.json';
const manifestSchema = JSON.parse(readFileSync(fromRoot(manifestSchemaPath), 'utf8'));
const ajv = createSchemaValidator();
const validateManifest = ajv.compile(manifestSchema);

const schemaKeywordNames = new Set([
  '$anchor',
  '$comment',
  '$defs',
  '$dynamicAnchor',
  '$dynamicRef',
  '$id',
  '$ref',
  '$schema',
  '$vocabulary',
  'additionalItems',
  'additionalProperties',
  'allOf',
  'anyOf',
  'const',
  'contains',
  'contentEncoding',
  'contentMediaType',
  'contentSchema',
  'default',
  'definitions',
  'dependentRequired',
  'dependentSchemas',
  'deprecated',
  'description',
  'else',
  'enum',
  'examples',
  'exclusiveMaximum',
  'exclusiveMinimum',
  'format',
  'if',
  'items',
  'maxContains',
  'maximum',
  'maxItems',
  'maxLength',
  'maxProperties',
  'minContains',
  'minimum',
  'minItems',
  'minLength',
  'minProperties',
  'multipleOf',
  'not',
  'oneOf',
  'pattern',
  'patternProperties',
  'prefixItems',
  'properties',
  'propertyNames',
  'readOnly',
  'required',
  'then',
  'title',
  'type',
  'unevaluatedItems',
  'unevaluatedProperties',
  'uniqueItems',
  'writeOnly',
]);

/** @type {Array<{label: string, kind: string, json: string, expectedAjvValid: boolean}>} */
const samples = [];

function addFileSample(path, kind, expectedAjvValid) {
  samples.push({
    label: path,
    kind,
    json: readFileSync(fromRoot(path), 'utf8'),
    expectedAjvValid,
  });
}

for (const file of readdirSync(fromRoot('docs/spec/examples'))
  .filter((name) => name.endsWith('.json'))
  .sort()) {
  addFileSample(join('docs/spec/examples', file), 'Spec example', true);
}
for (const theme of readdirSync(fromRoot('themes')).sort()) {
  const path = join('themes', theme, 'joo-theme.json');
  try {
    readFileSync(fromRoot(path));
    addFileSample(path, 'Theme manifest', true);
  } catch {
    // A theme without a manifest is not an input to tools/validate-schemas.mjs.
  }
}
for (const file of readdirSync(fromRoot('packages/theme-core/tests/Fixtures/manifest-invalid'))
  .filter((name) => name.endsWith('.json'))
  .sort()) {
  addFileSample(join('packages/theme-core/tests/Fixtures/manifest-invalid', file), 'Invalid fixture', false);
}

const emptyArrayObjectSample = {
  schema: 1,
  slug: 'edge-case',
  name: 'Edge case',
  core: '^0.1',
  features: [],
};
samples.push({
  label: 'Synthetic edge case: array for object-valued features',
  kind: 'Invalid edge case',
  json: JSON.stringify(emptyArrayObjectSample),
  expectedAjvValid: false,
});

const numericObjectKeySample = {
  schema: 1,
  slug: 'edge-case',
  name: 'Edge case',
  core: '^0.1',
  modules: { 0: true },
};
samples.push({
  label: 'Synthetic edge case: numeric key in object-valued modules',
  kind: 'Valid edge case',
  json: JSON.stringify(numericObjectKeySample),
  expectedAjvValid: true,
});

const runtime = spawnSync('php', ['tools/schema-validator-probe.php'], {
  cwd: fromRoot(),
  input: JSON.stringify({ samples: samples.map(({ label, json }) => ({ label, json })) }),
  encoding: 'utf8',
  maxBuffer: 4 * 1024 * 1024,
});
if (runtime.error) throw runtime.error;
if (runtime.status !== 0) {
  throw new Error(`Runtime validator probe failed: ${runtime.stderr.trim()}`);
}

let runtimeOutput;
try {
  runtimeOutput = JSON.parse(runtime.stdout);
} catch (error) {
  throw new Error(`Runtime validator probe returned invalid JSON: ${error.message}`, { cause: error });
}
if (runtimeOutput.results.length !== samples.length) {
  throw new Error(`Runtime returned ${runtimeOutput.results.length} results for ${samples.length} samples.`);
}

const keywords = new Map();
const foundSchemaFiles = [
  'packages/theme-core/schema/manifest.schema.json',
  'packages/theme-core/schema/block.schema.json',
  'plugins/jooservices-resume/schema/company.schema.json',
];
const schemaMapKeywordNames = ['properties', 'patternProperties', '$defs', 'definitions', 'dependentSchemas'];
const schemaSingleKeywordNames = [
  'items',
  'additionalProperties',
  'additionalItems',
  'contains',
  'propertyNames',
  'not',
  'if',
  'then',
  'else',
  'contentSchema',
  'unevaluatedItems',
  'unevaluatedProperties',
];
const schemaArrayKeywordNames = ['allOf', 'anyOf', 'oneOf', 'prefixItems'];

function recordKeyword(keyword, file) {
  if (!keywords.has(keyword)) keywords.set(keyword, new Set());
  keywords.get(keyword).add(file);
}

function visitSchema(node, file) {
  if (!node || typeof node !== 'object' || Array.isArray(node)) return;
  for (const [keyword, value] of Object.entries(node)) {
    if (schemaKeywordNames.has(keyword)) recordKeyword(keyword, file);
    if (schemaMapKeywordNames.includes(keyword) && value && typeof value === 'object' && !Array.isArray(value)) {
      for (const child of Object.values(value)) visitSchema(child, file);
    } else if (schemaSingleKeywordNames.includes(keyword)) {
      visitSchema(value, file);
    } else if (schemaArrayKeywordNames.includes(keyword) && Array.isArray(value)) {
      for (const child of value) visitSchema(child, file);
    }
  }
}

for (const file of foundSchemaFiles) visitSchema(JSON.parse(readFileSync(fromRoot(file), 'utf8')), file);

const supported = new Set(runtimeOutput.supported);
const annotations = new Set(runtimeOutput.annotations);
function handling(keyword) {
  if (keyword === 'patternProperties') return 'Partial: runtime only recognizes the ^x- pattern';
  if (supported.has(keyword)) return 'Implemented';
  if (annotations.has(keyword)) return 'Ignored (no validation effect)';
  return 'Not implemented';
}

const outcomes = samples.map((sample, index) => {
  const manifest = JSON.parse(sample.json);
  const ajvValid = validateManifest(manifest);
  const runtimeResult = runtimeOutput.results[index];
  if (runtimeResult.label !== sample.label) {
    throw new Error(`Runtime result order mismatch at ${sample.label}.`);
  }
  if (ajvValid !== sample.expectedAjvValid) {
    throw new Error(`${sample.label}: Ajv result was ${ajvValid}, expected ${sample.expectedAjvValid}.`);
  }
  const difference = ajvValid !== runtimeResult.valid;
  if (difference) {
    throw new Error(
      `${sample.label}: unexpected Ajv/runtime difference (Ajv ${ajvValid}, runtime ${runtimeResult.valid}).`
    );
  }
  return { ...sample, ajvValid, runtimeValid: runtimeResult.valid, difference, errors: runtimeResult.errors };
});

const rows = [...keywords.entries()]
  .sort(([left], [right]) => left.localeCompare(right))
  .map(
    ([keyword, files]) =>
      `| \`${keyword}\` | ${[...files].map((file) => `\`${file}\``).join('<br>')} | ${handling(keyword)} |`
  );
const resultRows = outcomes.map((outcome) => {
  const ajvDecision = outcome.ajvValid ? 'Accept' : 'Reject';
  const runtimeDecision = outcome.runtimeValid ? 'Accept' : 'Reject';
  const difference = outcome.difference
    ? `Yes: Ajv ${ajvDecision.toLowerCase()}, runtime ${runtimeDecision.toLowerCase()}`
    : 'No';
  return `| ${outcome.kind} | \`${outcome.label}\` | ${ajvDecision} | ${runtimeDecision} | ${difference} |`;
});
const manifestKeywords = [...keywords.entries()].filter(([, files]) => files.has(manifestSchemaPath));
const manifestUnsupported = manifestKeywords
  .filter(([keyword]) => !supported.has(keyword) && !annotations.has(keyword))
  .map(([keyword]) => keyword);

const report = `# Core schema keyword and runtime gap inventory

## Scope and method

This report inventories the JSON Schema resources validated by \`tools/validate-schemas.mjs\`: the theme manifest, block, and resume company schemas. \`packages/theme-core/schema/settings.schema.json\` is a settings descriptor consumed by the settings tooling, not a JSON Schema resource; its descriptor fields are checked separately by \`tools/validate-schemas.mjs\` and are not counted as JSON Schema keywords. The runtime \`SchemaValidator\` validates only the manifest schema.

The Ajv side uses the same strict Ajv 2020 configuration as \`tools/validate-schemas.mjs\`. The runtime side calls \`SchemaValidator::validate()\` with PHP's object-preserving \`json_decode\`, matching production validation before manifest values are normalized for runtime use. The input set contains every spec example and theme manifest discovered by the schema tool, every invalid manifest fixture, and two focused representation edge cases.

## Keyword inventory

| JSON Schema keyword | Schema resources using it | Runtime validator handling |
| --- | --- | --- |
${rows.join('\n')}

The manifest schema uses ${manifestUnsupported.length ? `unimplemented validation keywords: ${manifestUnsupported.map((keyword) => `\`${keyword}\``).join(', ')}` : 'no unimplemented validation keywords'}; its only partial keyword use is \`patternProperties: ^x-\`, which matches the runtime validator's special-case implementation.

## Differential results

| Input set | Manifest | Ajv | Runtime | Different? |
| --- | --- | --- | --- | --- |
${resultRows.join('\n')}

No differences were observed.

The differential check fails on any Ajv/runtime decision difference, including the focused representation edge cases.
`;

const reportPath = fromRoot('docs/dev-reports/core-schema-gap.md');
writeFileSync(reportPath, report);
for (const outcome of outcomes) {
  console.log(
    `${outcome.difference ? 'DIFF' : 'same'} ${outcome.kind}: ${outcome.label} (Ajv ${outcome.ajvValid ? 'accept' : 'reject'}, runtime ${outcome.runtimeValid ? 'accept' : 'reject'})`
  );
}
console.log(`Wrote ${reportPath}`);
