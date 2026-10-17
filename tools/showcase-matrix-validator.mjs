import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const REQUIRED_PROFILES = ['ltr', 'rtl'];
const REQUIRED_SCHEMES = ['dark', 'light', 'ocean', 'forest', 'paper'];
const REQUIRED_BROWSERS = ['Chromium', 'WebKit', 'Firefox'];
const REQUIRED_BROWSER_TARGETS = [
  'Chrome desktop: current and previous stable',
  'Edge desktop: current and previous stable',
  'Safari desktop: 16.4 or newer',
  'iOS Safari: 16.4 or newer on a real device',
  'Firefox: ESR and current stable',
  'Android Chrome: current stable on a real device',
];
const REQUIRED_VIEWPORTS = [
  { id: 'phone', width: 390, height: 844 },
  { id: 'tablet', width: 820, height: 1180 },
  { id: 'desktop', width: 1440, height: 900 },
];

/**
 * @typedef {{ id: string; width: number; height: number }} Viewport
 * @typedef {{ browsers: string[]; viewports: Viewport[]; schemes: string[]; languages: string[]; overflowSweepWidths?: number[]; browserTargets?: string[] }} VerificationProfile
 * @typedef {{ id: string; file: string; status: 'planned'|'implemented'; trackingTicket?: string }} AutomatedTest
 * @typedef {{ id: string; viewOrComponent: string; contentKey: string; contentRequired: string[]; expectedUrl: string; expectedStatus: number; specIds: string[]; automatedTest: AutomatedTest; existingCoverage?: { file: string; status: string }[]; manualCheckOwner: string; manualCheckStatus: string; checkedBy?: string; verificationProfiles: string[] }} MatrixRow
 * @typedef {{ schemaVersion: number; specificationSource: string; specificationIds: string[]; verificationProfiles: Record<string, VerificationProfile>; rows: MatrixRow[] }} ShowcaseMatrix
 * @typedef {{ key: string; status: string; url: string; httpStatus: number }} GeneratedEntry
 * @typedef {{ schemaVersion: number; entries: GeneratedEntry[] }} GeneratedInventory
 */

/**
 * @param {string} markdown
 * @returns {string[]}
 */
export function readSpecificationIds(markdown) {
  return [...markdown.matchAll(/^\s*-\s*\[[ xX]\]\s*([A-Z0-9]+-\d+)\b/gm)].map((match) => match[1]);
}

/**
 * Validate the static matrix contract and, when supplied, file references.
 * @param {ShowcaseMatrix} matrix
 * @param {{ expectedSpecIds?: string[]; rootDir?: string }} [options]
 * @returns {string[]}
 */
