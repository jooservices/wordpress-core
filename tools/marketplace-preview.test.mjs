import { describe, expect, it, vi } from 'vitest';
import { captureMarketplacePreview, requestOrigin } from './marketplace-preview.mjs';

/** @param {{ response?: { ok: () => boolean; status: () => number } | null; postPath?: string | null; requestUrl?: string }} options */
const makeBrowser = ({
  response = { ok: () => true, status: () => 200 },
  postPath = '/story/',
  requestUrl = 'http://showcase.test/wp-content/themes/jooservices/style.css',
} = {}) => {
  const calls = { directories: [], gotos: [], selectors: [], screenshots: [], viewports: [] };
  const page = {
    on: vi.fn((event, listener) => {
      if (event === 'request') page.requestListener = listener;
    }),
    setViewportSize: vi.fn(async (viewport) => calls.viewports.push(viewport)),
    goto: vi.fn(async (target) => {
      calls.gotos.push(target);
      page.requestListener?.({ url: () => requestUrl });
      return response;
    }),
    evaluate: vi.fn(async () => true),
    locator: vi.fn((selector) => {
      calls.selectors.push(selector);
      return {
        first: () => ({ getAttribute: async () => postPath }),
      };
    }),
    screenshot: vi.fn(async (options) => calls.screenshots.push(options)),
  };
  const browser = { newPage: vi.fn(async () => page) };
  return { browser, calls, page };
};

describe('marketplace preview capture', () => {
  it('captures the 1200x900 screenshot and five listing previews', async () => {
    const { browser, calls } = makeBrowser();
    const makeDirectory = (...args) => {
      calls.directories.push(args);
    };
    const log = vi.fn();
    const result = await captureMarketplacePreview({
      browser,
      url: 'http://showcase.test',
      screenshot: '/tmp/jooservices/screenshot.png',
      previewDir: '/tmp/jooservices/previews',
      makeDirectory,
      log,
    });

    expect(result.captures).toEqual([
      '/tmp/jooservices/screenshot.png',
      '/tmp/jooservices/previews/00_cover-draft.png',
      '/tmp/jooservices/previews/01_jooservices-home.jpg',
      '/tmp/jooservices/previews/02_desktop-home.png',
      '/tmp/jooservices/previews/03_single-post.png',
      '/tmp/jooservices/previews/04_mobile-home.png',
    ]);
    expect(result.externalRequests).toEqual([]);
    expect(calls.selectors).toEqual(['.wp-block-post-title a']);
    expect(calls.gotos).toEqual([
      'http://showcase.test',
      'http://showcase.test',
      'http://showcase.test',
      'http://showcase.test',
      'http://showcase.test/story/',
      'http://showcase.test',
    ]);
    expect(calls.viewports).toEqual([
      { width: 1200, height: 900 },
      { width: 2340, height: 1560 },
      { width: 590, height: 300 },
      { width: 1170, height: 780 },
      { width: 1170, height: 780 },
      { width: 390, height: 844 },
    ]);
    expect(calls.screenshots[2]).toMatchObject({ type: 'jpeg', quality: 90, animations: 'disabled' });
    expect(calls.directories).toHaveLength(6);
    expect(log).toHaveBeenCalledOnce();
  });

  it('rejects third-party requests, including after collecting all preview requests', async () => {
    const { browser } = makeBrowser({ requestUrl: 'https://fonts.googleapis.com/css?family=Example' });

    await expect(
      captureMarketplacePreview({
        browser,
        url: 'http://showcase.test',
        screenshot: '/tmp/jooservices/screenshot.png',
        previewDir: '/tmp/jooservices/previews',
        makeDirectory: vi.fn(),
        log: vi.fn(),
      })
    ).rejects.toThrow('showcase requested third-party resources: https://fonts.googleapis.com/css?family=Example');
  });

  it('fails when a showcase request has no response', async () => {
    const { browser } = makeBrowser({ response: null });

    await expect(
      captureMarketplacePreview({
        browser,
        url: 'http://showcase.test',
        screenshot: '/tmp/jooservices/screenshot.png',
        previewDir: '/tmp/jooservices/previews',
        makeDirectory: vi.fn(),
        log: vi.fn(),
      })
    ).rejects.toThrow('showcase returned HTTP no response');
  });

  it('fails when the showcase responds with an HTTP error', async () => {
    const { browser } = makeBrowser({ response: { ok: () => false, status: () => 503 } });

    await expect(
      captureMarketplacePreview({
        browser,
        url: 'http://showcase.test',
        screenshot: '/tmp/jooservices/screenshot.png',
        previewDir: '/tmp/jooservices/previews',
        makeDirectory: vi.fn(),
        log: vi.fn(),
      })
    ).rejects.toThrow('showcase returned HTTP 503');
  });

  it('fails when the homepage contains no article preview link', async () => {
    const { browser } = makeBrowser({ postPath: null });

    await expect(
      captureMarketplacePreview({
        browser,
        url: 'http://showcase.test',
        screenshot: '/tmp/jooservices/screenshot.png',
        previewDir: '/tmp/jooservices/previews',
        makeDirectory: vi.fn(),
        log: vi.fn(),
      })
    ).rejects.toThrow('showcase has no article link for the single-post preview');
  });
});

describe('requestOrigin', () => {
  it('returns an HTTP origin and ignores browser-local URLs', () => {
    expect(requestOrigin('https://example.test/path')).toBe('https://example.test');
    expect(requestOrigin('data:image/png;base64,abc')).toBeNull();
    expect(requestOrigin('not a URL')).toBeNull();
  });
});
