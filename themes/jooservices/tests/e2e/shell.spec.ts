import { test, expect } from '@playwright/test';
import { noHorizontalScroll, setScheme } from '../../../../packages/test-kit/e2e/helpers';

test.skip(process.env.E2E_THEME !== 'jooservices', 'jooservices theme specs');

const WIDTHS = [320, 360, 390, 430, 768, 820, 1024, 1280, 1440, 1920, 2560, 3840];
const VIEWS = ['/', '/category/ai/', '/about/', '/?s=docker', '/definitely-not-here/'];

test.describe('shell', () => {
  test('skip link is the first focusable element and moves focus to main', async ({ page, browserName }) => {
    test.skip(browserName === 'webkit', 'Safari moves focus to links only with Option+Tab');
    await page.goto('/');
    await page.keyboard.press('Tab');
    const skip = page.locator('a.skip-link');
    await expect(skip).toBeFocused();
    await expect(skip).toBeVisible();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/#wp--skip-link--target$/);
  });

  test('landmarks exist once each', async ({ page }) => {
    await page.goto('/');
    for (const selector of ['header.joo-header', 'main', 'aside.joo-rail', 'footer.joo-footer']) {
      await expect(page.locator(selector), selector).toHaveCount(1);
    }
    await expect(page.locator('nav[aria-label]').first()).toBeAttached();
  });

  test('the layout never scrolls horizontally from 320 to 3840 px', async ({ page }) => {
    test.slow(); // 60 page loads: needs more than the default budget on a busy machine
    for (const width of WIDTHS) {
      await page.setViewportSize({ width, height: 900 });
      for (const view of VIEWS) {
        await page.goto(view, { waitUntil: 'load' });
        await noHorizontalScroll(page);
      }
    }
  });

  test('the rail is a left column on wide screens and folds behind the Sidebar button on narrow ones', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    const rail = page.locator('#joo-rail');
    await expect(rail).toBeVisible();
    const box = (await rail.boundingBox())!;
    const main = (await page.locator('main').boundingBox())!;
    expect(box.x).toBeLessThan(main.x);
    expect(Math.round(box.width)).toBeGreaterThanOrEqual(280);
    await expect(page.locator('.joo-rail-btn')).toBeHidden();

    await page.setViewportSize({ width: 600, height: 900 });
    await page.goto('/');
    const button = page.locator('.joo-rail-btn');
    await expect(button).toBeVisible();
    await expect(rail).toBeHidden();
    await expect(button).toHaveAttribute('aria-expanded', 'false');
    await button.click();
    await expect(rail).toBeVisible();
    await expect(button).toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.press('Escape');
    await expect(rail).toBeHidden();
    await expect(button).toBeFocused();
  });

  test('dark is the default scheme and the toggle persists the choice', async ({ browser }) => {
    const dark = await browser.newContext({ colorScheme: 'dark' });
    const page = await dark.newPage();
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    const bg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
    expect(bg).toBe('rgb(10, 14, 19)');
    await page.getByRole('button', { name: /toggle dark mode/i }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    expect(await page.evaluate(() => getComputedStyle(document.body).backgroundColor)).toBe('rgb(238, 242, 246)');
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await dark.close();
  });

  test('a stored preference is applied before the first paint', async ({ page }) => {
    await page.addInitScript(() => {
      (window as unknown as { __seen: string[] }).__seen = [];
      document.addEventListener('DOMContentLoaded', () => {
        (window as unknown as { __seen: string[] }).__seen.push(document.documentElement.dataset.theme ?? '');
      });
    });
    await page.goto('/');
    await setScheme(page, 'light');
    await page.reload();
    const seen = await page.evaluate(() => (window as unknown as { __seen: string[] }).__seen);
    expect(seen[0]).toBe('light');
  });

  test('the footer lists about, explore, connect and legal columns', async ({ page }) => {
    await page.goto('/');
    const footer = page.locator('footer.joo-footer');
    for (const heading of ['joo services', 'Explore', 'Connect', 'Legal']) {
      await expect(footer.getByRole('heading', { name: heading })).toBeVisible();
    }
  });
});