export function validateMatrix(matrix, options = {}) {
  const errors = [];
  const fail = (where, message) => errors.push(`${where}: ${message}`);
  if (!matrix || typeof matrix !== 'object' || Array.isArray(matrix)) return ['matrix: expected a JSON object'];
  if (matrix.schemaVersion !== 1) fail('schemaVersion', 'expected version 1');
  if (typeof matrix.specificationSource !== 'string' || !matrix.specificationSource.trim()) {
    fail('specificationSource', 'must name the source checklist');
  }
  if (!Array.isArray(matrix.specificationIds) || matrix.specificationIds.length === 0) {
    fail('specificationIds', 'must be a non-empty array');
  }
  if (!Array.isArray(matrix.rows) || matrix.rows.length === 0) return [...errors, 'rows: must be a non-empty array'];
  if (!matrix.verificationProfiles || typeof matrix.verificationProfiles !== 'object') {
    fail('verificationProfiles', 'must be an object');
  }

  const declaredSpecs = Array.isArray(matrix.specificationIds) ? matrix.specificationIds : [];
  const declaredSpecSet = new Set(declaredSpecs);
  if (declaredSpecSet.size !== declaredSpecs.length) fail('specificationIds', 'contains duplicate ids');
  if (options.expectedSpecIds) {
    const expected = new Set(options.expectedSpecIds);
    for (const id of expected) if (!declaredSpecSet.has(id)) fail('specificationIds', `missing source id ${id}`);
    for (const id of declaredSpecSet)
      if (!expected.has(id)) fail('specificationIds', `unknown or stale source id ${id}`);
  }

  for (const [name, profile] of Object.entries(matrix.verificationProfiles ?? {})) {
    const at = `verificationProfiles.${name}`;
    if (!profile || typeof profile !== 'object' || Array.isArray(profile)) {
      fail(at, 'must be an object');
      continue;
    }
    for (const [dimension, values] of [
      ['browsers', profile.browsers],
      ['viewports', profile.viewports],
      ['schemes', profile.schemes],
      ['languages', profile.languages],
    ]) {
      if (!Array.isArray(values) || values.length === 0) fail(`${at}.${dimension}`, 'must be a non-empty array');
    }
    for (const browser of REQUIRED_BROWSERS) {
      if (!profile.browsers?.includes(browser)) fail(`${at}.browsers`, `missing ${browser}`);
    }
    for (const expected of REQUIRED_VIEWPORTS) {
      if (
        !profile.viewports?.some(
          (item) => item.id === expected.id && item.width === expected.width && item.height === expected.height
        )
      ) {
        fail(`${at}.viewports`, `missing ${expected.id} ${expected.width}x${expected.height}`);
      }
    }
    for (const scheme of REQUIRED_SCHEMES) {
      if (!profile.schemes?.includes(scheme)) fail(`${at}.schemes`, `missing ${scheme}`);
    }
    for (const target of REQUIRED_BROWSER_TARGETS) {
      if (!profile.browserTargets?.includes(target)) fail(`${at}.browserTargets`, `missing ${target}`);
    }
    if (name === 'ltr') {
      for (const language of ['en', 'vi'])
        if (!profile.languages?.includes(language)) fail(`${at}.languages`, `missing ${language}`);
    }
    if (name === 'rtl') {
      for (const language of ['ar', 'he_IL'])
        if (!profile.languages?.includes(language)) fail(`${at}.languages`, `missing ${language}`);
    }
    if (name === 'cjk' && !profile.languages?.includes('zh_CN')) fail(`${at}.languages`, 'missing zh_CN');
    if (!Array.isArray(profile.overflowSweepWidths) || profile.overflowSweepWidths.length < 12) {
      fail(`${at}.overflowSweepWidths`, 'must include the 12-width responsive sweep');
    } else if (!profile.overflowSweepWidths.includes(320) || !profile.overflowSweepWidths.includes(3840)) {
      fail(`${at}.overflowSweepWidths`, 'must span 320px through 3840px');
    }
  }
  for (const name of ['ltr', 'rtl']) {
    if (!matrix.verificationProfiles?.[name]) fail('verificationProfiles', `missing required ${name} profile`);
  }

  const rowIds = new Set();
  const contentKeys = new Set();
  const mappedSpecs = new Set();
  for (const [index, row] of matrix.rows.entries()) {
    const at = `rows[${index}]${row?.id ? ` (${row.id})` : ''}`;
    if (!row || typeof row !== 'object' || Array.isArray(row)) {
      fail(at, 'must be an object');
      continue;
    }
    for (const field of [
      'id',
      'viewOrComponent',
      'contentKey',
      'expectedUrl',
      'manualCheckOwner',
      'manualCheckStatus',
      'checkedBy',
    ]) {
      if (typeof row[field] !== 'string' || !row[field].trim()) fail(`${at}.${field}`, 'must be a non-empty string');
    }
    if (rowIds.has(row.id)) fail(`${at}.id`, 'is duplicated');
    rowIds.add(row.id);
    if (contentKeys.has(row.contentKey)) fail(`${at}.contentKey`, 'is duplicated');
    contentKeys.add(row.contentKey);
    if (
      !Array.isArray(row.contentRequired) ||
      row.contentRequired.length === 0 ||
      row.contentRequired.some((item) => typeof item !== 'string' || !item.trim())
    ) {
      fail(`${at}.contentRequired`, 'must contain at least one non-empty content requirement');
    }
    if (
      typeof row.expectedUrl !== 'string' ||
      !row.expectedUrl.startsWith('/') ||
      row.expectedUrl.startsWith('//') ||
      /\s/.test(row.expectedUrl)
    ) {
      fail(`${at}.expectedUrl`, 'must be a local absolute path without spaces');
    }
    if (!Number.isInteger(row.expectedStatus) || row.expectedStatus < 100 || row.expectedStatus > 599) {
      fail(`${at}.expectedStatus`, 'must be an HTTP status code');
    }
    if (!Array.isArray(row.specIds) || row.specIds.length === 0)
      fail(`${at}.specIds`, 'must map to at least one specification id');
    for (const id of row.specIds ?? []) {
      if (!declaredSpecSet.has(id)) fail(`${at}.specIds`, `unknown specification id ${id}`);
      mappedSpecs.add(id);
    }
    if (!row.automatedTest || typeof row.automatedTest.id !== 'string' || !row.automatedTest.id.trim()) {
      fail(`${at}.automatedTest`, 'must name the automated test id');
    } else {
      if (typeof row.automatedTest.file !== 'string' || !row.automatedTest.file.trim())
        fail(`${at}.automatedTest.file`, 'must name a test file');
      if (!['planned', 'implemented'].includes(row.automatedTest.status))
        fail(`${at}.automatedTest.status`, 'must be planned or implemented');
      if (row.automatedTest.status === 'planned' && !row.automatedTest.trackingTicket) {
        fail(`${at}.automatedTest.trackingTicket`, 'planned tests must name their delivery ticket');
      }
      if (row.automatedTest.status === 'implemented' && options.rootDir) {
        const file = resolve(options.rootDir, row.automatedTest.file ?? '');
        if (!file.startsWith(`${resolve(options.rootDir)}/`) || !readableFile(file))
          fail(`${at}.automatedTest.file`, 'implemented test file does not exist inside the repository');
      }
    }
    if (
      row.manualCheckStatus !== 'pending' &&
      row.manualCheckStatus !== 'passed' &&
      row.manualCheckStatus !== 'failed'
    ) {
      fail(`${at}.manualCheckStatus`, 'must be pending, passed, or failed');
    }
    if (!Array.isArray(row.verificationProfiles) || row.verificationProfiles.length === 0) {
      fail(`${at}.verificationProfiles`, 'must name at least one profile');
    }
    for (const name of REQUIRED_PROFILES) {
      if (!row.verificationProfiles?.includes(name)) fail(`${at}.verificationProfiles`, `must include ${name}`);
    }
    for (const name of row.verificationProfiles ?? []) {
      if (!matrix.verificationProfiles?.[name]) fail(`${at}.verificationProfiles`, `unknown profile ${name}`);
    }
    if (row.id === 'POST-CHINESE' && !row.verificationProfiles?.includes('cjk')) {
      fail(`${at}.verificationProfiles`, 'Chinese sample must include the zh_CN profile');
    }
    if (!Array.isArray(row.existingCoverage)) fail(`${at}.existingCoverage`, 'must be an array, possibly empty');
    for (const [coverageIndex, coverage] of (row.existingCoverage ?? []).entries()) {
      const coverageAt = `${at}.existingCoverage[${coverageIndex}]`;
      if (!coverage || typeof coverage.file !== 'string' || !coverage.file.trim())
        fail(`${coverageAt}.file`, 'must name an existing baseline test');
      if (coverage?.status !== 'partial-baseline') fail(`${coverageAt}.status`, 'must be labelled partial-baseline');
      if (coverage?.file && options.rootDir && !readableFile(resolve(options.rootDir, coverage.file))) {
        fail(`${coverageAt}.file`, 'baseline test file does not exist');
      }
    }
  }
  for (const id of declaredSpecs) if (!mappedSpecs.has(id)) fail('specification coverage', `no matrix row maps ${id}`);
  return errors;
}

