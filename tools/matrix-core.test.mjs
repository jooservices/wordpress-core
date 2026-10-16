// @vitest-environment node
import { describe, expect, it } from 'vitest';
import {
  browserResult,
  checked,
  phpProblems,
  prepareChildTheme,
  readRuntime,
  removeChildTheme,
  renderMarkdown,
  resultCounts,
  runMatrix,
  runtimeLabel,
  skipReason,
} from './matrix-core.mjs';

const good = (stdout = '') => ({ status: 0, stdout, stderr: '' });
const bad = (stderr = 'command failed') => ({ status: 1, stdout: '', stderr });

function fakeIO({ plugins = [], ...options } = {}) {
  const calls = [];
  const files = new Map();
  const io = {
    calls,
    files,
    wp(...args) {
      calls.push(['wp', ...args]);
      if (args[0] === 'db' && args[1] === 'export' && options.dumpFailure) return bad('dump failed');
      if (options.cleanupFailure === args.join(' ')) return bad('cleanup failed');
      if (args[0] === 'plugin' && args[1] === 'install' && options.throwOnInstall)
        throw new Error('install adapter failed');
      if (args[0] === 'plugin' && args[1] === 'install' && options.installFailure) return options.installFailure;
      if (args[0] === 'plugin' && args[1] === 'get') return good('1.2.3');
      if (args[0] === 'plugin' && args[1] === 'list') return good(options.activePlugins ?? 'wordpress-seo');
      if (args[0] === 'theme' && args[1] === 'activate' && options.activateFailure?.includes(args[2]))
        return bad('theme activation failed');
      if (args[0] === 'theme' && args[1] === 'list') return good(options.installedThemes ?? 'parent-theme');
      if (options.setupFailure && args.join(' ') === options.setupFailure) return bad('setup failed');
      return good();
    },
    sh(script) {
      calls.push(['sh', script]);
      if (script.startsWith('stat ')) return good(options.logOffset ?? '0');
      if (script.startsWith('tail ')) return good(options.phpLog ?? '');
      return good();
    },
    async runHealth(params) {
      calls.push(['health', params]);
      return options.healthRows ?? [];
    },
    browserSmoke(plugin, theme, ctx) {
      calls.push(['browser', plugin, theme, ctx]);
      return typeof options.browser === 'function'
        ? options.browser(plugin, theme)
        : (options.browser ?? { failed: false, notes: [] });
    },
    readJson(path) {
      return path.endsWith('plugins.json') ? { plugins } : [{ path: '/' }];
    },
    readValues: () => ({ 'first-post': '/hello/' }),
    readRuntime: () => ({ wordpress: '7.1.3', php: '8.3.35', database: 'MariaDB 12' }),
    hasZip: (key) => (options.zips ?? []).includes(key),
    prepareChildTheme: () => options.child ?? { slug: 'compat-child', created: false },
    removeChildTheme(child) {
      calls.push(['removeChild', child]);
    },
    mkdir(path) {
      calls.push(['mkdir', path]);
    },
    write(path, content) {
      files.set(path, content);
    },
    now: () => new Date('2026-10-16T12:00:00Z'),
    log: (message) => calls.push(['log', message]),
  };
  return io;
}

