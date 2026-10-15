// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { checkBudgets } from './perf.mjs';

const budgets = {
  scores: { performance: 0.9, seo: 0.95 },
  metrics: { 'cumulative-layout-shift': 0.02 },
  bytes: { css: 100 },
};
const ok = {
  scores: { performance: 0.95, seo: 1 },
  metrics: { 'cumulative-layout-shift': 0.01 },
  bytes: { css: 50 },
  crawlable: 1,
};

describe('perf budgets', () => {
  it('passes a result inside every budget', () => {
    expect(checkBudgets(budgets, ok)).toEqual([]);
  });
  it('reports each exceeded budget', () => {
    const bad = {
      ...ok,
      scores: { performance: 0.5, seo: 1 },
      metrics: { 'cumulative-layout-shift': 0.3 },
      bytes: { css: 500 },
    };
    expect(checkBudgets(budgets, bad)).toHaveLength(3);
  });
  it('inverts the SEO check for noindex pages', () => {
    const noindex = { ...ok, scores: { performance: 0.95, seo: 0.5 }, crawlable: 0 };
    expect(checkBudgets(budgets, noindex, { noindex: true })).toEqual([]);
    expect(checkBudgets(budgets, { ...noindex, crawlable: 1 }, { noindex: true })).toHaveLength(1);
  });
});