/**
 * Keep the human-readable table synchronized with the machine-readable contract.
 * @param {ShowcaseMatrix} matrix
 * @param {string} markdown
 * @returns {string[]}
 */
export function validateDocumentationMatrix(matrix, markdown) {
  const errors = [];
  const lines = markdown.split(/\r?\n/).filter((line) => /^\|\s*`[A-Z0-9-]+`\s*\|/.test(line));
  const documented = new Map();
  for (const [index, line] of lines.entries()) {
    const cells = line
      .slice(1, -1)
      .split('|')
      .map((cell) => cell.trim());
    const id = cells[0]?.replaceAll('`', '');
    cells[0] = id;
    if (documented.has(id)) errors.push(`documentation row ${index + 1}: duplicate id ${id}`);
    documented.set(id, cells);
  }
  for (const row of matrix.rows ?? []) {
    const cells = documented.get(row.id);
    if (!cells) {
      errors.push(`documentation: missing row ${row.id}`);
      continue;
    }
    const expected = [
      row.id,
      row.viewOrComponent,
      row.contentRequired.join('<br>'),
      `\`${row.expectedUrl}\` → HTTP ${row.expectedStatus}`,
      row.specIds.map((id) => `\`${id}\``).join(', '),
      `\`${row.automatedTest.id}\` (${row.automatedTest.status}, ${row.automatedTest.trackingTicket})`,
      `${row.manualCheckOwner} (${row.manualCheckStatus})`,
      row.verificationProfiles.map((profile) => `\`${profile}\``).join(', '),
    ];
    for (const [column, value] of expected.entries()) {
      if (cells[column] !== value)
        errors.push(`documentation.${row.id}: column ${column + 1} differs from matrix.json`);
    }
  }
  for (const id of documented.keys()) {
    if (!matrix.rows?.some((row) => row.id === id)) errors.push(`documentation: unknown row ${id}`);
  }
  return errors;
}

