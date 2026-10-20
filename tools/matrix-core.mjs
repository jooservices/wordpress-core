const OFFLINE =
  /could not resolve|connection refused|timed out|curl error|unable to connect|download failed|no route|could not establish a secure connection to WordPress\.org/i;
const MATRIX_DIR = 'artifacts/reports/matrix';
const WORDPRESS_ORG_TLS_WARNING = /PHP Warning: .*WordPress could not establish a secure connection to WordPress\.org/;
const WP_CLI_SYMFONY_DEPRECATION = /PHP Deprecated: .* in phar:\/\/.*\/wp-cli\.phar\/vendor\/symfony\/finder\//i;

/** Classifies a plugin entry before any work: returns a skip reason or null. */
export function skipReason(entry, { hasZip }) {
  if (entry.source === 'manual' && !hasZip)
    return 'licensed plugin: zip not present in tests/matrix/vendor/ (not tested)';
  if (!entry.slug && entry.source !== 'manual') return 'no slug';
  return null;
}

function normalizeGroup(group) {
  return group
    .trim()
    .toLowerCase()
    .replace(/[\s_-]+/g, '-');
}

export function selectPlugins(plugins, { only = [], group = '' } = {}) {
  const selectedGroup = normalizeGroup(group);
  const availableGroups = [...new Set(plugins.map((plugin) => plugin.group))];
  const normalizedGroups = new Set(availableGroups.map(normalizeGroup));
  if (selectedGroup && !normalizedGroups.has(selectedGroup))
    throw new Error(`unknown plugin group "${group}". Available groups: ${availableGroups.join(', ')}`);

  return plugins.filter((plugin) => {
    const selectedByKey = only.length ? only.includes(plugin.key) : plugin.tier === 1 || plugin.tier === 3;
    return selectedByKey && (!selectedGroup || normalizeGroup(plugin.group) === selectedGroup);
  });
}

/** Counts PHP problems in a log excerpt. */
export function phpProblems(log) {
  return log
    .split('\n')
    .filter(
      (line) =>
        /PHP (Fatal error|Warning|Notice|Deprecated|Parse error)/.test(line) &&
        !WORDPRESS_ORG_TLS_WARNING.test(line) &&
        !WP_CLI_SYMFONY_DEPRECATION.test(line)
    );
}

export function wordpressOrgWarnings(log) {
  return log.split('\n').filter((line) => WORDPRESS_ORG_TLS_WARNING.test(line));
}

export function wpCliSymfonyDeprecations(log) {
  return log.split('\n').filter((line) => WP_CLI_SYMFONY_DEPRECATION.test(line));
}

/** Formats the runtime facts captured from the selected WordPress container. */
export function runtimeLabel(runtime) {
  return `WordPress ${runtime.wordpress ?? 'unknown'} / PHP ${runtime.php ?? 'unknown'} / ${runtime.database ?? 'database unknown'}`;
}

/** Counts results while keeping licensed and operationally blocked cells visible. */
export function resultCounts(results) {
  return {
    pass: results.filter((r) => r.result === 'PASS').length,
    fail: results.filter((r) => r.result === 'FAIL').length,
    skipped: results.filter((r) => r.result.startsWith('SKIPPED')).length,
    unsupported: results.filter((r) => r.result === 'UNSUPPORTED').length,
    blocked: results.filter((r) => r.result === 'BLOCKED').length,
  };
}

export function renderMarkdown(report) {
  const title = report.kind === 'runtime' ? 'Runtime compatibility matrix' : 'Plugin compatibility matrix';
  const countText = [
    `${report.counts.pass} pass`,
    `${report.counts.fail} fail`,
    `${report.counts.skipped} skipped`,
    `${report.counts.unsupported} unsupported`,
    `${report.counts.blocked} blocked`,
  ].join(', ');
  const rows = report.results.map(
    (r) =>
      `| ${r.key} | ${r.name} | ${r.version ?? '-'} | ${r.result} | ${(r.notes ?? []).join('; ').replace(/\|/g, '/')} |`
  );
  return [
    `# ${title}: ${report.theme} (${report.date})`,
    '',
    `Variant: ${report.variant ?? 'default'}. ${runtimeLabel(report.runtime)}.`,
    `Tested ${countText}.`,
    '',
    '| Key | Plugin or check | Version | Result | Notes |',
    '| --- | --- | --- | --- | --- |',
    ...rows,
    '',
  ].join('\n');
}

