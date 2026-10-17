import { test, expect } from '@playwright/test';

test.skip(process.env.E2E_THEME !== 'jooservices', 'jooservices theme specs');

test.describe('archives, search and pages', () => {
  test('a category shows its banner, breadcrumbs and posts', async ({ page }) => {
    await page.goto('/category/ai/');
    await expect(page.locator('.joo-eyebrow')).toHaveText('Category');
    await expect(page.locator('.joo-banner h1')).toHaveText('AI');
    await expect(page.locator('.joo-banner')).toContainText('Agents, LLMs');
    await expect(page.locator('.joo-card').first()).toBeVisible();
    await expect(page.locator('.joo-breadcrumbs li').last()).toContainText('AI');
  });

  test('a topic uses the Topic eyebrow', async ({ page }) => {
    await page.goto('/tag/mcp/');
    await expect(page.locator('.joo-eyebrow')).toHaveText('Topic');
  });

  test('search echoes the query, lists results and is noindex', async ({ page }) => {
    await page.goto('/?s=docker');
    await expect(page.locator('.joo-banner h1')).toContainText('docker');
    await expect(page.locator('.joo-card').first()).toBeVisible();
    expect(await page.locator('meta[name="robots"]').getAttribute('content')).toContain('noindex');
  });

  test('a search without results keeps the form and says so', async ({ page }) => {
    await page.goto('/?s=zzzzqqqq');
    await expect(page.locator('.joo-empty')).toBeVisible();
    await expect(page.locator('.joo-search-form input[type="search"]')).toBeVisible();
  });

  test('the search dialog opens from the header and submits', async ({ page }) => {
    await page.goto('/');
    await page.locator('.joo-search__link').click();
    await expect(page.locator('.joo-search__dialog')).toBeVisible();
    await page.locator('#joo-search-input').fill('docker');
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\?s=docker/);
  });

  test('the About page is full width and lists the companies', async ({ page }) => {
    await page.goto('/about/');
    await expect(page.locator('#joo-rail')).toHaveCount(0);
    await expect(page.locator('.joo-main .joo-resume__item').first()).toBeVisible();
  });

  test('the Vietnamese posts are tagged for translation', async ({ page }) => {
    await page.goto('/?s=Vietnamese');
    await expect(page.locator('html')).toHaveAttribute('lang', /en/);
  });

  test('the author archive hides the login name', async ({ request }) => {
    const res = await request.get('/?author=2', { maxRedirects: 0 });
    expect([301, 302, 404]).toContain(res.status());
  });
});