describe('matrix core helpers', () => {
  it('classifies plugins, counts outcomes, and formats reports', () => {
    expect(skipReason({ source: 'manual', slug: '' }, { hasZip: false })).toContain('not tested');
    expect(skipReason({ source: 'manual', slug: '' }, { hasZip: true })).toBeNull();
    expect(skipReason({ source: 'wporg', slug: '' }, { hasZip: false })).toBe('no slug');
    expect(skipReason({ source: 'wporg', slug: 'seo' }, { hasZip: false })).toBeNull();
    expect(phpProblems('PHP Warning: bad\nclean\nPHP Deprecated: old')).toHaveLength(2);
    expect(runtimeLabel({})).toBe('WordPress unknown / PHP unknown / database unknown');
    expect(
      resultCounts([
        { result: 'PASS' },
        { result: 'FAIL' },
        { result: 'SKIPPED' },
        { result: 'UNSUPPORTED' },
        { result: 'BLOCKED' },
      ])
    ).toEqual({ pass: 1, fail: 1, skipped: 1, unsupported: 1, blocked: 1 });
    const md = renderMarkdown({
      kind: 'runtime',
      theme: 'theme',
      date: 'today',
      runtime: {},
      counts: { pass: 1, fail: 0, skipped: 0, unsupported: 0, blocked: 0 },
      results: [{ key: 'runtime', name: 'Health', result: 'PASS', notes: ['x | y'] }],
    });
    expect(md).toContain('# Runtime compatibility matrix: theme (today)');
    expect(md).toContain('x / y');
  });

  it('checks commands, reads runtime facts and manages a generated child theme', () => {
    expect(checked(good('value\n'), 'test')).toBe('value');
    expect(() => checked(bad('detail'), 'check')).toThrow('check: detail');
    const args = [];
    const wp = (...command) => {
      args.push(command);
      if (command[0] === 'core') return good('7.1');
      if (command[0] === 'eval') return good('8.3');
      return good('MariaDB');
    };
    expect(readRuntime(wp)).toEqual({ wordpress: '7.1', php: '8.3', database: 'MariaDB' });
    expect(args).toHaveLength(3);
    expect(prepareChildTheme('parent', (...command) => (command[0] === 'theme' ? good('parent\n') : good()))).toEqual({
      slug: 'jooservices-compat-child',
      created: true,
    });
    expect(prepareChildTheme('parent', () => good('parent\njooservices-compat-child\n'))).toEqual({
      slug: 'jooservices-compat-child',
      created: false,
    });
    const removed = [];
    removeChildTheme(
      null,
      () => good(),
      (message) => removed.push(message)
    );
    removeChildTheme(
      { slug: 'child', created: false },
      () => good(),
      (message) => removed.push(message)
    );
    removeChildTheme(
      { slug: 'child', created: true },
      () => bad(),
      (message) => removed.push(message)
    );
    expect(removed).toEqual(['warning: could not remove generated child theme child']);
  });

  it('parses Playwright results and provides a fallback for missing JSON', () => {
    expect(browserResult(good())).toEqual({ failed: false, notes: [] });
    const report = JSON.stringify({
      suites: [
        { specs: [{ title: 'layout', ok: false, tests: [{ results: [{ error: { message: 'broken\ntrace' } }] }] }] },
      ],
    });
    expect(browserResult(bad(), report)).toEqual({ failed: true, notes: ['browser layout: broken'] });
    expect(browserResult(bad('one\ntwo')).notes).toEqual(['browser failed without a report: two']);
  });
});

