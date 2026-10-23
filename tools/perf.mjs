// Lighthouse (mobile) and byte budgets for a theme running on the shared WordPress site.
// Usage: make perf THEME=jooservices [PERF_BASE_URL=http://...]
import { chromium } from '@playwright/test';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createConnection, createServer } from 'node:net';
import { dirname, resolve } from 'node:path';
import { gzipSync } from 'node:zlib';
import lighthouse from 'lighthouse';
import { launch } from 'chrome-launcher';
import { fromRoot, ROOT } from './lib/paths.mjs';
import { readCodeBudgets } from './lib/perf-budgets.mjs';

/** Reads one theme's Lighthouse budgets from the selected checkout. */
export function readThemeBudgets(theme, root = ROOT) {
  const path = resolve(root, 'themes', theme, 'budgets.json');
  try {
    return JSON.parse(readFileSync(path, 'utf8'));
  } catch (error) {
    throw new Error(`Cannot read Lighthouse budgets for theme "${theme}" at ${path}: ${error.message}`, {
      cause: error,
    });
  }
}

/** Compares measured values with budgets. Returns a list of problems. */
export function checkBudgets(budgets, result, page = {}) {
  const problems = [];
  for (const [id, min] of Object.entries(budgets.scores ?? {})) {
    if (page.noindex && id === 'seo') continue; // the inverted assertion below covers it
    if ((result.scores[id] ?? 0) < min) problems.push(`${id} score ${result.scores[id]} < ${min}`);
  }
  if (page.noindex && result.crawlable !== 0)
    problems.push('noindex page is still crawlable (is-crawlable audit passed)');
  for (const [id, max] of Object.entries(budgets.metrics ?? {})) {
    if ((result.metrics[id] ?? 0) > max)
      problems.push(`${id} ${Math.round((result.metrics[id] ?? 0) * 1000) / 1000} > ${max}`);
  }
  for (const [type, max] of Object.entries(budgets.bytes ?? {})) {
    if ((result.bytes[type] ?? 0) > max) problems.push(`${type} bytes ${result.bytes[type]} > ${max}`);
  }
  return problems;
}

/** Returns problems for the measured JavaScript bundle sizes. */
export function checkAssetBudgets(budgets, assets) {
  const problems = [];
  for (const [name, max] of Object.entries(budgets.javascript ?? {})) {
    const asset = assets[name];
    if (!asset) {
      problems.push(`missing JavaScript bundle: ${name}`);
    } else if (asset.type !== 'js' || !Number.isFinite(asset.gzip)) {
      problems.push(`invalid JavaScript bundle measurement: ${name}`);
    } else if (asset.gzip > max) {
      problems.push(`${name} gzip ${asset.gzip} B exceeds budget ${max} B`);
    }
  }
  return problems;
}

/** Checks emitted core bundles and the current critical CSS files against the documented limits. */
export function checkCodeBudgets({
  assetsManifest = fromRoot('packages', 'theme-core', 'resources', 'build', 'assets.json'),
  root = fromRoot(),
  checkCriticalCss = true,
  budgets = readCodeBudgets(),
} = {}) {
  const problems = [];
  const manifestPath = resolve(assetsManifest);
  if (!existsSync(manifestPath)) {
    problems.push(`missing asset manifest: ${manifestPath} (build core assets first)`);
  } else {
    try {
      const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
      const assetRoot = dirname(manifestPath);
      const measured = {};
      for (const [name, asset] of Object.entries(manifest.assets ?? {})) {
        if (asset.type !== 'js') continue;
        const file = resolve(assetRoot, asset.file);
        if (!existsSync(file)) {
          problems.push(`missing JavaScript bundle file: ${name} (${file})`);
          continue;
        }
        measured[name] = { type: asset.type, gzip: gzipSync(readFileSync(file)).byteLength };
      }
      problems.push(...checkAssetBudgets(budgets, measured));
    } catch (error) {
      problems.push(`cannot read JavaScript asset manifest: ${error.message}`);
    }
  }

  if (checkCriticalCss) {
    const critical = budgets.criticalCss;
    for (const theme of critical.themes) {
      for (const template of critical.templates) {
        const file = resolve(root, 'themes', theme, 'assets', 'critical', `${template}.css`);
        if (!existsSync(file)) {
          problems.push(`missing critical CSS: ${theme}/${template}.css`);
          continue;
        }
        const gzip = gzipSync(readFileSync(file)).byteLength;
        if (gzip > critical.maxGzipBytes) {
          problems.push(`${theme}/${template}.css gzip ${gzip} B exceeds budget ${critical.maxGzipBytes} B`);
        }
      }
    }
  }
  return problems;
}

function parseArgs(args) {
  const parsed = {};
  for (let index = 0; index < args.length; index += 1) {
    const arg = args[index];
    if (!arg.startsWith('--')) continue;
    const key = arg.slice(2);
    if (args[index + 1] === undefined || args[index + 1].startsWith('--')) parsed[key] = true;
    else parsed[key] = args[++index];
  }
  return parsed;
}

