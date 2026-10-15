// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { problems } from './release.mjs';
import { check } from './theme-check.mjs';
import { render } from './perf-report.mjs';

describe('release', () => {
  const ok = { style: '1.2.3', changelog: '1.2.3', testedUpTo: '7.1', coreRange: '^0.1' };
  it('accepts consistent versions', () => expect(problems(ok, { latestWordPress: '7.1.3' })).toEqual([]));
  it('blocks a changelog that lags behind style.css', () =>
    expect(problems({ ...ok, changelog: '1.2.2' })).toHaveLength(1));
  it('blocks a non-semver version and a stale Tested up to', () => {
    expect(problems({ ...ok, style: '1.2' }).length).toBeGreaterThan(0);
    expect(problems({ ...ok, testedUpTo: '6.8' }, { latestWordPress: '7.1.3' })).toHaveLength(1);
  });
});

describe('theme-check', () => {
  it('reports a theme without header fields or screenshot', () => {
    expect(check('/nonexistent/acme').length).toBeGreaterThan(5);
  });
});

describe('perf-report', () => {
  it('renders one row per page', () => {
    const md = render('t', [
      {
        name: 'home',
        scores: { performance: 1, accessibility: 1, 'best-practices': 1, seo: 1 },
        metrics: { 'cumulative-layout-shift': 0.01, 'total-blocking-time': 0, 'largest-contentful-paint': 2000 },
        bytes: { css: 1, js: 2, fonts: 3 },
        problems: [],
      },
    ]);
    expect(md).toContain('| home | 1 | 1 | 1 | 1 | 0.010 | 0 | 2000 | 1 / 2 / 3 | ok |');
  });
});
