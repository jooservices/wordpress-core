// Screenshot gallery of the running showcase, or selected buyer-guide screenshots.
// Usage: node tools/screenshots.mjs --theme jooservices [--base URL] [--output DIR] [--views home,about]
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { join, resolve } from 'node:path';
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

export const SCHEMES = ['dark', 'light'];

export function screenshotFilename(view, viewport, scheme) {
  return `${view}-${viewport}-${scheme}.png`;
}

export function isGeneratedScreenshot(filename) {
  return VIEWS.some(([view]) =>
    VIEWPORTS.some(([viewport]) => SCHEMES.some((scheme) => filename === screenshotFilename(view, viewport, scheme)))
  );
}

/** Parse the screenshot runner arguments and validate selected showcase targets. */
export function screenshotOptions(argv) {
  const args = Object.fromEntries(
    argv.map((value, index) => (value.startsWith('--') ? [value.slice(2), argv[index + 1]] : null)).filter(Boolean)
  );
  const select = (value, allowed, name, fallback) => {
    const selected = String(value ?? fallback)
      .split(',')
      .map((item) => item.trim())
      .filter(Boolean);
    const unknown = selected.filter((item) => !allowed.includes(item));
    if (unknown.length) throw new Error(`unknown ${name}: ${unknown.join(', ')}`);
    return selected;
  };
  const views = select(
    args.views,
    VIEWS.map(([name]) => name),
    'view',
    VIEWS.map(([name]) => name).join(',')
  );
  const viewports = select(
    args.viewports,
    VIEWPORTS.map(([name]) => name),
    'viewport',
    VIEWPORTS.map(([name]) => name).join(',')
  );
  const schemes = select(args.schemes, SCHEMES, 'scheme', SCHEMES.join(','));
  return {
    theme: String(args.theme ?? 'jooservices'),
    base: String(args.base ?? process.env.E2E_BASE_URL ?? process.env.WP_SITE_URL ?? 'http://127.0.0.1:8082').replace(
      /\/$/,
      ''
    ),
    output: resolve(fromRoot(), String(args.output ?? `artifacts/screenshots/${args.theme ?? 'jooservices'}`)),
    views: VIEWS.filter(([name]) => views.includes(name)),
    viewports: VIEWPORTS.filter(([name]) => viewports.includes(name)),
    schemes,
  };
}

/** Capture the selected gallery pages using a Playwright-compatible browser instance. */
export async function captureScreenshots(options, browser) {
  const out = options.output;
  mkdirSync(out, { recursive: true });
  let count = 0;
  for (const scheme of options.schemes) {
    for (const [vname, width, height] of options.viewports) {
      const context = await browser.newContext({
        viewport: { width, height },
        colorScheme: scheme,
        reducedMotion: 'reduce',
      });
      const page = await context.newPage();
      for (const [name, path] of options.views) {
        await page.goto(options.base + path, { waitUntil: 'load' });
        // Let the lazy fragments (rail, related posts) load before the picture is taken.
        await page.evaluate(async () => {
          for (const lazy of document.querySelectorAll('joo-lazy')) lazy.scrollIntoView();
          window.scrollTo(0, 0);
        });
        await page.waitForTimeout(700);
        await page.screenshot({ path: join(out, screenshotFilename(name, vname, scheme)), fullPage: true });
        count += 1;
      }
      await context.close();
    }
  }
  return count;
}

/** Run the CLI workflow; launch is injectable so argument handling is testable without a browser.
 * @param {Array<string>} argv
 * @param {() => Promise<unknown>} [launch]
 */
export async function runScreenshotCli(argv, launch = () => chromium.launch()) {
  const options = screenshotOptions(argv);
  const browser = /** @type {Awaited<ReturnType<typeof chromium.launch>>} */ (await launch());
  try {
    const count = await captureScreenshots(options, browser);
    console.log(`${count} screenshots in ${options.output}`);
    return count;
  } finally {
    await browser.close();
  }
}

if (import.meta.url === `file://${process.argv[1]}`) {
  await runScreenshotCli(process.argv.slice(2));
}