export function checked(result, description) {
  if (result.status !== 0) throw new Error(`${description}: ${(result.stderr || result.stdout || '').trim()}`);
  return result.stdout.trim();
}

export function readRuntime(wpCommand) {
  const wordpress = checked(wpCommand('core', 'version'), 'could not read WordPress version');
  const php = checked(wpCommand('eval', 'echo PHP_VERSION;'), 'could not read PHP version');
  const database = checked(
    wpCommand('db', 'query', 'SELECT VERSION();', '--skip-column-names'),
    'could not read database version'
  );
  return { wordpress, php, database };
}

export function prepareChildTheme(parent, wpCommand) {
  const slug = 'jooservices-compat-child';
  const themes = checked(wpCommand('theme', 'list', '--field=name'), 'could not list installed themes')
    .split('\n')
    .filter(Boolean);
  if (themes.includes(slug)) return { slug, created: false };

  checked(
    wpCommand(
      'scaffold',
      'child-theme',
      slug,
      `--parent_theme=${parent}`,
      '--theme_name=JOOservices Compatibility Child'
    ),
    'could not scaffold the compatibility child theme'
  );
  return { slug, created: true };
}

export function removeChildTheme(child, wpCommand, logError = (_message) => {}) {
  if (!child?.created) return;
  const result = wpCommand('theme', 'delete', child.slug, '--force');
  if (result.status !== 0) logError(`warning: could not remove generated child theme ${child.slug}`);
}

/** Converts Playwright's process result and optional JSON output into matrix notes. */
export function browserResult(result, reportText = '') {
  if (result.status === 0) return { failed: false, notes: [] };
  try {
    const failed = [];
    const walk = (suite) => {
      for (const spec of suite.specs ?? [])
        if (!spec.ok)
          failed.push(`${spec.title}: ${(spec.tests?.[0]?.results?.[0]?.error?.message ?? '').split('\n')[0]}`);
      for (const child of suite.suites ?? []) walk(child);
    };
    for (const suite of JSON.parse(reportText).suites ?? []) walk(suite);
    return { failed: true, notes: failed.map((failure) => `browser ${failure}`) };
  } catch {
    return {
      failed: true,
      notes: [`browser failed without a report: ${(result.stderr || result.stdout || '').trim().split('\n').at(-1)}`],
    };
  }
}

/** The checkout as the shared PHP container sees it (the same path as on the host); '/workspace' in unit tests. */
const ROOT_DIR = process.env.JOO_ROOT ?? '/workspace';

