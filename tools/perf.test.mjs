// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { checkAssetBudgets, checkBudgets, remoteChromeConnection } from './perf.mjs';
import { readCodeBudgets } from './lib/perf-budgets.mjs';

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

describe('documented code budgets', () => {
  const codeBudgets = readCodeBudgets();

  it('rejects an oversized JavaScript bundle fixture', () => {
    const runtimeBudget = codeBudgets.javascript.runtime;
    const assets = Object.fromEntries(
      Object.entries(codeBudgets.javascript).map(([name, max]) => [name, { type: 'js', gzip: max }])
    );
    assets.runtime.gzip = runtimeBudget + 1;

    expect(checkAssetBudgets(codeBudgets, assets)).toContain(
      `runtime gzip ${runtimeBudget + 1} B exceeds budget ${runtimeBudget} B`
    );
  });

  it('sets a single enforced limit for each documented JavaScript bundle', () => {
    expect(Object.keys(codeBudgets.javascript)).toEqual([
      'runtime',
      'blocks/featured-slider/view',
      'blocks/toc/view',
      'blocks/companies/view',
      'blocks/category-tree/view',
      'terminal-engine',
      'terminal',
    ]);
  });
});

describe('remote Lighthouse browser', () => {
  it('uses the Dev VM debugging endpoint when configured', () => {
    expect(
      remoteChromeConnection({ LIGHTHOUSE_CDP_HOST: 'host.docker.internal', LIGHTHOUSE_CDP_PORT: '9321' })
    ).toEqual({
      host: 'host.docker.internal',
      port: 9321,
    });
  });

  it('keeps local launch as the default', () => {
    expect(remoteChromeConnection({})).toBeNull();
  });

  it('rejects an invalid debugging port or missing hostname', () => {
    expect(() => remoteChromeConnection({ LIGHTHOUSE_CDP_HOST: 'localhost', LIGHTHOUSE_CDP_PORT: 'nope' })).toThrow(
      'Invalid LIGHTHOUSE_CDP_PORT'
    );
    expect(() => remoteChromeConnection({ LIGHTHOUSE_CDP_PORT: '9321' })).toThrow('LIGHTHOUSE_CDP_HOST is required');
  });
});

describe('starter performance budgets', () => {
  it('defines representative pages and keeps the shared Lighthouse thresholds', () => {
    const starter = JSON.parse(readFileSync(new URL('../themes/starter/budgets.json', import.meta.url), 'utf8'));
    const jooservices = JSON.parse(
      readFileSync(new URL('../themes/jooservices/budgets.json', import.meta.url), 'utf8')
    );

    expect(starter.pages.map(({ name }) => name)).toEqual(['home', 'single', 'category', 'search', 'about']);
    expect(starter.pages.find(({ name }) => name === 'search').noindex).toBe(true);
    expect(starter.scores).toEqual(jooservices.scores);
    expect(starter.metrics).toEqual(jooservices.metrics);
    expect(starter.bytes).toEqual(jooservices.bytes);
  });
});
