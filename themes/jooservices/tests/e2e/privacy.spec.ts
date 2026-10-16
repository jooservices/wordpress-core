import { test, expect } from '@playwright/test';

test.skip(process.env.E2E_THEME !== 'jooservices', 'jooservices theme specs');

test('public page makes no third-party requests or sets tracking cookies', async ({ page, context }) => {
  const siteOrigin = new URL(process.env.E2E_BASE_URL ?? 'http://wordpress').origin;
  const externalRequests: string[] = [];
  page.on('request', (request) => {
    try {
      const origin = new URL(request.url()).origin;
      if (origin !== siteOrigin) externalRequests.push(request.url());
    } catch {
      // Browser-internal and data URLs are not network requests to third parties.
    }
  });

  await page.goto('/');
  await expect(page.locator('body')).toBeVisible();
  await page.waitForLoadState('networkidle');

  expect(externalRequests).toEqual([]);
  expect(await context.cookies()).toEqual([]);
  const storageKeys = await page.evaluate(() => Object.keys(localStorage));
  expect(storageKeys.filter((key) => /analytics|tracking|pixel|^_ga/i.test(key))).toEqual([]);
});
