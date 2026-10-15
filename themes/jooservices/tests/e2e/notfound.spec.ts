import { test, expect } from '@playwright/test';

test.skip(process.env.E2E_THEME !== 'jooservices', 'jooservices theme specs');

test.describe('404', () => {
  test('is a real 404 that is not cached and not indexed', async ({ request }) => {
    const res = await request.get('/definitely-missing/');
    expect(res.status()).toBe(404);
    expect(res.headers()['x-robots-tag'] ?? '').toContain('noindex');
    expect(res.headers()['cache-control'] ?? '').toMatch(/no-store|no-cache/);
  });

  test('archives without content are 404s', async ({ request }) => {
    for (const path of ['/category/nope/', '/tag/nope/', '/author/nobody/', '/2099/01/']) {
      expect((await request.get(path, { maxRedirects: 0 })).status(), path).toBe(404);
    }
  });

  test('draft and private posts are 404 for visitors', async ({ request }) => {
    for (const slug of ['draft-unfinished-thoughts', 'private-only-for-me']) {
      expect((await request.get(`/blog/${slug}/`, { maxRedirects: 0 })).status(), slug).toBe(404);
    }
  });

  test('shows the path, the transcript, suggestions and a search form without a rail or dock', async ({ page }) => {
    await page.goto('/definitely-missing/');
    await expect(page.locator('.joo-404__title')).toContainText('404');
    await expect(page.locator('.joo-404__transcript')).toContainText('curl -i /definitely-missing/');
    await expect(page.locator('.joo-404__transcript')).toContainText('HTTP/2 404');
    await expect(page.locator('.joo-404__search input[name="s"]')).toBeVisible();
    await expect(page.locator('#joo-rail')).toHaveCount(0);
    await expect(page.locator('joo-terminal')).toHaveCount(0);
    expect(await page.locator('h1').count()).toBe(1);
    await expect(page.locator('link[rel="canonical"]')).toHaveCount(0);
  });

  test('a mistyped slug suggests the closest post first', async ({ page }) => {
    await page.goto('/ai/wordpress-mpc-v1');
    const first = page.locator('.joo-404__suggest a').first();
    await expect(first).toContainText('WordPress MCP');
  });

  test('a hostile path is escaped', async ({ page }) => {
    const errors: string[] = [];
    page.on('dialog', (d) => errors.push(d.message()));
    await page.goto('/%22%3E%3Cimg%20src=x%20onerror=alert(1)%3E');
    await expect(page.locator('.joo-404__transcript')).toContainText('curl -i');
    expect(await page.locator('.joo-404 img').count()).toBe(0);
    expect(errors).toEqual([]);
  });

  test('the retry form works with JavaScript', async ({ page }) => {
    await page.goto('/definitely-missing/');
    const retry = page.locator('.joo-404__retry');
    await expect(retry).toBeVisible();
    await retry.locator('input').fill('/ai/prompt-caching');
    await retry.getByRole('button').click();
    await expect(page.locator('.joo-404__result')).not.toHaveText('');
  });

  test('everything useful remains without JavaScript', async ({ browser }) => {
    const ctx = await browser.newContext({ javaScriptEnabled: false });
    const page = await ctx.newPage();
    await page.goto('/definitely-missing/');
    await expect(page.locator('.joo-404__transcript')).toBeVisible();
    await expect(page.locator('.joo-404__suggest a').first()).toBeVisible();
    await expect(page.locator('.joo-404__search')).toBeVisible();
    await ctx.close();
  });

  test('the CSS of the 404 does not change other pages', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('.joo-404')).toHaveCount(0);
  });
});
