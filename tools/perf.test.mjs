// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import { build } from 'esbuild';
import {
  checkAssetBudgets,
  checkBudgets,
  checkRemoteChrome,
  localChromePath,
  readThemeBudgets,
  remoteChromeConnection,
} from './perf.mjs';
import { readCodeBudgets } from './lib/perf-budgets.mjs';
import { fromRoot } from './lib/paths.mjs';

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

  it('keeps the standalone terminal engine independent of built-in commands and within budget', async () => {
    const result = await build({
      entryPoints: [fromRoot('packages', 'theme-core', 'resources', 'js', 'terminal', 'engine.js')],
      bundle: true,
      write: false,
      format: 'esm',
      target: 'es2022',
      minify: true,
      legalComments: 'none',
      external: ['joo-runtime'],
      metafile: true,
    });
    const output = result.outputFiles[0].contents;
    const inputs = Object.values(result.metafile.outputs)[0].inputs;

    expect(Object.keys(inputs).some((file) => file.includes('/commands/'))).toBe(false);
    expect(gzipSync(output).byteLength).toBeLessThanOrEqual(codeBudgets.javascript['terminal-engine']);
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

  it('checks the DevTools endpoint from the Node runtime before running Lighthouse', async () => {
    const fetchMock = async (url, options) => {
      expect(url).toBe('http://host.docker.internal:9321/json/version');
      expect(options.signal).toBeInstanceOf(AbortSignal);
      return new Response(
        JSON.stringify({ Browser: 'Chrome/124.0', webSocketDebuggerUrl: 'ws://127.0.0.1/devtools/browser/id' })
      );
    };

    await expect(checkRemoteChrome({ host: 'host.docker.internal', port: 9321 }, fetchMock)).resolves.toMatchObject({
      Browser: 'Chrome/124.0',
    });
  });

  it('rejects an unreachable or invalid DevTools endpoint', async () => {
    const unreachable = async () => new Response('', { status: 502 });
    const invalid = async () => new Response(JSON.stringify({ Browser: 'Chrome/124.0' }));

    await expect(checkRemoteChrome({ host: 'host.docker.internal', port: 9321 }, unreachable)).rejects.toThrow(
      'HTTP 502'
    );
    await expect(checkRemoteChrome({ host: 'host.docker.internal', port: 9321 }, invalid)).rejects.toThrow(
      'invalid DevTools version response'
    );
  });
});

describe('localChromePath', () => {
  it('returns an existing Chromium path', () => {
    expect(
      localChromePath(
        () => '/opt/chrome',
        () => true
      )
    ).toBe('/opt/chrome');
  });

  it('explains how to use the Dev VM browser when Chromium is missing', () => {
    expect(() =>
      localChromePath(
        () => '/tmp/.cache/ms-playwright/chromium-1248/chrome',
        () => false
      )
    ).toThrow('make perf THEME=<theme>');
  });
});

describe('starter performance budgets', () => {
  it('resolves the starter budgets from this checkout at the documented path', () => {
    const starter = readThemeBudgets('starter');
    const documentedPath = readFileSync(fromRoot('docs/reference/budgets.md'), 'utf8');

    expect(starter.pages.map(({ name }) => name)).toEqual(['home', 'single', 'category', 'search', 'about']);
    expect(documentedPath).toContain('themes/starter/budgets.json');
    expect(() => readThemeBudgets('starter', '/tmp/perf-test-missing-checkout')).toThrow(
      '/tmp/perf-test-missing-checkout/themes/starter/budgets.json'
    );
  });

  it('keeps the documented pages and shared Lighthouse thresholds unchanged', () => {
    const starter = readThemeBudgets('starter');
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
