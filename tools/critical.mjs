// Generates assets/critical/{context}.css for a theme from the running demo.
// Usage: node tools/critical.mjs --theme jooservices [--base http://127.0.0.1:8080] [--max-gzip 14336]
import { chromium } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { fromRoot } from './lib/paths.mjs';
import { extractCriticalRules, mergeAndMinify } from './lib/critical.mjs';

const args = Object.fromEntries(
  process.argv
    .slice(2)
    .reduce(
      (acc, a, i, all) =>
        a.startsWith('--') ? [...acc, [a.slice(2), all[i + 1]?.startsWith('--') ? true : (all[i + 1] ?? true)]] : acc,
      []
    )
);
const theme = String(args.theme ?? 'jooservices');
const base = String(args.base ?? 'http://127.0.0.1:8080').replace(/\/$/, '');
const maxGzip = Number(args['max-gzip'] ?? 14 * 1024);
const outDir = fromRoot('themes', theme, 'assets', 'critical');
const viewports = [
  { width: 390, height: 844 },
  { width: 820, height: 1180 },
  { width: 1440, height: 900 },
];

/** @param {string} path @returns {Promise<any>} */
async function api(path) {
  const response = await fetch(`${base}/wp-json/${path}`);
  return response.ok ? response.json() : [];
}
const [posts, categories, pages] = await Promise.all([
  api('wp/v2/posts?per_page=1&_fields=link'),
  api('wp/v2/categories?per_page=1&hide_empty=true&_fields=link'),
  api('wp/v2/pages?per_page=1&_fields=link'),
]);
const targets = {
  home: `${base}/`,
  single: posts[0]?.link,
  archive: categories[0]?.link,
  page: pages[0]?.link,
  search: `${base}/?s=a`,
  404: `${base}/definitely-missing-critical-url/`,
};

mkdirSync(outDir, { recursive: true });
const browser = await chromium.launch();
let failed = false;
for (const [context, url] of Object.entries(targets)) {
  if (!url) {
    console.log(`skip ${context}: no URL`);
    continue;
  }
  const lists = [];
  for (const viewport of viewports) {
    const page = await browser.newPage({ viewport, reducedMotion: 'reduce' });
    await page.goto(url, { waitUntil: 'networkidle' });
    lists.push(await extractCriticalRules(page));
    await page.close();
  }
  const { css, size, gzip } = mergeAndMinify(lists);
  writeFileSync(join(outDir, `${context}.css`), css);
  const ok = gzip <= maxGzip;
  failed ||= !ok;
  console.log(
    `${ok ? 'ok  ' : 'FAIL'} ${context.padEnd(7)} ${String(size).padStart(6)} B  ${String(gzip).padStart(6)} B gzip (limit ${maxGzip})`
  );
}
await browser.close();
process.exit(failed ? 1 : 0);