/** Returns Lighthouse's connection options when Chromium is supplied by the Dev VM. */
export function remoteChromeConnection(env = process.env) {
  if (env.LIGHTHOUSE_CDP_PORT === undefined || env.LIGHTHOUSE_CDP_PORT === '') return null;
  const port = Number(env.LIGHTHOUSE_CDP_PORT);
  if (!Number.isInteger(port) || port < 1 || port > 65535) {
    throw new Error(`Invalid LIGHTHOUSE_CDP_PORT: ${env.LIGHTHOUSE_CDP_PORT}`);
  }
  const host = String(env.LIGHTHOUSE_CDP_HOST ?? '').trim();
  if (!host) throw new Error('LIGHTHOUSE_CDP_HOST is required with LIGHTHOUSE_CDP_PORT.');
  return { host, port };
}

async function startCDPForwarder({ host, port }) {
  const server = createServer((client) => {
    const upstream = createConnection({ host, port });
    const close = () => {
      client.destroy();
      upstream.destroy();
    };
    client.on('error', close);
    upstream.on('error', close);
    client.pipe(upstream).pipe(client);
  });
  await new Promise((resolve, reject) => {
    server.once('error', reject);
    server.listen({ port, host: '127.0.0.1' }, () => resolve());
  });
  return server;
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = parseArgs(process.argv.slice(2));
  if (args['check-code-budgets']) {
    const problems = checkCodeBudgets({
      assetsManifest: args['assets-manifest'] ? resolve(String(args['assets-manifest'])) : undefined,
      checkCriticalCss: !args['skip-critical-css'],
    });
    if (problems.length) {
      console.error(`Performance code budgets failed:\n${problems.map((problem) => `  ${problem}`).join('\n')}`);
      process.exitCode = 1;
    } else {
      console.log('Performance code budgets passed.');
    }
  } else {
    const theme = String(args.theme ?? 'jooservices');
    const base = String(
      args.base ?? process.env.E2E_BASE_URL ?? process.env.WP_SITE_URL ?? 'http://127.0.0.1:8082'
    ).replace(/\/$/, '');
    const budgets = readThemeBudgets(theme);
    const remoteChrome = remoteChromeConnection();
    const chrome = remoteChrome
      ? null
      : await launch({
          chromePath: chromium.executablePath(),
          chromeFlags: ['--headless=new', '--no-sandbox'],
        });
    const cdpForwarder = remoteChrome ? await startCDPForwarder(remoteChrome) : null;
    const connection = remoteChrome ? { hostname: '127.0.0.1', port: remoteChrome.port } : { port: chrome.port };
    const report = [];
    let failed = false;
    try {
      for (const page of budgets.pages) {
        const run = await lighthouse(`${base}${page.path}`, {
          ...connection,
          output: 'json',
          logLevel: 'error',
          onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo'],
        });
        const lhr = run.lhr;
        const bytes = { css: 0, js: 0, fonts: 0, html: 0 };
        for (const item of /** @type {any} */ (lhr.audits['network-requests'].details).items) {
          const kind = { Stylesheet: 'css', Script: 'js', Font: 'fonts', Document: 'html' }[item.resourceType];
          if (kind) bytes[kind] += item.transferSize ?? 0;
        }
        const result = {
          name: page.name,
          scores: Object.fromEntries(Object.entries(lhr.categories).map(([id, c]) => [id, c.score])),
          metrics: Object.fromEntries(
            ['cumulative-layout-shift', 'total-blocking-time', 'largest-contentful-paint'].map((id) => [
              id,
              lhr.audits[id].numericValue,
            ])
          ),
          bytes,
          crawlable: lhr.audits['is-crawlable'].score,
        };
        result.problems = checkBudgets(budgets, result, page);
        failed ||= result.problems.length > 0;
        report.push(result);
        console.log(
          `${result.problems.length ? 'FAIL' : 'ok  '} ${result.name.padEnd(9)} perf ${result.scores.performance} a11y ${result.scores.accessibility} bp ${result.scores['best-practices']} seo ${result.scores.seo} | CLS ${result.metrics['cumulative-layout-shift'].toFixed(3)} TBT ${Math.round(result.metrics['total-blocking-time'])} LCP ${Math.round(result.metrics['largest-contentful-paint'])} | css ${bytes.css} js ${bytes.js} fonts ${bytes.fonts}${result.problems.length ? ' :: ' + result.problems.join('; ') : ''}`
        );
      }
    } finally {
      if (cdpForwarder) await new Promise((resolve) => cdpForwarder.close(resolve));
      await chrome?.kill();
    }
    mkdirSync(fromRoot('artifacts', 'reports', 'perf'), { recursive: true });
    writeFileSync(fromRoot('artifacts', 'reports', 'perf', `${theme}.json`), JSON.stringify(report, null, 2));
    process.exitCode = failed ? 1 : 0;
  }
}
