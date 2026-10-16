import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { chromium } from '@playwright/test';

/** @typedef {{ width: number; height: number }} Viewport */
/** @typedef {{ url: () => string }} PreviewRequest */
/** @typedef {{ on: (event: 'request', listener: (request: PreviewRequest) => void) => void; setViewportSize: (viewport: Viewport) => Promise<unknown>; goto: (target: string, options: { waitUntil: 'networkidle' }) => Promise<{ ok: () => boolean; status: () => number } | null | undefined>; evaluate: (callback: () => Promise<unknown>) => Promise<unknown>; locator: (selector: string) => { first: () => { getAttribute: (name: string) => Promise<string | null> } }; screenshot: (options: { path: string; type?: 'jpeg'; quality?: number; animations: 'disabled' }) => Promise<unknown> }} PreviewPage */
/** @typedef {{ newPage: (options: { viewport: Viewport; deviceScaleFactor: number; colorScheme: 'dark'; reducedMotion: 'reduce' }) => Promise<PreviewPage> }} PreviewBrowser */

/** Return the origin for a request URL, or null for browser-local schemes. */
export function requestOrigin(requestUrl) {
  try {
    const parsed = new URL(requestUrl);
    return parsed.protocol === 'http:' || parsed.protocol === 'https:' ? parsed.origin : null;
  } catch {
    return null;
  }
}

/** Capture product and listing previews from a synthetic-content showcase. */
/** @param {{ browser: PreviewBrowser; url?: string; screenshot?: string; previewDir?: string; makeDirectory?: (path: string, options: { recursive: true }) => unknown; log?: typeof console.log }} options */
export async function captureMarketplacePreview({
  browser,
  url = process.env.E2E_BASE_URL ?? 'http://wordpress',
  screenshot = 'themes/jooservices/screenshot.png',
  previewDir = 'docs/release/previews',
  makeDirectory = mkdirSync,
  log = console.log,
}) {
  const screenshotPath = resolve(screenshot);
  const previewPath = resolve(previewDir);
  const origin = new URL(url).origin;
  const externalRequests = [];
  const page = await browser.newPage({
    viewport: { width: 1200, height: 900 },
    deviceScaleFactor: 1,
    colorScheme: 'dark',
    reducedMotion: 'reduce',
  });

  page.on('request', (request) => {
    const requestUrl = request.url();
    const requestOriginValue = requestOrigin(requestUrl);
    if (requestOriginValue && requestOriginValue !== origin) externalRequests.push(requestUrl);
  });

  const load = async (target, viewport) => {
    await page.setViewportSize(viewport);
    const response = await page.goto(target, { waitUntil: 'networkidle' });
    if (!response?.ok()) throw new Error(`showcase returned HTTP ${response?.status() ?? 'no response'}`);
    await page.evaluate(() => document.fonts.ready.then(() => true));
  };
  const capture = async (path, target, viewport, type) => {
    await load(target, viewport);
    makeDirectory(dirname(path), { recursive: true });
    await page.screenshot({ path, type, quality: type === 'jpeg' ? 90 : undefined, animations: 'disabled' });
  };

  await capture(screenshotPath, url, { width: 1200, height: 900 });
  await capture(`${previewPath}/00_cover-draft.png`, url, { width: 2340, height: 1560 });
  await capture(`${previewPath}/01_jooservices-home.jpg`, url, { width: 590, height: 300 }, 'jpeg');
  await capture(`${previewPath}/02_desktop-home.png`, url, { width: 1170, height: 780 });
  const postPath = await page.locator('.joo-card__title a').first().getAttribute('href');
  if (!postPath) throw new Error('showcase has no article link for the single-post preview');
  await capture(`${previewPath}/03_single-post.png`, new URL(postPath, url).toString(), { width: 1170, height: 780 });
  await capture(`${previewPath}/04_mobile-home.png`, url, { width: 390, height: 844 });

  if (externalRequests.length) {
    throw new Error(`showcase requested third-party resources: ${externalRequests.join(', ')}`);
  }

  const captures = [
    screenshotPath,
    `${previewPath}/00_cover-draft.png`,
    `${previewPath}/01_jooservices-home.jpg`,
    `${previewPath}/02_desktop-home.png`,
    `${previewPath}/03_single-post.png`,
    `${previewPath}/04_mobile-home.png`,
  ];
  log(`captured ${screenshotPath} (1200x900) and five previews in ${previewPath}`);
  return { captures, externalRequests };
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
  const browser = await chromium.launch();
  try {
    await captureMarketplacePreview({
      browser,
      url: process.env.E2E_BASE_URL ?? 'http://wordpress',
      screenshot: process.argv[2] ?? 'themes/jooservices/screenshot.png',
      previewDir: process.argv[3] ?? 'docs/release/previews',
    });
  } finally {
    await browser.close();
  }
}
