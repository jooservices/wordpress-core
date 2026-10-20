// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { evaluate, renderResults } from './health.mjs';

const ok = { status: 200, type: 'text/html; charset=UTF-8', body: '<title>x</title>' };

describe('health evaluate', () => {
  it('passes a matching response', () => {
    expect(evaluate({ status: 200, contains: ['<title>'] }, ok)).toEqual([]);
  });
  it('reports status, missing and unexpected markers', () => {
    const problems = evaluate({ status: 404, contains: ['noindex'], notContains: ['<title>'] }, ok);
    expect(problems).toHaveLength(3);
  });
  it('matches content types', () => {
    expect(evaluate({ status: 200, type: 'json' }, { ...ok, type: 'application/json' })).toEqual([]);
    expect(evaluate({ status: 200, type: 'json' }, ok)).toHaveLength(1);
  });
});

describe('seoContains', () => {
  const check = { status: 200, seoContains: ['application/ld+json'] };
  it('applies the theme SEO markers only when no SEO plugin is active', () => {
    expect(evaluate(check, ok)).toHaveLength(1);
    expect(evaluate(check, ok, { seoActive: true })).toEqual([]);
  });
});

describe('health output', () => {
  it('renders matrix rows as JSON without a summary line', () => {
    const results = [{ name: 'home', path: '/', ok: true, problems: [] }];
    expect(renderResults(results, true)).toBe(JSON.stringify(results));
  });
  it('keeps the human-readable result and summary', () => {
    expect(renderResults([{ name: 'home', path: '/', ok: false, problems: ['status 500'] }])).toBe(
      'FAIL home (/) — status 500\n0/1 checks passed'
    );
  });
});
