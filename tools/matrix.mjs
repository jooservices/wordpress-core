// Plugin compatibility matrix: install each Tier-1 plugin, run the health table and the browser smoke, then restore.
// Usage: node tools/matrix.mjs [--theme <label, default: the active theme>] [--only yoast,cf7] [--base http://127.0.0.1:8080] [--no-browser]
import { execFileSync, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { run as runHealth } from './health.mjs';

const OFFLINE = /could not resolve|connection refused|timed out|curl error|unable to connect|download failed|no route/i;

/** Classifies a plugin entry before any work: returns a skip reason or null. */
export function skipReason(entry, { hasZip }) {
  if (entry.source === 'manual' && !hasZip)
    return 'licensed plugin: zip not present in tests/matrix/vendor/ (not tested)';
  if (!entry.slug && entry.source !== 'manual') return 'no slug';
  return null;
}

/** Counts PHP problems in a log excerpt. */
export function phpProblems(log) {
  return log.split('\n').filter((line) => /PHP (Fatal error|Warning|Notice|Deprecated|Parse error)/.test(line));
}

export function renderMarkdown(report) {
  const rows = report.results.map(
    (r) =>
      `| ${r.key} | ${r.name} | ${r.version ?? '-'} | ${r.result} | ${(r.notes ?? []).join('; ').replace(/\|/g, '/')} |`
  );
  return [
    `# Plugin matrix: ${report.theme} (${report.date})`,
    '',
    `Tested ${report.counts.pass} pass, ${report.counts.fail} fail, ${report.counts.skipped} skipped.`,
    '',
    '| Key | Plugin | Version | Result | Notes |',
    '| --- | --- | --- | --- | --- |',
    ...rows,
    '',
  ].join('\n');
}

const compose = (...args) => spawnSync('./docker/scripts/compose.sh', args, { encoding: 'utf8', input: '' });
const wp = (...args) => compose('exec', '-T', '-u', 'www-data', 'wordpress', 'wp', ...args);
const sh = (script) => compose('exec', '-T', '-u', 'www-data', 'wordpress', 'bash', '-c', script);

async function testPlugin(entry, ctx) {
  const notes = [];
  const out = { key: entry.key, name: entry.name, group: entry.group, result: 'PASS', notes };
  const snapshot = `/workspace/artifacts/matrix/${entry.key}.sql`;
  sh(`mkdir -p /workspace/artifacts/matrix`);
  const dump = wp('db', 'export', snapshot, '--porcelain');
  if (dump.status !== 0)
    return { ...out, result: 'FAIL', notes: ['could not snapshot the database: ' + dump.stderr.trim()] };
  try {
    const manualZip = `/workspace/tests/matrix/vendor/${entry.key}.zip`;
    const install =
      entry.source === 'manual'
        ? wp('plugin', 'install', manualZip, '--activate')
        : wp(
            'plugin',
            'install',
            entry.slug,
            ...(entry.version && entry.version !== 'latest' ? [`--version=${entry.version}`] : []),
            '--activate'
          );
    if (install.status !== 0) {
      const text = `${install.stderr}${install.stdout}`;
      return {
        ...out,
        result: OFFLINE.test(text) ? 'SKIPPED (offline)' : 'FAIL',
        notes: [text.trim().split('\n').pop()],
      };
    }
    out.version = wp('plugin', 'get', entry.slug || entry.key, '--field=version').stdout.trim() || undefined;
    for (const command of entry.setup ?? []) wp(...command.split(' '));
    wp('rewrite', 'flush', '--hard');
    wp('cache', 'flush');

    const before = Number(sh('stat -c %s /var/log/php/wp-debug.log 2>/dev/null || echo 0').stdout.trim() || 0);
    const health = await runHealth({
      base: ctx.base,
      table: ctx.table,
      extra: ctx.values,
      seoActive: entry.group === 'SEO',
    });
    for (const r of health.filter((h) => !h.ok)) notes.push(`health ${r.name}: ${r.problems.join(', ')}`);
    const log = sh(`tail -c +$((${before} + 1)) /var/log/php/wp-debug.log 2>/dev/null || true`).stdout;
    const problems = phpProblems(log);
    if (problems.length) notes.push(`php log: ${problems.length} problem(s), first: ${problems[0].slice(0, 160)}`);

    let browserFailed = false;
    if (ctx.browser) {
      const json = `artifacts/matrix/${entry.key}-playwright.json`;
      const res = spawnSync(
        'npx',
        [
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
            E2E_BASE_URL: ctx.base,
            MATRIX_PLUGIN: entry.key,
            MATRIX_PATHS: JSON.stringify(ctx.paths),
            PLAYWRIGHT_JSON_OUTPUT_NAME: `${process.cwd()}/${json}`,
          },
        }
      );
      browserFailed = res.status !== 0;
      if (browserFailed) {
        try {
          const failed = [];
          const walk = (suite) => {
            for (const spec of suite.specs ?? [])
              if (!spec.ok)
                failed.push(`${spec.title}: ${(spec.tests?.[0]?.results?.[0]?.error?.message ?? '').split('\n')[0]}`);
            for (const child of suite.suites ?? []) walk(child);
          };
          for (const suite of JSON.parse(readFileSync(json, 'utf8')).suites ?? []) walk(suite);
          notes.push(...failed.map((f) => `browser ${f}`));
        } catch {
          notes.push('browser: failed (no report)');
        }
      }
    }
    if (health.some((h) => !h.ok) || problems.length || browserFailed) out.result = 'FAIL';
    return out;
  } finally {
    wp('plugin', 'deactivate', entry.slug || entry.key);
    wp('plugin', 'delete', entry.slug || entry.key);
    wp('db', 'import', snapshot);
    wp('rewrite', 'flush', '--hard');
    sh(`rm -f ${snapshot}`);
  }
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = process.argv.slice(2);
  const flag = (n, d) => (args.includes(n) ? args[args.indexOf(n) + 1] : d);
  const base = flag('--base', process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8080');
  const theme = flag('--theme', wp('theme', 'list', '--status=active', '--field=name').stdout.trim() || 'unknown');
  const only = flag('--only', '').split(',').filter(Boolean);
  const matrix = JSON.parse(readFileSync('tests/matrix/plugins.json', 'utf8'));
  const table = JSON.parse(readFileSync('tests/matrix/urls.json', 'utf8'));
  const values = JSON.parse(
    execFileSync(
      './docker/scripts/compose.sh',
      ['exec', '-T', '-u', 'www-data', 'wordpress', 'wp', 'eval-file', '/scripts/health-values.php'],
      { encoding: 'utf8', input: '' }
    )
  );
  const paths = { single: values['first-post'], notfound404: undefined };
  delete paths.notfound404;
  const ctx = { base, table, values, paths, browser: !args.includes('--no-browser') };
  const results = [];
  for (const entry of matrix.plugins.filter((p) => (only.length ? only.includes(p.key) : p.tier === 1))) {
    const skip = skipReason(entry, { hasZip: existsSync(`tests/matrix/vendor/${entry.key}.zip`) });
    const result = skip
      ? { key: entry.key, name: entry.name, group: entry.group, result: 'SKIPPED', notes: [skip] }
      : await testPlugin(entry, ctx);
    console.log(`${result.result.padEnd(18)} ${entry.key}${result.notes.length ? ` — ${result.notes[0]}` : ''}`);
    results.push(result);
  }
  const counts = {
    pass: results.filter((r) => r.result === 'PASS').length,
    fail: results.filter((r) => r.result === 'FAIL').length,
    skipped: results.filter((r) => r.result.startsWith('SKIPPED')).length,
  };
  const date = new Date().toISOString().slice(0, 10);
  const report = { theme, date, counts, results };
  mkdirSync('artifacts/reports/matrix', { recursive: true });
  writeFileSync(`artifacts/reports/matrix/${theme}-${date}.json`, JSON.stringify(report, null, 2));
  writeFileSync(`artifacts/reports/matrix/${theme}-${date}.md`, renderMarkdown(report));
  console.log(`matrix: ${counts.pass} pass, ${counts.fail} fail, ${counts.skipped} skipped`);
  process.exit(counts.fail === 0 ? 0 : 1);
}
