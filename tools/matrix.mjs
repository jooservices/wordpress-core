// Compatibility matrix: test the active theme with selected plugins and report the exact runtime.
// Usage: make test-matrix THEME=jooservices [WP_VERSION=6.6] [WP_PHP_VERSION=8.3] [ARGS="--child-theme"]
// Add --runtime-only to check the selected runtime without installing plugins.
import { spawnSync } from 'node:child_process';
import { chmodSync, existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { browserResult, readRuntime, prepareChildTheme, removeChildTheme, runMatrix } from './matrix-core.mjs';

export * from './matrix-core.mjs';

// WP-CLI and shell commands run in the shared PHP CLI container, in this worktree's site folder (JOO_SITE_DIR).
const rt = (...args) => spawnSync('bash', ['./docker/scripts/runtime.sh', ...args], { encoding: 'utf8', input: '' });
const wp = (...args) => rt('wp', ...args);
const sh = (script) => rt('sh', script);
const root = process.env.JOO_ROOT ?? '/workspace';
// The checkout as wp-web (where Playwright runs) sees it.
const webRoot = process.env.JOO_WEB_ROOT ?? '/workspace';

export function prepareReportDirectory(path, { makeDirectory = mkdirSync, setMode = chmodSync } = {}) {
  makeDirectory(path, { recursive: true });
  setMode(path, 0o1777);
}

export function browserSmoke(
  plugin,
  theme,
  ctx,
  {
    spawn = spawnSync,
    readReport = (path) => readFileSync(path, 'utf8'),
    prepareReportDirectory: prepare = prepareReportDirectory,
  } = {}
) {
  prepare('artifacts/reports/e2e');
  const json = `artifacts/reports/e2e/${plugin}-${theme}-playwright.json`;
  const result = spawn(
    'bash',
    [
      './docker/scripts/runtime.sh',
      'playwright',
      'npx',
      '--no-install',
      'playwright',
      'test',
      '-c',
      'tests/e2e/playwright.config.ts',
      'specs/matrix',
      '--project=desktop',
      '--reporter=json',
    ],
    {
      encoding: 'utf8',
      env: {
        ...process.env,
        E2E_BASE_URL: ctx.browserBase,
        MATRIX_PLUGIN: `${plugin} (${theme})`,
        MATRIX_PATHS: JSON.stringify(ctx.paths),
        PLAYWRIGHT_JSON_OUTPUT_NAME: `${webRoot}/${json}`,
      },
    }
  );
  let reportText = '';
  if (result.status !== 0) {
    try {
      reportText = readReport(json);
    } catch {
      // Preserve the runner's stderr/stdout fallback if Playwright did not write JSON.
    }
  }
  return browserResult(result, reportText);
}

export function runHealthViaWeb({ theme, base, extra, seoActive }, spawn = spawnSync) {
  const args = [
    './docker/scripts/wp-task.sh',
    'matrix-health',
    theme,
    '--base',
    base,
    '--values',
    JSON.stringify(extra),
    '--json',
  ];
  if (seoActive) args.push('--seo-active');
  const result = spawn('bash', args, { encoding: 'utf8', input: '' });
  if (result.error) throw result.error;
  try {
    return JSON.parse(result.stdout);
  } catch {
    throw new Error(`health probe could not return JSON: ${(result.stderr || result.stdout || '').trim()}`);
  }
}

const defaultIO = {
  wp,
  sh,
  runHealth: runHealthViaWeb,
  browserSmoke,
  readRuntime: () => readRuntime(wp),
  prepareChildTheme: (parent) => prepareChildTheme(parent, wp),
  removeChildTheme: (child) => removeChildTheme(child, wp, console.error),
  readJson: (path) => JSON.parse(readFileSync(path, 'utf8')),
  readValues: () => {
    const result = wp('eval-file', `${root}/docker/scripts/health-values.php`);
    if (result.status !== 0) throw new Error(`could not read the health values: ${result.stderr}`);
    return JSON.parse(result.stdout);
  },
  hasZip: (key) => existsSync(`tests/matrix/vendor/${key}.zip`),
  mkdir: (path) => mkdirSync(path, { recursive: true }),
  write: (path, content) => writeFileSync(path, content),
  now: () => new Date(),
  log: console.log,
};

if (import.meta.url === `file://${process.argv[1]}`) {
  const { exitCode } = await runMatrix({ args: process.argv.slice(2), env: process.env, io: defaultIO });
  process.exit(exitCode);
}
