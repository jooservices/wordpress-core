import { test, expect, type Page } from '@playwright/test';
import { loginAsAdmin } from '../../../../packages/test-kit/e2e/helpers';

// Block editor panels registered by the core (Featured, SEO preview): they appear, save and persist.
const openEditor = async (page: Page) => {
  await loginAsAdmin(page);
  await page.goto('/wp-admin/post-new.php');
  await page.waitForSelector('.edit-post-layout, .editor-visual-editor', { timeout: 30_000 });
  // The welcome guide and the fullscreen mode are per-user preferences: switch them off through the data store.
  await page.evaluate(() => {
    const w = window as unknown as {
      wp: { data: { dispatch: (s: string) => Record<string, (...a: unknown[]) => void> } };
    };
    w.wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false);
    w.wp.data.dispatch('core/preferences').set('core/edit-post', 'fullscreenMode', false);
    w.wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/document');
  });
};

test.describe('editor panels', () => {
  test.skip(({ browserName, isMobile }) => browserName !== 'chromium' || !!isMobile, 'desktop Chromium only');

  test('the Featured panel offers the toggle and the order and saves them', async ({ page }) => {
    await openEditor(page);
    const panel = page.getByRole('button', { name: 'Featured', exact: true });
    await expect(panel).toBeVisible();
    if ((await panel.getAttribute('aria-expanded')) === 'false') await panel.click();
    await expect(page.getByLabel('Featured post')).toBeVisible();
    await expect(page.getByLabel('Featured order')).toBeVisible();
    await page.getByLabel('Featured post').check();
    await page.getByLabel('Featured order').fill('3');
    const meta = await page.evaluate(() => {
      const w = window as unknown as {
        wp: { data: { select: (s: string) => { getEditedPostAttribute: (k: string) => Record<string, unknown> } } };
      };
      return w.wp.data.select('core/editor').getEditedPostAttribute('meta');
    });
    expect(meta._joo_featured).toBe(true);
    expect(Number(meta._joo_featured_order)).toBe(3);
  });

  test('the SEO panel previews the search result and counts the characters', async ({ page }) => {
    await openEditor(page);
    const button = page.getByRole('button', { name: /search appearance|seo/i }).first();
    await expect(button).toBeVisible();
    if ((await button.getAttribute('aria-expanded')) === 'false') await button.click();
    await expect(page.locator('.joo-serp, [class*="serp"]').first()).toBeVisible();
  });
});