describe('runMatrix', () => {
  it('runs successful runtime smoke, writes both reports and keeps an existing child theme', async () => {
    const io = fakeIO({
      installedThemes: 'parent-theme\ncompat-child',
      child: { slug: 'compat-child', created: false },
    });
    const { report, exitCode } = await runMatrix({
      args: ['--theme', 'parent-theme', '--runtime-only', '--child-theme', '--base', 'http://local'],
      env: { E2E_BASE_URL: 'http://wordpress' },
      io,
    });
    expect(exitCode).toBe(0);
    expect(report.results[0].result).toBe('PASS');
    expect(report.testedAt).toBe('2026-10-16T12:00:00.000Z');
    expect(io.calls.filter((call) => call[0] === 'health')).toHaveLength(2);
    expect(io.calls.find((call) => call[0] === 'browser')[3].paths.page).toBe('/about/');
    expect(io.calls.some((call) => call[0] === 'removeChild')).toBe(false);
    expect([...io.files.keys()]).toHaveLength(2);
  });

  it('reports plugin pass, unsupported and skipped entries and cleans up its child theme', async () => {
    const io = fakeIO({
      child: { slug: 'compat-child', created: true },
      zips: ['licensed'],
      plugins: [
        {
          key: 'ok',
          name: 'Okay',
          group: 'SEO',
          tier: 1,
          slug: 'seo',
          source: 'wporg',
          setup: ['option update sample enabled'],
        },
        { key: 'licensed', name: 'Licensed', group: 'Other', tier: 1, slug: '', source: 'manual' },
        { key: 'wp-rocket', name: 'Absent', group: 'Cache', tier: 1, slug: '', source: 'manual' },
        { key: 'empty', name: 'No slug', group: 'Other', tier: 1, slug: '', source: 'wporg' },
      ],
    });
    const { report, exitCode } = await runMatrix({ args: ['--child-theme', '--no-browser'], env: {}, io });
    expect(exitCode).toBe(0);
    expect(report.counts).toEqual({ pass: 2, fail: 0, skipped: 1, unsupported: 1, blocked: 0 });
    expect(report.results[0].version).toBe('1.2.3');
    expect(
      io.calls.some(
        (call) =>
          call[0] === 'wp' &&
          call[1] === 'plugin' &&
          call[2] === 'install' &&
          call[3] === '/workspace/tests/matrix/vendor/licensed.zip'
      )
    ).toBe(true);
    expect(io.calls.some((call) => call[0] === 'removeChild')).toBe(true);
    expect(
      io.calls.some(
        (call) => call[0] === 'wp' && call[1] === 'theme' && call[2] === 'activate' && call[3] === 'parent-theme'
      )
    ).toBe(true);
  });

  it('marks health, browser, PHP log and setup failures and adds tier 3 limits', async () => {
    const io = fakeIO({
      setupFailure: 'option update sample enabled',
      phpLog: 'PHP Warning: bad',
      healthRows: [{ ok: false, name: 'home', problems: ['bad title'] }],
      browser: (plugin) =>
        plugin === 'elementor' ? { failed: true, notes: ['browser visual failed'] } : { failed: false, notes: [] },
      plugins: [
        {
          key: 'simple',
          name: 'Simple',
          group: 'SEO',
          tier: 1,
          slug: 'seo',
          source: 'wporg',
          setup: ['option update sample enabled'],
        },
        { key: 'woocommerce', name: 'WooCommerce', group: 'Shop', tier: 3, slug: 'woocommerce', source: 'wporg' },
        { key: 'elementor', name: 'Elementor', group: 'Builder', tier: 3, slug: 'elementor', source: 'wporg' },
      ],
    });
    const { report, exitCode } = await runMatrix({ args: [], env: {}, io });
    expect(exitCode).toBe(1);
    expect(report.counts.fail).toBe(3);
    expect(report.results[0].notes.join(' ')).toMatch(/setup command failed|health home|php log/);
    expect(report.results[1].notes.join(' ')).toContain('Shop templates are unstyled');
    expect(report.results[2].notes.join(' ')).toContain('browser visual failed');
  });

  it('marks offline installs blocked, restores snapshots, and reports snapshot errors', async () => {
    const plugin = { key: 'one', name: 'One', group: 'Other', tier: 1, slug: 'one', source: 'wporg' };
    const offline = fakeIO({ plugins: [plugin], installFailure: bad('Could not resolve host') });
    const blocked = await runMatrix({ args: ['--only', 'one'], env: {}, io: offline });
    expect(blocked.report.counts.blocked).toBe(1);
    expect(blocked.exitCode).toBe(1);
    expect(offline.calls.some((call) => call[0] === 'wp' && call[1] === 'db' && call[2] === 'import')).toBe(true);
    const snapshot = await runMatrix({
      args: ['--only', 'one'],
      env: {},
      io: fakeIO({ plugins: [plugin], dumpFailure: true }),
    });
    expect(snapshot.report.results[0].notes[0]).toContain('could not snapshot');
  });

  it('fails a plugin cell when its database snapshot cannot be restored', async () => {
    const io = fakeIO({
      plugins: [{ key: 'one', name: 'One', group: 'Other', tier: 1, slug: 'one', source: 'wporg' }],
      cleanupFailure: 'db import /workspace/artifacts/matrix/one.sql',
    });
    const { report, exitCode } = await runMatrix({ args: ['--only', 'one', '--no-browser'], env: {}, io });
    expect(exitCode).toBe(1);
    expect(report.results[0].result).toBe('FAIL');
    expect(report.results[0].notes.join(' ')).toContain('database restore failed: cleanup failed');
  });

  it('handles runtime activation and browser smoke failures, then cleans up child on exceptions', async () => {
    const runtime = fakeIO({
      child: { slug: 'compat-child', created: true },
      activateFailure: ['compat-child'],
      browser: () => ({ failed: true, notes: [] }),
    });
    const result = await runMatrix({ args: ['--runtime-only', '--child-theme'], env: {}, io: runtime });
    expect(result.exitCode).toBe(1);
    expect(result.report.results[0].notes.join(' ')).toMatch(/browser smoke failed|could not activate/);
    expect(runtime.calls.some((call) => call[0] === 'removeChild')).toBe(true);

    const throwing = fakeIO({
      child: { slug: 'compat-child', created: true },
      plugins: [{ key: 'explode', name: 'Explode', group: 'Other', tier: 1, slug: 'explode', source: 'wporg' }],
      throwOnInstall: true,
    });
    await expect(runMatrix({ args: ['--child-theme'], env: {}, io: throwing })).rejects.toThrow(
      'install adapter failed'
    );
    expect(throwing.calls.some((call) => call[0] === 'removeChild')).toBe(true);
  });
});
