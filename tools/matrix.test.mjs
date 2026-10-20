// @vitest-environment node
import { describe, expect, it } from 'vitest';
import {
  browserSmoke,
  phpProblems,
  prepareReportDirectory,
  renderMarkdown,
  resultCounts,
  runHealthViaWeb,
  runMatrix,
  runtimeLabel,
  skipReason,
  woocommerceEarlyTextdomainNotices,
} from './matrix.mjs';

describe('matrix helpers', () => {
  it('skips licensed plugins without a zip and records why', () => {
    expect(skipReason({ source: 'manual', slug: '' }, { hasZip: false })).toMatch(/not tested/);
    expect(skipReason({ source: 'manual', slug: '' }, { hasZip: true })).toBeNull();
    expect(skipReason({ source: 'wporg', slug: 'x' }, { hasZip: false })).toBeNull();
    expect(skipReason({ source: 'wporg', slug: '' }, { hasZip: false })).toBe('no slug');
  });
  it('finds PHP problems in a log excerpt', () => {
    const log = '[a] PHP Notice:  x\n[b] harmless\n[c] PHP Fatal error:  y';
    expect(phpProblems(log)).toHaveLength(2);
  });
  it('makes E2E reports writable to the shared browser runtime with sticky permissions', () => {
    const calls = [];
    prepareReportDirectory('artifacts/reports/e2e', {
      makeDirectory: (...args) => {
        calls.push(['mkdir', ...args]);
        return 'artifacts/reports/e2e';
      },
      setMode: (...args) => {
        calls.push(['chmod', ...args]);
      },
    });
    expect(calls).toEqual([
      ['mkdir', 'artifacts/reports/e2e', { recursive: true }],
      ['chmod', 'artifacts/reports/e2e', 0o1777],
    ]);
  });
  it('allows only the WooCommerce early text-domain notice in its own matrix row', () => {
    const notice =
      '[a] PHP Notice:  Function _load_textdomain_just_in_time was called <strong>incorrectly</strong>. Translation loading for the <code>woocommerce</code> domain was triggered too early.';
    expect(woocommerceEarlyTextdomainNotices(notice)).toHaveLength(1);
    expect(phpProblems(notice)).toHaveLength(1);
    expect(phpProblems(notice, { plugin: 'woocommerce' })).toEqual([]);
    expect(phpProblems(`${notice}\n[b] PHP Warning:  unrelated`, { plugin: 'woocommerce' })).toHaveLength(1);
    expect(phpProblems(notice, { plugin: 'yoast' })).toHaveLength(1);
  });
  it('counts passing, failing, skipped, unsupported and blocked cells', () => {
    expect(
      resultCounts([
        { result: 'PASS' },
        { result: 'FAIL' },
        { result: 'SKIPPED (offline)' },
        { result: 'UNSUPPORTED' },
        { result: 'BLOCKED' },
      ])
    ).toEqual({ pass: 1, fail: 1, skipped: 1, unsupported: 1, blocked: 1 });
  });
  it('uses --group to run only plugins in the requested group', async () => {
    const plugins = [
      { key: 'cf7', name: 'Contact Form 7', group: 'Forms', tier: 1, slug: 'contact-form-7', source: 'wporg' },
      { key: 'wpforms', name: 'WPForms Lite', group: 'Forms', tier: 1, slug: 'wpforms-lite', source: 'wporg' },
      { key: 'fluentform', name: 'Fluent Forms', group: 'Forms', tier: 1, slug: 'fluentform', source: 'wporg' },
      { key: 'yoast', name: 'Yoast SEO', group: 'SEO', tier: 1, slug: 'wordpress-seo', source: 'wporg' },
    ];
    const success = { status: 0, stdout: '', stderr: '' };
    const reports = /** @type {{ results: { key: string }[] }[]} */ ([]);
    const { report: result } = await runMatrix({
      args: ['--group', 'forms', '--no-browser'],
      env: {},
      io: {
        wp: (...args) => {
          if (args[0] === 'theme' && args[1] === 'list') return { ...success, stdout: 'jooservices\n' };
          if (args[0] === 'plugin' && args[1] === 'get') return { ...success, stdout: '1.0.0\n' };
          return success;
        },
        sh: (command) => (command.startsWith('stat -c') ? { ...success, stdout: '0\n' } : success),
        runHealth: async () => [],
        browserSmoke: () => ({ failed: false, notes: [] }),
        readRuntime: () => ({ wordpress: '7.1.3', php: '8.3.30', database: 'MariaDB' }),
        readJson: (path) => (path === 'tests/matrix/plugins.json' ? { plugins } : {}),
        readValues: () => ({ 'first-post': '/hello/' }),
        hasZip: () => false,
        mkdir: () => {},
        write: (path, content) => {
          if (path.endsWith('.json')) reports.push(JSON.parse(content));
        },
        now: () => new Date('2026-10-20T00:00:00.000Z'),
        log: () => {},
      },
    });

    expect(result.results.map((plugin) => plugin.key)).toEqual(['cf7', 'wpforms', 'fluentform']);
    const report = reports[0];
    if (!report) throw new Error('matrix did not write a report');
    expect(report.results.map((plugin) => plugin.key)).toEqual(['cf7', 'wpforms', 'fluentform']);
  });
  it('formats available and missing runtime values', () => {
    expect(runtimeLabel({ wordpress: '7.1.3', php: '8.3.35', database: '12.3.3-MariaDB' })).toBe(
      'WordPress 7.1.3 / PHP 8.3.35 / 12.3.3-MariaDB'
    );
    expect(runtimeLabel({})).toBe('WordPress unknown / PHP unknown / database unknown');
  });
  it('runs matrix health through wp-task on the shared web runtime and parses JSON output', () => {
    const output = [{ name: 'home', path: '/', ok: false, problems: ['status 500'] }];
    /** @type {{ command: string; args: string[]; options: { input?: string } } | null} */
    let invocation = null;
    const spawn = /** @type {typeof import('node:child_process').spawnSync} */ (
      (command, args, options) => {
        invocation = { command, args, options };
        const stdout = JSON.stringify(output);
        return {
          pid: 1,
          output: [null, stdout, ''],
          signal: null,
          status: 1,
          stdout,
          stderr: '',
        };
      }
    );
    const results = runHealthViaWeb(
      {
        theme: 'starter',
        base: 'http://starter.localhost:8082',
        extra: { 'first-post': '/hello/' },
        seoActive: true,
      },
      spawn
    );
    expect(results).toEqual(output);
    if (invocation === null) {
      throw new Error('runHealthViaWeb did not invoke the spawnSync dependency');
    }
    expect(invocation.command).toBe('bash');
    expect(invocation.args).toEqual([
      './docker/scripts/wp-task.sh',
      'matrix-health',
      'starter',
      '--base',
      'http://starter.localhost:8082',
      '--values',
      '{"first-post":"/hello/"}',
      '--json',
      '--seo-active',
    ]);
    expect(invocation.options.input).toBe('');
  });
  it('renders a plugin report with counts and escaped notes', () => {
    const md = renderMarkdown({
      kind: 'plugins',
      theme: 't',
      date: 'd',
      variant: 'wp-7.1.3-php-8.3',
      runtime: { wordpress: '7.1.3', php: '8.3.35', database: '12.3.3-MariaDB' },
      counts: { pass: 1, fail: 0, skipped: 1, unsupported: 1, blocked: 0 },
      results: [
        { key: 'a', name: 'A', result: 'PASS', notes: [] },
        { key: 'b', name: 'B', result: 'SKIPPED', notes: ['why | x'] },
        { key: 'c', name: 'C', result: 'UNSUPPORTED', notes: ['licensed copy unavailable'] },
      ],
    });
    expect(md).toContain('# Plugin compatibility matrix: t (d)');
    expect(md).toContain('Variant: wp-7.1.3-php-8.3. WordPress 7.1.3 / PHP 8.3.35 / 12.3.3-MariaDB.');
    expect(md).toContain('1 pass, 0 fail, 1 skipped, 1 unsupported, 0 blocked');
    expect(md).toContain('why / x');
  });
  it('labels runtime reports separately from plugin reports', () => {
    const md = renderMarkdown({
      kind: 'runtime',
      theme: 'jooservices',
      date: '2026-10-16',
      variant: 'wp-6.6-php-8.1',
      runtime: { wordpress: '6.6.2', php: '8.1.33', database: '12.3.3-MariaDB' },
      counts: { pass: 1, fail: 0, skipped: 0, unsupported: 0, blocked: 0 },
      results: [{ key: 'runtime', name: 'Theme smoke', result: 'PASS', notes: [] }],
    });
    expect(md).toContain('# Runtime compatibility matrix: jooservices');
    expect(md).toContain('1 pass, 0 fail, 0 skipped, 0 unsupported, 0 blocked');
  });

  it('uses the shared Playwright runner and reports stderr when its JSON artifact is missing', () => {
    let invoked = false;
    let reportDirectory = '';
    const result = browserSmoke(
      'runtime',
      'jooservices',
      { browserBase: 'http://wordpress', paths: { single: '/hello/' } },
      {
        prepareReportDirectory: (path) => {
          reportDirectory = path;
        },
        spawn: (command, args, options) => {
          invoked = true;
          expect(command).toBe('bash');
          expect(args).toContain('playwright');
          expect(options.env?.E2E_BASE_URL).toBe('http://wordpress');
          expect(options.env?.PLAYWRIGHT_JSON_OUTPUT_NAME).toMatch(
            /\/artifacts\/reports\/e2e\/runtime-jooservices-playwright\.json$/
          );
          return /** @type {any} */ ({
            pid: 1,
            output: [null, 'runner output', 'first line\nlast line'],
            stdout: 'runner output',
            stderr: 'first line\nlast line',
            status: 1,
            signal: null,
          });
        },
        readReport: () => {
          throw new Error('report was not created');
        },
      }
    );
    expect(invoked).toBe(true);
    expect(reportDirectory).toBe('artifacts/reports/e2e');
    expect(result).toEqual({ failed: true, notes: ['browser failed without a report: last line'] });
  });
});
