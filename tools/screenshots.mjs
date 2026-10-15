// Screenshot gallery of the running demo: every view at three viewports in both schemes.
// Usage: node tools/screenshots.mjs --theme jooservices [--base http://127.0.0.1:8080]
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { fromRoot } from './lib/paths.mjs';

export const VIEWS = [
  ['home', '/'],
  ['single', '/ai/prompt-caching/'],
  ['single-long', '/research-and-development/study-cases/complete-release-checklist/'],
  ['single-code', '/research-and-development/tips-and-tricks/queue-worker-listing/'],
  ['category', '/category/ai/'],
  ['topic', '/tag/mcp/'],
  ['search', '/?s=docker'],
  ['about', '/about/'],
  ['not-found', '/definitely-not-here/'],
];
/** @type {Array<[string, number, number]>} */
export const VIEWPORTS = [
  ['phone', 390, 844],
  ['tablet', 820, 1180],
  ['desktop', 1440, 900],
];

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = Object.fromEntries(
    process.argv
      .slice(2)
      .map((a, i, all) => (a.startsWith('--') ? [a.slice(2), all[i + 1]] : null))
      .filter(Boolean)
  );
  const theme = String(args.theme ?? 'jooservices');
  const base = String(args.base ?? 'http://127.0.0.1:8080').replace(/\/$/, '');
  const out = fromRoot('artifacts', 'screenshots', theme);
  mkdirSync(out, { recursive: true });
  const browser = await chromium.launch();
  let count = 0;
  for (const scheme of /** @type {const} */ (['dark', 'light'])) {
    for (const [vname, width, height] of VIEWPORTS) {
      const context = await browser.newContext({
        viewport: { width, height },
        colorScheme: scheme,
        reducedMotion: 'reduce',
      });
      const page = await context.newPage();
      for (const [name, path] of VIEWS) {
        await page.goto(base + path, { waitUntil: 'load' });
        // Let the lazy fragments (rail, related posts) load before the picture is taken.
        await page.evaluate(async () => {
          for (const lazy of document.querySelectorAll('joo-lazy')) lazy.scrollIntoView();
          window.scrollTo(0, 0);
        });
        await page.waitForTimeout(700);
        await page.screenshot({ path: join(out, `${name}-${vname}-${scheme}.png`), fullPage: true });
        count += 1;
      }
      await context.close();
    }
  }
  await browser.close();
  console.log(`${count} screenshots in artifacts/screenshots/${theme}`);
}
