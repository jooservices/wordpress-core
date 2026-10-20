import { test, expect } from '@playwright/test';

// Plugin matrix smoke: run by tools/matrix.mjs with one plugin active (MATRIX_PLUGIN names it).
// The URL types are fixed; dynamic paths arrive through MATRIX_PATHS (JSON from the runner).
const plugin = process.env.MATRIX_PLUGIN ?? 'none';
const paths: Record<string, string> = {
  home: '/',
  category: '/category/ai/',
  tag: '/tag/mcp/',
  search: '/?s=docker',
  ...(JSON.parse(process.env.MATRIX_PATHS ?? '{}') as Record<string, string>),
};
const CLS_BUDGET = Number(process.env.MATRIX_CLS ?? '0.02');
// Third-party admin-bar or consent scripts may talk to the network; only first-party failures count.
const ignorable = /favicon|net::ERR_|Failed to load resource: the server responded with a status of (401|403)/;

test(`${plugin}: CSS compatibility baseline`, async ({ page }, info) => {
  const browser = await page.evaluate(() => ({
    userAgent: navigator.userAgent,
    platform: navigator.platform,
    language: navigator.language,
  }));
  expect(browser.userAgent).toMatch(/Mozilla\//);
  await info.attach('browser-environment.json', { body: JSON.stringify(browser), contentType: 'application/json' });
  const supported = await page.evaluate(() => ({
    containerQueries: CSS.supports('container-type', 'inline-size'),
    relationalSelector: CSS.supports('selector(:has(*))'),
    colorMix: CSS.supports('background-color', 'color-mix(in srgb, red, blue)'),
  }));
  expect(supported).toEqual({ containerQueries: true, relationalSelector: true, colorMix: true });
});

for (const [name, path] of Object.entries(paths)) {
  test(`${plugin}: ${name}`, async ({ page }, info) => {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
    page.on('console', (m) => {
      if (m.type() === 'error' && !ignorable.test(m.text())) errors.push(m.text());
    });
    await page.addInitScript(() => {
      (window as unknown as { __cls: number }).__cls = 0;
      (window as unknown as { __clsEntries: unknown[] }).__clsEntries = [];
      new PerformanceObserver((list) => {
        for (const entry of list.getEntries() as unknown as {
          hadRecentInput: boolean;
          value: number;
          startTime: number;
          sources?: {
            node: Node | null;
            previousRect: DOMRectReadOnly;
            currentRect: DOMRectReadOnly;
          }[];
        }[]) {
          if (entry.hadRecentInput) continue;

          (window as unknown as { __cls: number }).__cls += entry.value;
          (window as unknown as { __clsEntries: unknown[] }).__clsEntries.push({
            value: entry.value,
            startTime: entry.startTime,
            viewport: {
              innerWidth: window.innerWidth,
              clientWidth: document.documentElement.clientWidth,
              innerHeight: window.innerHeight,
              documentHeight: document.documentElement.scrollHeight,
            },
            sources: (entry.sources ?? []).map((source) => {
              const element = source.node instanceof Element ? source.node : (source.node?.parentElement ?? null);
              return {
                element: element
                  ? `${element.tagName.toLowerCase()}${element.id ? `#${element.id}` : ''}${element.getAttribute('class') ? `.${element.getAttribute('class')?.trim().replace(/\s+/g, '.')}` : ''}`
                  : null,
                previousRect: source.previousRect.toJSON(),
                currentRect: source.currentRect.toJSON(),
              };
            }),
          });
        }
      }).observe({ type: 'layout-shift', buffered: true });
    });
    const response = await page.goto(path, { waitUntil: 'load' });
    expect(response?.status()).toBe(200);
    await page.waitForTimeout(600);

    const head = await page.evaluate(() => ({
      titles: document.querySelectorAll('head > title').length,
      canonicals: document.querySelectorAll('link[rel="canonical"]').length,
      ogTitles: document.querySelectorAll('meta[property="og:title"]').length,
      descriptions: document.querySelectorAll('meta[name="description"]').length,
      cls: (window as unknown as { __cls: number }).__cls,
      clsEntries: (window as unknown as { __clsEntries: unknown[] }).__clsEntries,
    }));
    await info.attach(`${plugin}-${name}.json`, { body: JSON.stringify(head), contentType: 'application/json' });
    expect(head.titles, 'one <title>').toBe(1);
    expect(head.canonicals, 'at most one canonical').toBeLessThanOrEqual(1);
    expect(head.ogTitles, 'no duplicate og:title').toBeLessThanOrEqual(1);
    expect(head.descriptions, 'no duplicate meta description').toBeLessThanOrEqual(1);
    expect(head.cls, `layout shift budget; sources: ${JSON.stringify(head.clsEntries)}`).toBeLessThanOrEqual(
      CLS_BUDGET
    );
    expect(errors, 'console errors').toEqual([]);
  });
}
