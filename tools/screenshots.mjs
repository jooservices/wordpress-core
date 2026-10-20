// Screenshot gallery of the running showcase, or selected buyer-guide screenshots.
// Usage: node tools/screenshots.mjs --theme jooservices [--base URL] [--output DIR] [--views home,about]
import { mkdirSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { launchBrowser } from './lib/browser.mjs';
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

/** Wait for automatically loaded fragments, native lazy images, and fonts before a full-page capture. */
export async function waitForScreenshotAssets(page) {
  // Loading a fragment can add another deferred fragment, so repeat until a pass finds no pending auto-loads.
  for (let pass = 0; pass < 5; pass += 1) {
    const fragments = page.locator('joo-lazy[data-src]');
    const count = await fragments.count();
    let pending = false;
    for (let index = 0; index < count; index += 1) {
      const fragment = fragments.nth(index);
      if (!(await fragment.isVisible())) continue;
      if (['related', 'comments'].includes(await fragment.getAttribute('name'))) continue;
      if (await fragment.locator('[data-joo-load]').count()) continue;
      const done = await fragment.evaluate(
        (element) => element.state === 'done' || !element.querySelector('.joo-lazy__placeholder')
      );
      if (done) continue;

      pending = true;
      await fragment.scrollIntoViewIfNeeded();
      await page.waitForFunction(
        (element) => element.state === 'done' || !element.querySelector('.joo-lazy__placeholder'),
        await fragment.elementHandle()
      );
    }
    if (!pending) break;
  }

  await page.evaluate(async () => {
    const nextFrame = () => new Promise((resolve) => requestAnimationFrame(resolve));
    const images = Array.from(document.images).filter((image) => {
      const rect = image.getBoundingClientRect();
      return rect.width > 0 && rect.height > 0 && rect.right > 0 && rect.left < window.innerWidth;
    });
    const originalLoading = images.map((image) => image.getAttribute('loading'));
    try {
      images.forEach((image) => {
        image.loading = 'eager';
      });
      await Promise.all(
        images.map(async (image) => {
          if (!image.complete) {
            await new Promise((resolve, reject) => {
              const timer = setTimeout(() => reject(new Error('image did not finish loading before screenshot')), 5000);
              const finish = () => {
                clearTimeout(timer);
                resolve();
              };
              image.addEventListener('load', finish, { once: true });
              image.addEventListener('error', finish, { once: true });
            });
          }
          if (image.currentSrc && image.naturalWidth === 0)
            throw new Error('visible image failed to load before screenshot');
          if (image.naturalWidth > 0 && typeof image.decode === 'function') {
            await new Promise((resolve, reject) => {
              const timer = setTimeout(() => reject(new Error('image decode timed out before screenshot')), 5000);
              image.decode().then(
                () => {
                  clearTimeout(timer);
                  resolve();
                },
                (error) => {
                  clearTimeout(timer);
                  reject(error);
                }
              );
            });
          }
        })
      );
      await document.fonts.ready;
    } finally {
      images.forEach((image, index) => {
        if (originalLoading[index] === null) image.removeAttribute('loading');
        else image.setAttribute('loading', originalLoading[index]);
      });
      window.scrollTo(0, 0);
      await nextFrame();
    }
  });
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
        await waitForScreenshotAssets(page);
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
export async function runScreenshotCli(argv, launch = () => launchBrowser()) {
  const options = screenshotOptions(argv);
  const browser = /** @type {import('@playwright/test').Browser} */ (await launch());
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
