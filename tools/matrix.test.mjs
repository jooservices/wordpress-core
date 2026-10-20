// @vitest-environment node
import { describe, expect, it } from 'vitest';
import {
  browserSmoke,
  phpProblems,
  renderMarkdown,
  resultCounts,
  runHealthViaWeb,
  runtimeLabel,
  skipReason,
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
    const result = browserSmoke(
      'runtime',
      'jooservices',
      { browserBase: 'http://wordpress', paths: { single: '/hello/' } },
      {
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
    expect(result).toEqual({ failed: true, notes: ['browser failed without a report: last line'] });
  });
});
