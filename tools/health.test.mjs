// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { evaluate } from './health.mjs';

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