async function testPlugin(entry, ctx, io) {
  const { wp, sh, runHealth, browserSmoke } = io;
  const notes = [];
  const out = { key: entry.key, name: entry.name, group: entry.group, result: 'PASS', notes };
  const snapshot = `${ROOT_DIR}/artifacts/matrix/${entry.key}.sql`;
  const directory = sh(`mkdir -p ${ROOT_DIR}/artifacts/matrix`);
  if (directory.status !== 0)
    return { ...out, result: 'FAIL', notes: ['could not create the matrix snapshot directory'] };
  const dump = wp('db', 'export', snapshot, '--porcelain');
  if (dump.status !== 0)
    return { ...out, result: 'FAIL', notes: ['could not snapshot the database: ' + dump.stderr.trim()] };

  const before = Number(sh('stat -c %s wp-content/debug.log 2>/dev/null || echo 0').stdout.trim() || 0);
  const cleanupFailure = (description, result) => {
    if (result.status !== 0) {
      notes.push(`${description}: ${(result.stderr || result.stdout || 'command failed').trim()}`);
      out.result = 'FAIL';
    }
  };
  let installed = false;
  try {
    const manualZip = `${ROOT_DIR}/tests/matrix/vendor/${entry.key}.zip`;
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
      out.result = OFFLINE.test(text) ? 'BLOCKED' : 'FAIL';
      notes.push(text.trim().split('\n').at(-1) || 'plugin installation failed');
      return out;
    }
    installed = true;
    out.version = wp('plugin', 'get', entry.slug || entry.key, '--field=version').stdout.trim() || undefined;
    for (const command of entry.setup ?? []) {
      const setup = wp(...command.split(' '));
      if (setup.status !== 0) notes.push(`setup command failed: ${command}`);
    }
    cleanupFailure('rewrite flush failed', wp('rewrite', 'flush', '--hard'));
    cleanupFailure('cache flush failed', wp('cache', 'flush'));

    for (const theme of ctx.themes) {
      const activate = wp('theme', 'activate', theme);
      if (activate.status !== 0) {
        notes.push(`could not activate ${theme}: ${activate.stderr.trim()}`);
        continue;
      }
      const health = await runHealth({
        base: ctx.base,
        theme,
        table: ctx.table,
        extra: ctx.values,
        seoActive: entry.group === 'SEO',
      });
      for (const result of health.filter((row) => !row.ok))
        notes.push(`${theme} health ${result.name}: ${result.problems.join(', ')}`);

      if (ctx.browser) {
        const browser = browserSmoke(entry.key, theme, ctx);
        notes.push(...browser.notes);
        if (browser.failed) out.result = 'FAIL';
      }
    }

    const log = sh(`tail -c +$((${before} + 1)) wp-content/debug.log 2>/dev/null || true`).stdout;
    const wordpressOrgWarningsInLog = wordpressOrgWarnings(log);
    if (wordpressOrgWarningsInLog.length)
      notes.push(
        `environment warning: WordPress.org update check could not establish TLS (${wordpressOrgWarningsInLog.length} warning(s))`
      );
    const wpCliSymfonyDeprecationsInLog = wpCliSymfonyDeprecations(log);
    if (wpCliSymfonyDeprecationsInLog.length)
      notes.push(
        `environment warning: WP-CLI bundled Symfony Finder emitted PHP Deprecated (${wpCliSymfonyDeprecationsInLog.length} warning(s))`
      );
    const problems = phpProblems(log);
    if (problems.length) notes.push(`php log: ${problems.length} problem(s), first: ${problems[0].slice(0, 160)}`);
    if (entry.tier === 3) {
      notes.push('Basic compatibility only; no custom page-builder or shop design is included.');
      if (entry.key === 'woocommerce') notes.push('Shop templates are unstyled; only non-shop pages are checked.');
    }
    if (notes.some((note) => / health |could not activate|php log:|setup command failed|browser /.test(note)))
      out.result = 'FAIL';
    return out;
  } finally {
    const slug = entry.slug || entry.key;
    if (installed) {
      cleanupFailure('plugin deactivation failed', wp('plugin', 'deactivate', slug));
      cleanupFailure('plugin deletion failed', wp('plugin', 'delete', slug));
    }
    cleanupFailure('database restore failed', wp('db', 'import', snapshot));
    cleanupFailure('rewrite restore failed', wp('rewrite', 'flush', '--hard'));
    cleanupFailure('snapshot cleanup failed', sh(`rm -f ${snapshot}`));
  }
}

async function testRuntime(ctx, io) {
  const { wp, runHealth, browserSmoke } = io;
  const notes = [];
  const activePlugins = checked(
    wp('plugin', 'list', '--status=active', '--field=name'),
    'could not read active plugins'
  );
  const seoActive = /(^|\n)(wordpress-seo|seo-by-rank-math|all-in-one-seo-pack|wp-seopress)(\n|$)/.test(activePlugins);
  for (const theme of ctx.themes) {
    const activate = wp('theme', 'activate', theme);
    if (activate.status !== 0) {
      notes.push(`could not activate ${theme}: ${activate.stderr.trim()}`);
      continue;
    }
    const health = await runHealth({ base: ctx.base, theme, table: ctx.table, extra: ctx.values, seoActive });
    for (const result of health.filter((row) => !row.ok))
      notes.push(`${theme} health ${result.name}: ${result.problems.join(', ')}`);
    if (ctx.browser) {
      const browser = browserSmoke('runtime', theme, ctx);
      notes.push(...browser.notes);
      if (browser.failed && browser.notes.length === 0) notes.push(`browser smoke failed for ${theme}`);
    }
  }
  return {
    key: 'runtime',
    name: 'Theme health and browser smoke',
    result: notes.length ? 'FAIL' : 'PASS',
    notes,
  };
}

