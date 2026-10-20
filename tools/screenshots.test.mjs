// @vitest-environment node
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { JSDOM } from 'jsdom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  captureScreenshots,
  isGeneratedScreenshot,
  SCHEMES,
  screenshotFilename,
  screenshotOptions,
  runScreenshotCli,
  waitForScreenshotAssets,
} from './screenshots.mjs';

let tempRoot;

function outputPath(prefix) {
  tempRoot = mkdtempSync(join(tmpdir(), prefix));
  return tempRoot;
}

afterEach(() => {
  if (tempRoot) rmSync(tempRoot, { recursive: true, force: true });
  tempRoot = undefined;
  vi.restoreAllMocks();
});

describe('showcase screenshot options', () => {
  it('defaults to the complete gallery', () => {
    const options = screenshotOptions([]);
    expect(options.views).toHaveLength(9);
    expect(options.viewports.map(([name]) => name)).toEqual(['phone', 'tablet', 'desktop']);
    expect(options.schemes).toEqual(SCHEMES);
    expect(options.output.endsWith('/artifacts/screenshots/jooservices')).toBe(true);
  });

  it('selects only requested generated buyer-guide screenshots', () => {
    const options = screenshotOptions([
      '--theme',
      'jooservices',
      '--base',
      'http://127.0.0.1:8082/',
      '--output',
      'docs/buyer/assets',
      '--views',
      'home,about',
      '--viewports',
      'desktop',
      '--schemes',
      'dark',
    ]);
    expect(options.base).toBe('http://127.0.0.1:8082');
    expect(options.views.map(([name]) => name)).toEqual(['home', 'about']);
    expect(options.viewports.map(([name]) => name)).toEqual(['desktop']);
    expect(options.schemes).toEqual(['dark']);
    expect(options.output.endsWith('/docs/buyer/assets')).toBe(true);
  });

  it('rejects unknown views, viewports, and schemes', () => {
    expect(() => screenshotOptions(['--views', 'unknown'])).toThrow('unknown view: unknown');
    expect(() => screenshotOptions(['--viewports', 'wide'])).toThrow('unknown viewport: wide');
    expect(() => screenshotOptions(['--schemes', 'sepia'])).toThrow('unknown scheme: sepia');
  });

  it('recognizes generated screenshot filenames only', () => {
    const filename = screenshotFilename('about', 'desktop', 'dark');
    expect(filename).toBe('about-desktop-dark.png');
    expect(isGeneratedScreenshot(filename)).toBe(true);
    expect(isGeneratedScreenshot('manual-capture.png')).toBe(false);
  });

  it('captures the selected views and closes each viewport context', async () => {
    const options = screenshotOptions([
      '--output',
      outputPath('jooservices-screenshots-test-'),
      '--base',
      'http://127.0.0.1:8082',
      '--views',
      'about',
      '--viewports',
      'desktop',
      '--schemes',
      'dark',
    ]);
    const page = {
      goto: vi.fn(),
      evaluate: vi.fn(),
      locator: vi.fn(() => ({ count: vi.fn().mockResolvedValue(0) })),
      screenshot: vi.fn(),
    };
    const context = { newPage: vi.fn().mockResolvedValue(page), close: vi.fn() };
    const browser = { newContext: vi.fn().mockResolvedValue(context) };

    await expect(captureScreenshots(options, browser)).resolves.toBe(1);
    expect(browser.newContext).toHaveBeenCalledWith({
      viewport: { width: 1440, height: 900 },
      colorScheme: 'dark',
      reducedMotion: 'reduce',
    });
    expect(page.goto).toHaveBeenCalledWith('http://127.0.0.1:8082/about/', { waitUntil: 'load' });
    expect(page.screenshot).toHaveBeenCalledWith({
      path: join(tempRoot, 'about-desktop-dark.png'),
      fullPage: true,
    });
    expect(context.close).toHaveBeenCalledOnce();
  });

  it('waits for automatic fragments, scroll-loads and decodes images, then restores the top position', async () => {
    const dom = new JSDOM(
      '<joo-lazy data-src="/auto"><div class="joo-lazy__placeholder"></div></joo-lazy>' +
        '<joo-lazy data-src="/manual"><button data-joo-load></button><div class="joo-lazy__placeholder"></div></joo-lazy>' +
        '<div hidden><joo-lazy data-src="/hidden"><div class="joo-lazy__placeholder"></div></joo-lazy></div>' +
        '<joo-lazy name="related" data-src="/related"><div class="joo-lazy__placeholder"></div></joo-lazy>' +
        '<img src="/thumb.png"><img src="/offscreen.png">',
      { runScripts: 'outside-only', pretendToBeVisual: true }
    );
    const { document, window } = dom.window;
    const autoFragment = document.querySelector('[data-src="/auto"]');
    const manualFragment = document.querySelector('[data-src="/manual"]');
    const hiddenFragment = document.querySelector('[data-src="/hidden"]');
    const relatedFragment = document.querySelector('[data-src="/related"]');
    autoFragment.state = 'idle';
    manualFragment.state = 'idle';
    autoFragment.scrollIntoView = vi.fn(() => {
      autoFragment.state = 'done';
      autoFragment.querySelector('.joo-lazy__placeholder').remove();
    });
    manualFragment.scrollIntoView = vi.fn();
    hiddenFragment.scrollIntoView = vi.fn();
    relatedFragment.scrollIntoView = vi.fn();
    const image = document.querySelector('img');
    const offscreenImage = document.querySelector('img[src="/offscreen.png"]');
    Object.defineProperties(image, {
      complete: { configurable: true, value: true },
      naturalWidth: { configurable: true, value: 320 },
    });
    Object.defineProperties(offscreenImage, {
      complete: { configurable: true, value: true },
      naturalWidth: { configurable: true, value: 320 },
    });
    image.decode = vi.fn().mockResolvedValue(undefined);
    offscreenImage.decode = vi.fn().mockResolvedValue(undefined);
    image.getBoundingClientRect = () => ({ width: 320, height: 180, left: 0, right: 320 });
    offscreenImage.getBoundingClientRect = () => ({ width: 320, height: 180, left: 1600, right: 1920 });
    Object.defineProperty(document.documentElement, 'scrollHeight', { configurable: true, value: 1500 });
    Object.defineProperty(window, 'innerHeight', { configurable: true, value: 500 });
    Object.defineProperty(document, 'fonts', { configurable: true, value: { ready: Promise.resolve() } });
    window.scrollTo = vi.fn();

    const locator = (element) => ({
      locator: (selector) => ({ count: vi.fn().mockResolvedValue(element.querySelectorAll(selector).length) }),
      getAttribute: async (name) => element.getAttribute(name),
      evaluate: async (callback) => callback(element),
      isVisible: async () => !element.closest('[hidden]'),
      scrollIntoViewIfNeeded: async () => element.scrollIntoView(),
      elementHandle: async () => element,
    });
    const page = {
      locator: vi.fn((selector) => {
        const elements = [...document.querySelectorAll(selector)];
        return {
          count: vi.fn().mockResolvedValue(elements.length),
          nth: (index) => locator(elements[index]),
        };
      }),
      waitForFunction: vi.fn(async (predicate, element) => expect(predicate(element)).toBe(true)),
      evaluate: async (callback) => window.eval(`(${callback.toString()})()`),
    };

    try {
      await waitForScreenshotAssets(page);
      expect(autoFragment.scrollIntoView).toHaveBeenCalledOnce();
      expect(manualFragment.scrollIntoView).not.toHaveBeenCalled();
      expect(hiddenFragment.scrollIntoView).not.toHaveBeenCalled();
      expect(relatedFragment.scrollIntoView).not.toHaveBeenCalled();
      expect(page.waitForFunction).toHaveBeenCalledOnce();
      expect(image.decode).toHaveBeenCalledOnce();
      expect(offscreenImage.decode).not.toHaveBeenCalled();
      expect(image.hasAttribute('loading')).toBe(false);
      expect(window.scrollTo).toHaveBeenLastCalledWith(0, 0);
    } finally {
      window.close();
    }
  });

  it('runs the CLI, reports its output, and closes the browser', async () => {
    const close = vi.fn();
    const browser = {
      newContext: vi.fn().mockResolvedValue({
        newPage: vi.fn().mockResolvedValue({
          goto: vi.fn(),
          evaluate: vi.fn(),
          locator: vi.fn(() => ({ count: vi.fn().mockResolvedValue(0) })),
          screenshot: vi.fn(),
        }),
        close: vi.fn(),
      }),
      close,
    };
    const log = vi.spyOn(console, 'log').mockImplementation(() => {});

    await expect(
      runScreenshotCli(
        [
          '--output',
          outputPath('jooservices-screenshots-cli-'),
          '--views',
          'about',
          '--viewports',
          'desktop',
          '--schemes',
          'dark',
        ],
        async () => browser
      )
    ).resolves.toBe(1);
    expect(log).toHaveBeenCalledWith(`1 screenshots in ${tempRoot}`);
    expect(close).toHaveBeenCalledOnce();
  });

  it('closes the browser even when a screenshot capture fails', async () => {
    const close = vi.fn();
    const browser = {
      newContext: vi.fn().mockRejectedValue(new Error('browser context failed')),
      close,
    };
    await expect(
      runScreenshotCli(
        [
          '--output',
          outputPath('jooservices-screenshots-error-'),
          '--views',
          'about',
          '--viewports',
          'desktop',
          '--schemes',
          'dark',
        ],
        async () => browser
      )
    ).rejects.toThrow('browser context failed');
    expect(close).toHaveBeenCalledOnce();
  });
});
