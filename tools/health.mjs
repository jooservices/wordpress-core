// Verifies a running demo: status, content type and key markers for each view in tests/matrix/urls.json.
// Usage: node tools/health.mjs [--base http://127.0.0.1:8082] [--values '{"draft-id":1}'] (`make health` fills the values from WP-CLI)
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const TYPES = { xml: /xml/, rss: /rss\+xml|xml/, json: /json/ };

/** Runs one check against fetched data. Returns a list of problems (empty when it passes). */
export function evaluate(check, response, { seoActive = false } = {}) {
  const problems = [];
  if (response.status !== check.status) problems.push(`status ${response.status}, expected ${check.status}`);
  if (check.type) {
    const pattern = TYPES[check.type] ?? new RegExp(check.type.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    if (!pattern.test(response.type)) problems.push(`content-type "${response.type}" does not match ${check.type}`);
  }
  for (const needle of [...(check.contains ?? []), ...(seoActive ? [] : (check.seoContains ?? []))])
    if (!response.body.includes(needle)) problems.push(`missing "${needle}"`);
  for (const needle of check.notContains ?? [])
    if (response.body.includes(needle)) problems.push(`unexpected "${needle}"`);
  return problems;
}

async function get(base, path, attempts = 3) {
  for (let attempt = 1; ; attempt += 1) {
    try {
      const res = await fetch(new URL(path, base), { redirect: 'manual' });
      const body = await res.text();
      return { status: res.status, type: res.headers.get('content-type') ?? '', body, headers: res.headers };
    } catch (error) {
      // A freshly activated plugin can restart workers: retry a dropped connection before reporting it.
      if (attempt >= attempts) throw error;
      await new Promise((done) => setTimeout(done, 1000 * attempt));
    }
  }
}

/** Looks up dynamic placeholders through the REST-free route: the home page and WP-CLI-independent public data. */
async function resolve(base) {
  const values = {};
  const home = await get(base, '/').catch(() => ({ body: '' }));
  const link =
    home.body.match(/href="(https?:\/\/[^"]+\/[a-z0-9-]+\/[a-z0-9-]+\/)"/g)?.map((h) => h.slice(6, -1)) ?? [];
  const own = link.filter(
    (u) => u.startsWith(base.replace(/\/$/, '')) && !/\/(category|tag|author|page|feed|wp-[a-z]+)\//.test(u)
  );
  values['first-post'] = own[0] ? new URL(own[0]).pathname : '/';
  return values;
}

export async function run({ base, table, extra = {}, seoActive = false }) {
  const values = { ...(await resolve(base)), ...extra };
  const results = [];
  for (const check of table.checks) {
    const path = check.path.replace(/\{([a-z-]+)\}/g, (_, k) => values[k] ?? '');
    if (path === '' || /\{/.test(path)) {
      results.push({ name: check.name, ok: false, problems: [`unresolved placeholder in ${check.path}`] });
      continue;
    }
    try {
      let problems = [];
      let used = path;
      for (const candidate of [path, ...(check.altPaths ?? [])]) {
        used = candidate;
        problems = evaluate(check, await get(base, candidate), { seoActive });
        if (problems.length === 0) break;
      }
      results.push({ name: check.name, path: used, ok: problems.length === 0, problems });
    } catch (error) {
      results.push({ name: check.name, path, ok: false, problems: [String(error.message)] });
    }
  }
  return results;
}

export function renderResults(results, json = false) {
  if (json) return JSON.stringify(results);
  const rows = results.map(
    (r) =>
      `${r.ok ? 'ok  ' : 'FAIL'} ${r.name}${r.path ? ` (${r.path})` : ''}${r.ok ? '' : ` — ${r.problems.join('; ')}`}`
  );
  const passed = results.filter((r) => r.ok).length;
  return [...rows, `${passed}/${results.length} checks passed`].join('\n');
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = process.argv.slice(2);
  const flag = (n, d) => (args.includes(n) ? args[args.indexOf(n) + 1] : d);
  const json = args.includes('--json');
  const base = flag('--base', process.env.E2E_BASE_URL ?? process.env.WP_SITE_URL ?? 'http://127.0.0.1:8082');
  const table = JSON.parse(readFileSync(fileURLToPath(new URL('../tests/matrix/urls.json', import.meta.url)), 'utf8'));
  const extra = JSON.parse(flag('--values', '{}'));
  const results = await run({ base, table, extra, seoActive: args.includes('--seo-active') });
  console.log(renderResults(results, json));
  const failed = results.filter((r) => !r.ok);
  process.exit(failed.length === 0 ? 0 : 1);
}