/** @param {{ args?: string[], env?: Record<string, string | undefined>, io: any }} options */
export async function runMatrix(options) {
  const { args = [], env = {}, io } = options;
  const flag = (name, fallback) => (args.includes(name) ? args[args.indexOf(name) + 1] : fallback);
  const base = flag('--base', env.WP_SITE_URL ?? 'http://127.0.0.1:8082');
  const browserBase = env.E2E_BASE_URL ?? 'http://127.0.0.1:8082';
  const theme = flag(
    '--theme',
    checked(io.wp('theme', 'list', '--status=active', '--field=name'), 'could not read active theme') || 'unknown'
  );
  const only = flag('--only', '').split(',').filter(Boolean);
  const group = flag('--group', '');
  const hasExplicitSelection = args.includes('--only') || args.includes('--group');
  const runtimeOnly = args.includes('--runtime-only');
  const childRequested = args.includes('--child-theme');
  const matrix = io.readJson('tests/matrix/plugins.json');
  const table = io.readJson('tests/matrix/urls.json');
  const values = io.readValues();
  const runtime = io.readRuntime();
  const variant = env.MATRIX_VARIANT || `wp-${runtime.wordpress}-php-${runtime.php}`;
  const paths = { single: values['first-post'], page: '/about/' };
  const child = childRequested ? io.prepareChildTheme(theme) : null;
  const ctx = {
    base,
    browserBase,
    table,
    values,
    paths,
    themes: child ? [theme, child.slug] : [theme],
    browser: !args.includes('--no-browser'),
  };
  const kind = runtimeOnly ? 'runtime' : 'plugins';
  const results = [];

  try {
    if (runtimeOnly) {
      results.push(await testRuntime(ctx, io));
    } else {
      const selected = selectPlugins(matrix.plugins, { only, group });
      if (hasExplicitSelection && selected.length === 0)
        throw new Error('no plugins matched the specified --only/--group filters');
      for (const entry of selected) {
        const skip = skipReason(entry, { hasZip: io.hasZip(entry.key) });
        const result = skip
          ? {
              key: entry.key,
              name: entry.name,
              group: entry.group,
              result: entry.source === 'manual' ? 'UNSUPPORTED' : 'SKIPPED',
              notes: [skip],
            }
          : await testPlugin(entry, ctx, io);
        io.log(`${result.result.padEnd(18)} ${entry.key}${result.notes.length ? ` — ${result.notes[0]}` : ''}`);
        results.push(result);
      }
    }
  } finally {
    if (child?.created) {
      io.wp('theme', 'activate', theme);
      io.removeChildTheme(child);
    }
  }

  const counts = resultCounts(results);
  const testedAt = io.now().toISOString();
  const date = testedAt.slice(0, 10);
  const report = { kind, theme, variant, date, testedAt, runtime, counts, results };
  io.mkdir(MATRIX_DIR);
  const filename = `${theme}-${variant.replace(/[^a-zA-Z0-9.-]+/g, '-')}-${date}`;
  io.write(`${MATRIX_DIR}/${filename}.json`, JSON.stringify(report, null, 2));
  io.write(`${MATRIX_DIR}/${filename}.md`, renderMarkdown(report));
  io.log(
    `matrix: ${counts.pass} pass, ${counts.fail} fail, ${counts.blocked} blocked, ${counts.unsupported} unsupported, ${counts.skipped} skipped`
  );
  return { report, exitCode: counts.fail === 0 && counts.blocked === 0 ? 0 : 1 };
}
