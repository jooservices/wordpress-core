// Compatibility matrix: test the active theme with selected plugins and report the exact runtime.
// Usage: make test-matrix THEME=jooservices [WP_VERSION=6.6] [WP_PHP_VERSION=8.3] [ARGS="--child-theme"]
// Add --runtime-only to check the selected runtime without installing plugins.
import { execFileSync, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { run as runHealth } from './health.mjs';
import { browserResult, readRuntime, prepareChildTheme, removeChildTheme, runMatrix } from './matrix-core.mjs';

export * from './matrix-core.mjs';

const compose = (...args) => spawnSync('./docker/scripts/compose.sh', args, { encoding: 'utf8', input: '' });
const wp = (...args) => compose('exec', '-T', '-u', 'www-data', 'wordpress', 'wp', ...args);
const sh = (script) => compose('exec', '-T', '-u', 'www-data', 'wordpress', 'bash', '-c', script);

export function browserSmoke(
  plugin,
  theme,
  ctx,
  { spawn = spawnSync, readReport = (path) => readFileSync(path, 'utf8') } = {}
) {
  const json = `artifacts/matrix/${plugin}-${theme}-playwright.json`;
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
        PLAYWRIGHT_JSON_OUTPUT_NAME: `/workspace/${json}`,
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

const defaultIO = {
  wp,
  sh,
  runHealth,
  browserSmoke,
  readRuntime: () => readRuntime(wp),
  prepareChildTheme: (parent) => prepareChildTheme(parent, wp),
  removeChildTheme: (child) => removeChildTheme(child, wp, console.error),
  readJson: (path) => JSON.parse(readFileSync(path, 'utf8')),
  readValues: () =>
    JSON.parse(
      execFileSync(
        './docker/scripts/compose.sh',
        ['exec', '-T', '-u', 'www-data', 'wordpress', 'wp', 'eval-file', '/scripts/health-values.php'],
        { encoding: 'utf8', input: '' }
      )
    ),
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
