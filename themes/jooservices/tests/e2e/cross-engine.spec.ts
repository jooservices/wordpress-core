import { test, expect } from '@playwright/test';

test.skip(process.env.E2E_THEME !== 'jooservices', 'jooservices theme specs');

// Engine-sensitive features (JOOWP-204). The specs run in every project; the WebKit and Firefox projects
// (E2E_ENGINES=webkit,firefox) are the ones that matter. Fallbacks are documented in docs/browser-fallbacks.md.
const CODE = '/research-and-development/tips-and-tricks/queue-worker-listing/';

test.describe('cross-engine features', () => {
  test('the search overlay is a native dialog that opens, takes focus and closes', async ({ page }) => {
    await page.goto('/');
    const dialog = page.locator('joo-search dialog');
    await expect(dialog).toHaveCount(1);
    await expect(dialog).not.toHaveAttribute('popover', /.*/);
    await page.locator('.joo-search__link').first().click();
    await expect(dialog).toHaveJSProperty('open', true);
    await expect(dialog.locator('input[type="search"]')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(dialog).toHaveJSProperty('open', false);
  });

  test('the theme does not use the popover API', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('[popover], [popovertarget]')).toHaveCount(0);
  });

  test('the slider track scroll-snaps and a control moves one whole slide', async ({ page }) => {
    await page.goto('/');
    const track = page.locator('.joo-slider__track');
    expect(await track.evaluate((n) => getComputedStyle(n).scrollSnapType)).toMatch(/x\s+mandatory/);
    const width = await track.evaluate((n) => n.clientWidth);
    await page.locator('.joo-slider__next').click();
    await expect
      .poll(async () => track.evaluate((n) => Math.abs(n.scrollLeft - n.clientWidth) <= 1), { timeout: 5_000 })
      .toBe(true);
    expect(await track.evaluate((n) => n.clientWidth)).toBe(width);
  });

  test('color-mix and container queries resolve to real values', async ({ page }) => {
    await page.goto('/');
    const supported = await page.evaluate(() => ({
      colorMix: CSS.supports('color', 'color-mix(in srgb, red 50%, blue)'),
      container: CSS.supports('container-type', 'inline-size'),
    }));
    expect(supported).toEqual({ colorMix: true, container: true });
    const shell = page.locator('.joo-shell').first();
    expect(await shell.evaluate((n) => getComputedStyle(n).containerType)).toBe('inline-size');
  });

  test('the :has() rules are guarded and their fallback keeps the card readable', async ({ page }) => {
    await page.goto('/');
    expect(await page.evaluate(() => CSS.supports('selector(:has(*))'))).toBe(true);
    // Without :has() support the thumbless card keeps its two-column grid; the body stays a visible column.
    const card = page.locator('.joo-card').first();
    await expect(card.locator('.joo-card__body')).toBeVisible();
  });

  test('the sticky terminal dock sticks inside its flex row on desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    const term = page.locator('.joo-term').first();
    expect(await term.evaluate((n) => getComputedStyle(n).position)).toBe('sticky');
    expect(await term.evaluate((n) => getComputedStyle(n).alignSelf)).toBe('flex-start');
  });

  test('below-the-fold images are lazy and the first image is not', async ({ page }) => {
    await page.goto('/');
    const lazy = page.locator('img[loading="lazy"]');
    expect(await lazy.count()).toBeGreaterThan(0);
    expect(await page.locator('.joo-slider img').first().getAttribute('loading')).not.toBe('lazy');
    await lazy.last().scrollIntoViewIfNeeded();
    await expect.poll(async () => lazy.last().evaluate((n) => (n as HTMLImageElement).complete)).toBe(true);
  });

  test('content-visibility sections keep a reserved size and the viewport units follow the visible viewport', async ({
    page,
  }) => {
    await page.goto('/');
    const supportsCv = await page.evaluate(() => CSS.supports('content-visibility', 'auto'));
    const cv = page.locator('.joo-cv').first();
    if (supportsCv && (await cv.count())) {
      expect(await cv.evaluate((n) => getComputedStyle(n).containIntrinsicSize)).not.toBe('none');
    }
    // 100dvh must never exceed the layout viewport the engine reports.
    const dvh = await page.evaluate(() => {
      const probe = document.createElement('div');
      probe.style.cssText = 'position:fixed;height:100dvh;width:1px;visibility:hidden';
      document.body.append(probe);
      const height = probe.getBoundingClientRect().height;
      probe.remove();
      return { height, inner: window.innerHeight };
    });
    expect(dvh.height).toBeLessThanOrEqual(dvh.inner + 1);
  });

  test('speculation rules do not raise console errors or page errors', async ({ page }) => {
    const problems: string[] = [];
    page.on('pageerror', (error) => problems.push(error.message));
    page.on('console', (message) => {
      if (message.type() === 'error') problems.push(message.text());
    });
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    expect(problems.filter((text) => /speculat|prerender|prefetch/i.test(text))).toEqual([]);
  });
});

test.describe('code copy and the clipboard permission', () => {
  test('falls back to execCommand when the clipboard API is denied', async ({ page }) => {
    await page.addInitScript(() => {
      Object.defineProperty(navigator, 'clipboard', {
        configurable: true,
        value: { writeText: () => Promise.reject(new DOMException('denied', 'NotAllowedError')) },
      });
      (window as unknown as { __copied: string[] }).__copied = [];
      document.execCommand = (command: string) => {
        const area = document.querySelector('textarea');
        if (command === 'copy' && area) (window as unknown as { __copied: string[] }).__copied.push(area.value);
        return command === 'copy';
      };
    });
    await page.goto(CODE);
    const copy = page.locator('.joo-code__copy').first();
    await expect(copy).toBeVisible();
    await copy.click();
    await expect(page.locator('.joo-code__status').first()).toContainText(/copied/i);
    const copied = await page.evaluate(() => (window as unknown as { __copied: string[] }).__copied);
    expect(copied).toHaveLength(1);
    expect(copied[0].length).toBeGreaterThan(0);
  });

  test('tells the visitor to copy by hand when no copy path works', async ({ page }) => {
    await page.addInitScript(() => {
      Object.defineProperty(navigator, 'clipboard', { configurable: true, value: undefined });
      document.execCommand = () => false;
    });
    await page.goto(CODE);
    const copy = page.locator('.joo-code__copy').first();
    await copy.click();
    await expect(copy).toHaveText('Press Ctrl+C');
    await expect(page.locator('.joo-code__status').first()).toContainText(/failed/i);
  });
});
