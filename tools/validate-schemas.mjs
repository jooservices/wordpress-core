// Validates manifests against the manifest schema (Ajv 2020, strict) and cross-checks the settings descriptor.
import Ajv2020 from 'ajv/dist/2020.js';
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { fromRoot } from './lib/paths.mjs';

const read = (p) => JSON.parse(readFileSync(p, 'utf8'));
const manifestSchema = read(fromRoot('packages/theme-core/schema/manifest.schema.json'));
const settings = read(fromRoot('packages/theme-core/schema/settings.schema.json'));
const ajv = new Ajv2020({ strict: true, allErrors: true });
const validate = ajv.compile(manifestSchema);
let failures = 0;
let settingProblems = 0;

/** @param {string} label @param {boolean} ok @param {string} [detail] */
function report(label, ok, detail = '') {
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${label}${detail ? ' - ' + detail : ''}`);
  if (!ok) failures += 1;
}

// 1. valid manifests: spec examples and every theme manifest present
const valid = readdirSync(fromRoot('docs/spec/examples'))
  .filter((f) => f.endsWith('.json'))
  .map((f) => fromRoot('docs/spec/examples', f));
for (const theme of readdirSync(fromRoot('themes'))) {
  try {
    readFileSync(fromRoot('themes', theme, 'joo-theme.json'));
    valid.push(fromRoot('themes', theme, 'joo-theme.json'));
  } catch {
    /* theme has no manifest yet */
  }
}
for (const file of valid) {
  const ok = validate(read(file));
  report(`valid   ${file.replace(fromRoot() + '/', '')}`, ok, ok ? '' : ajv.errorsText(validate.errors));
}

// 2. invalid fixtures must be rejected
const invalidDir = fromRoot('packages/theme-core/tests/Fixtures/manifest-invalid');
for (const f of readdirSync(invalidDir)) {
  const ok = !validate(read(join(invalidDir, f)));
  report(`invalid ${f} is rejected`, ok);
}

// 2b. block.json files (shipped blocks, fixtures) against the block schema; viewScriptModule/style are wired from the build
const blockSchema = read(fromRoot('packages/theme-core/schema/block.schema.json'));
const validateBlock = ajv.compile(blockSchema);
const blockDirs = [
  fromRoot('packages/theme-core/resources/blocks'),
  fromRoot('packages/theme-core/tests/Fixtures/blocks'),
  ...readdirSync(fromRoot('themes')).map((t) => fromRoot('themes', t, 'blocks')),
];
for (const root of blockDirs) {
  let entries;
  try {
    entries = readdirSync(root);
  } catch {
    continue;
  }
  for (const entry of entries) {
    const file = join(root, entry, 'block.json');
    let doc;
    try {
      doc = read(file);
    } catch {
      continue;
    }
    const ok = validateBlock(doc);
    report(`block   ${file.replace(fromRoot() + '/', '')}`, ok, ok ? '' : ajv.errorsText(validateBlock.errors));
  }
}

// 3. settings descriptor: every key complete, defaults match type/enum/range, groups exist
const groups = new Set(settings.groups.map((g) => g.id));
const sanitizers = new Set([
  'bool',
  'int_range',
  'enum',
  'text',
  'attachment_id',
  'user_id',
  'term_ids',
  'string_list',
]);
const typeOk = {
  boolean: (v) => typeof v === 'boolean',
  integer: Number.isInteger,
  string: (v) => typeof v === 'string',
  array: Array.isArray,
};
for (const [key, def] of Object.entries(settings.settings)) {
  const problems = [];
  for (const field of ['type', 'default', 'group', 'title', 'description', 'ui', 'sanitize'])
    if (!(field in def)) problems.push(`missing ${field}`);
  if (!groups.has(def.group)) problems.push(`unknown group ${def.group}`);
  if (!sanitizers.has(def.sanitize)) problems.push(`unknown sanitizer ${def.sanitize}`);
  if (typeOk[def.type] && !typeOk[def.type](def.default)) problems.push('default does not match type');
  if (def.enum && !def.enum.includes(def.default)) problems.push('default not in enum');
  if (def.minimum !== undefined && def.default < def.minimum) problems.push('default below minimum');
  if (def.maximum !== undefined && def.default > def.maximum) problems.push('default above maximum');
  if (def.type === 'string' && def.sanitize === 'enum' && !def.enum) problems.push('enum sanitizer without enum');
  if (problems.length) {
    settingProblems += 1;
    report(`setting ${key}`, false, problems.join('; '));
  }
}
report(`settings descriptor (${Object.keys(settings.settings).length} keys) is consistent`, settingProblems === 0);

// 4. the manifest map of every setting must point at a property that exists in the manifest schema
/** @param {string} path dotted manifest path */
function schemaHas(path) {
  let node = manifestSchema;
  for (const part of path.split('.')) {
    const next =
      node.properties?.[part] ??
      (node.additionalProperties && typeof node.additionalProperties === 'object'
        ? node.additionalProperties
        : undefined);
    if (!next) return false;
    node = next;
  }
  return true;
}
for (const [key, def] of Object.entries(settings.settings)) {
  if (def.manifest && !schemaHas(def.manifest))
    report(`setting ${key} manifest path ${def.manifest}`, false, 'not in manifest schema');
}

// 5. Resume company schema: the example is valid, every invalid fixture is rejected
const companySchema = read(fromRoot('plugins/jooservices-resume/schema/company.schema.json'));
const validateCompany = ajv.compile(companySchema);
const companyDir = fromRoot('plugins/jooservices-resume/schema');
const companyExample = read(join(companyDir, 'examples/company.json'));
report(
  'valid   plugins/jooservices-resume/schema/examples/company.json',
  validateCompany(companyExample),
  ajv.errorsText(validateCompany.errors)
);
for (const f of readdirSync(join(companyDir, 'invalid'))) {
  report(`invalid company ${f} is rejected`, !validateCompany(read(join(companyDir, 'invalid', f))));
}

console.log(failures ? `\n${failures} failure(s)` : '\nall schema checks passed');
process.exit(failures ? 1 : 0);
