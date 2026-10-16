// @vitest-environment node
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  captureScreenshots,
  isGeneratedScreenshot,
  SCHEMES,
  screenshotFilename,
  screenshotOptions,
  runScreenshotCli,
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
      waitForTimeout: vi.fn(),
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

  it('runs the CLI, reports its output, and closes the browser', async () => {
    const close = vi.fn();
    const browser = {
      newContext: vi.fn().mockResolvedValue({
        newPage: vi.fn().mockResolvedValue({
          goto: vi.fn(),
          evaluate: vi.fn(),
          waitForTimeout: vi.fn(),
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
