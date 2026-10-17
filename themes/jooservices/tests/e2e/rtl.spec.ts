import { test, expect } from '@playwright/test';
import { noHorizontalScroll, runAxe, setScheme, useRtl } from '../../../../packages/test-kit/e2e/helpers';

test.skip(process.env.E2E_THEME !== 'jooservices', 'jooservices theme specs');

const WIDTHS = [320, 390, 768, 1024, 1440, 2560];
const VIEWS = ['/', '/category/ai/', '/about/', '/?s=docker', '/definitely-not-here/'];

test.describe('right to left', () => {
  test.beforeEach(async ({ page }) => {
    await useRtl(page);
  });

  test('the layout never scrolls horizontally from 320 to 2560 px', async ({ page }) => {
    test.slow(); // WIDTHS x VIEWS page loads: a browser reached over a tunnel needs more than the default time
    for (const width of WIDTHS) {
      await page.setViewportSize({ width, height: 900 });
      for (const view of VIEWS) {
        await page.goto(view, { waitUntil: 'load' });
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await noHorizontalScroll(page);
      }
    }
  });

  test('the rail moves to the right of the content and the terminal dock to the left', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    const rail = (await page.locator('#joo-rail').boundingBox())!;
    const main = (await page.locator('main').boundingBox())!;
    expect(rail.x).toBeGreaterThan(main.x);
    const dock = page.locator('.joo-term');
    if (await dock.count()) {
      const box = (await dock.first().boundingBox())!;
      expect(box.x).toBeLessThan(720);
    }
  });

  test('text and the primary navigation start on the right', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    const align = await page.locator('main').evaluate((el) => getComputedStyle(el).direction);
    expect(align).toBe('rtl');
    const logo = (await page.locator('header.joo-header a').first().boundingBox())!;
    expect(logo.x).toBeGreaterThan(720);
  });

  test('code blocks stay left to right inside right to left prose', async ({ page }) => {
    await page.goto('/ai/wordpress-mcp-v1/');
    const pre = page.locator('.joo-prose pre, .joo-prose code').first();
    if (await pre.count()) {
      expect(await pre.evaluate((el) => getComputedStyle(el).direction)).toBe('ltr');
    }
  });

  for (const scheme of ['dark', 'light'] as const) {
    test(`home passes the accessibility scan in ${scheme}`, async ({ page }) => {
      await setScheme(page, scheme);
      await page.goto('/');
      await runAxe(page);
    });
  }
});