/**
 * Check a generated showcase inventory against every row. The generator writes one entry per contentKey,
 * including expected route status for intentionally unpublished content and error views.
 * @param {ShowcaseMatrix} matrix
 * @param {GeneratedInventory} inventory
 * @returns {string[]}
 */
export function validateGeneratedInventory(matrix, inventory) {
  const errors = [];
  if (!inventory || inventory.schemaVersion !== 1 || !Array.isArray(inventory.entries)) {
    return ['inventory: expected schemaVersion 1 and an entries array'];
  }
  const indexed = new Map();
  for (const [index, entry] of inventory.entries.entries()) {
    if (!entry || typeof entry.key !== 'string' || !entry.key.trim()) {
      errors.push(`inventory.entries[${index}].key: must be a non-empty string`);
      continue;
    }
    if (indexed.has(entry.key)) errors.push(`inventory.entries[${index}].key: duplicate key ${entry.key}`);
    indexed.set(entry.key, entry);
  }
  for (const row of matrix.rows ?? []) {
    const entry = indexed.get(row.contentKey);
    if (!entry) {
      errors.push(`inventory: missing generated content key ${row.contentKey} (${row.id})`);
      continue;
    }
    if (entry.status !== 'present')
      errors.push(`inventory.${row.contentKey}.status: expected present, got ${entry.status}`);
    if (entry.url !== row.expectedUrl)
      errors.push(`inventory.${row.contentKey}.url: expected ${row.expectedUrl}, got ${entry.url}`);
    if (entry.httpStatus !== row.expectedStatus) {
      errors.push(`inventory.${row.contentKey}.httpStatus: expected ${row.expectedStatus}, got ${entry.httpStatus}`);
    }
  }
  return errors;
}

/** @param {string} file @returns {boolean} */
function readableFile(file) {
  try {
    readFileSync(file);
    return true;
  } catch {
    return false;
  }
}

/** @param {string[]} args @returns {Map<string, string>} */
function parseArgs(args) {
  const values = new Map();
  for (let index = 0; index < args.length; index += 1) {
    const name = args[index];
    if (!['--matrix', '--inventory'].includes(name)) throw new Error(`unknown argument: ${name}`);
    const value = args[index + 1];
    if (!value || value.startsWith('--')) throw new Error(`${name} needs a file path`);
    values.set(name, value);
    index += 1;
  }
  return values;
}

/** @param {string} file @returns {unknown} */
function readJson(file) {
  return JSON.parse(readFileSync(file, 'utf8'));
}

/** @param {string[]} args */
export function run(args) {
  const values = parseArgs(args);
  const matrixFile = resolve(root, values.get('--matrix') ?? 'tests/showcase/matrix.json');
  const matrix = /** @type {ShowcaseMatrix} */ (readJson(matrixFile));
  const specFile = resolve(root, matrix.specificationSource);
  const expectedSpecIds = readSpecificationIds(readFileSync(specFile, 'utf8'));
  const errors = validateMatrix(matrix, { expectedSpecIds, rootDir: root });
  errors.push(
    ...validateDocumentationMatrix(matrix, readFileSync(resolve(root, 'docs/spec/showcase-matrix.md'), 'utf8'))
  );
  if (values.has('--inventory')) {
    const inventoryFile = resolve(root, values.get('--inventory'));
    errors.push(...validateGeneratedInventory(matrix, /** @type {GeneratedInventory} */ (readJson(inventoryFile))));
  }
  if (errors.length) {
    process.stderr.write(`${errors.map((error) => `- ${error}`).join('\n')}\n`);
    process.exitCode = 1;
    return;
  }
  process.stdout.write(
    `Showcase matrix valid: ${matrix.rows.length} rows, ${expectedSpecIds.length} specification ids.\n`
  );
  if (!values.has('--inventory')) {
    process.stdout.write(
      'Generated-site content was not checked; provide --inventory after JOOS-93 generates the showcase.\n'
    );
  } else {
    process.stdout.write('Generated-site inventory covers every matrix content key and expected URL/status.\n');
  }
}
