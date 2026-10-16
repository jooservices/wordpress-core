// Checks the naming registry: uniqueness, prefixes, agreement with the settings descriptor and
// (when code exists) that every joo/ hook, meta key, REST route, block and custom element used in source is registered.
// Usage: node tools/check-registry.mjs [--fix-docs]
import { readFileSync, readdirSync, writeFileSync, statSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { fromRoot, ROOT } from './lib/paths.mjs';

const registry = JSON.parse(readFileSync(fromRoot('docs/spec/naming-registry.json'), 'utf8'));
const settings = JSON.parse(readFileSync(fromRoot('packages/theme-core/schema/settings.schema.json'), 'utf8'));
let failures = 0;
const fail = (message) => {
  console.log(`FAIL ${message}`);
  failures += 1;
};

// 1. uniqueness and prefix rules
const prefixRules = {
  options: /^joo_/,
  postMeta: /^_joo_/,
  postMetaInternal: /^_joo_/,
  termMeta: /^joo_/,
  userMeta: /^joo_/,
  postTypes: /^joo_/,
  blocks: /^joo\/[a-z0-9-]+$/,
  customElements: /^joo-[a-z0-9-]+$/,
  hooks: /^joo\/[a-z0-9_]+(\/[a-z0-9_{}]+)+$/,
};
for (const [list, items] of Object.entries(registry)) {
  if (!Array.isArray(items)) continue;
  const seen = new Set();
  for (const item of items) {
    if (seen.has(item)) fail(`${list}: duplicate ${item}`);
    seen.add(item);
    if (prefixRules[list] && !prefixRules[list].test(item)) fail(`${list}: ${item} violates the prefix rule`);
  }
}

// 2. every key of the settings descriptor is unique and uses a known namespace
const namespaces = new Set(['modules', 'core', 'seo', 'lazy', 'perf', 'features', 'content', 'i18n', 'terminal']);
for (const key of Object.keys(settings.settings))
  if (!namespaces.has(key.split('.')[0])) fail(`settings: ${key} has an unknown namespace`);

// 3. source scan
/** @param {string} dir @returns {string[]} files */
function walk(dir) {
  if (!existsSync(dir)) return [];
  const out = [];
  for (const name of readdirSync(dir)) {
    if (['node_modules', 'vendor', 'build', 'core', '.git'].includes(name)) continue;
    const full = join(dir, name);
    if (statSync(full).isDirectory()) out.push(...walk(full));
    else out.push(full);
  }
  return out;
}
const files = [
  ...walk(fromRoot('packages/theme-core/src')),
  ...walk(fromRoot('packages/theme-core/resources')),
  ...walk(fromRoot('plugins/jooservices-resume/src')),
  ...walk(fromRoot('plugins/jooservices-resume')),
  ...walk(fromRoot('themes')),
].filter(
  (f) =>
    /\.(php|js|mjs|json|html)$/.test(f) && !/\.test\.js$/.test(f) && !f.includes('/tests/') && !f.includes('/design/')
);
const hookMatchers = registry.hooks.map((h) => new RegExp('^' + h.replace(/\{[a-z]+\}/g, '[a-z0-9_-]+') + '$'));
const metaAll = new Set([
  ...registry.postMeta,
  ...registry.postMetaInternal,
  ...registry.termMeta,
  ...registry.userMeta,
  ...registry.options,
]);
const blockSet = new Set(registry.blocks);
const elementSet = new Set(registry.customElements);
const unknown = new Set();
const settingKeys = new Set(Object.keys(settings.settings));
for (const file of new Set(files)) {
  const text = readFileSync(file, 'utf8');
  const rel = file.replace(ROOT + '/', '');
  for (const m of text.matchAll(/Hooks::(?:filter|action|deprecated)\(\s*'(joo\/[^']+)'/g))
    if (!hookMatchers.some((r) => r.test(m[1]))) unknown.add(`${rel}: hook ${m[1]}`);
  for (const m of text.matchAll(/'(_joo_[a-z_]+)'/g)) if (!metaAll.has(m[1])) unknown.add(`${rel}: meta ${m[1]}`);
  for (const m of text.matchAll(/register_rest_route\(\s*'joo\/v1',\s*'([^']+)'/g))
    if (!registry.restRoutes.includes(m[1])) unknown.add(`${rel}: REST route ${m[1]}`);
  for (const m of text.matchAll(/customElements\.define\(\s*'(joo-[a-z0-9-]+)'|\bdefine\(\s*'(joo-[a-z0-9-]+)'/g))
    if (!elementSet.has(m[1] ?? m[2])) unknown.add(`${rel}: custom element ${m[1] ?? m[2]}`);
  if (/block\.json$/.test(file)) {
    const name = JSON.parse(text).name;
    if (name && !blockSet.has(name)) unknown.add(`${rel}: block ${name}`);
  }
  for (const m of text.matchAll(/Settings::get\(\s*'([a-z_.]+)'/g))
    if (!settingKeys.has(m[1])) unknown.add(`${rel}: setting ${m[1]}`);
}
for (const item of [...unknown].sort()) fail(`not in registry: ${item}`);

// 4. docs
if (process.argv.includes('--fix-docs')) {
  const lines = [
    '# Naming registry',
    '',
    'Generated from `docs/spec/naming-registry.json` by `node tools/check-registry.mjs --fix-docs`. Do not edit by hand.',
    '',
  ];
  for (const [list, items] of Object.entries(registry)) {
    if (!Array.isArray(items)) continue;
    lines.push(`## ${list}`, '', ...items.map((i) => `- \`${i}\``), '');
  }
  lines.push('## settings keys', '', ...Object.keys(settings.settings).map((k) => `- \`${k}\``), '');
  writeFileSync(fromRoot('docs/reference/naming.md'), lines.join('\n'));
  console.log('wrote docs/reference/naming.md');
}
console.log(
  failures
    ? `\n${failures} problem(s)`
    : `registry ok (${Object.values(registry).flat().length} names, ${settingKeys.size} settings keys, ${new Set(files).size} source files scanned)`
);
process.exit(failures ? 1 : 0);
