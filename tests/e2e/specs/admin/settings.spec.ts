import { test, expect } from '@playwright/test';
import { loginAsAdmin, runAxe } from '../../../../packages/test-kit/e2e/helpers';

const PAGE = '/wp-admin/themes.php?page=joo-settings';
const TABS = ['general', 'seo', 'performance', 'lazy', 'content', 'terminal', 'languages', 'compatibility', 'tools'];

test.describe('settings page', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('every tab renders and its content passes axe', async ({ page }) => {
    test.slow(); // one axe scan per tab: a browser reached over a tunnel needs more than the default time
    for (const tab of TABS) {
      await page.goto(`${PAGE}&tab=${tab}`);
      await expect(page.locator('.joo-settings h1')).toBeVisible();
      await expect(page.locator('.nav-tab-active')).toHaveAttribute('aria-current', 'page');
      await runAxe(page, { include: '.joo-settings' });
    }
  });

  test('saving a value persists it and shows the saved badge', async ({ page }) => {
    await page.goto(`${PAGE}&tab=lazy`);
    await page.locator('#joo-lazy-eager-count').fill('3');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.locator('.notice-success')).toBeVisible();
    await expect(page.locator('#joo-lazy-eager-count')).toHaveValue('3');
    await expect(page.locator('[data-joo-key="lazy.eager_count"] .joo-badge--user')).toBeVisible();
  });

  test('an invalid value shows an inline error and is not saved', async ({ page }) => {
    await page.goto(`${PAGE}&tab=lazy`);
    await page.locator('#joo-lazy-eager-count').fill('99');
    // The browser's own max/min validation would block the submit; this test exercises the server validation.
    await page.evaluate(() => {
      (document.querySelector('.joo-settings__form') as HTMLFormElement).noValidate = true;
    });
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.locator('#joo-lazy-eager-count-error')).toContainText('at most');
    await expect(page.locator('#joo-lazy-eager-count')).not.toHaveValue('99');
  });

  test('reset returns the tab to defaults through a confirmation dialog', async ({ page }) => {
    await page.goto(`${PAGE}&tab=lazy`);
    await page.getByRole('button', { name: 'Reset this tab to defaults' }).click();
    await page.locator('dialog button.button-primary').click();
    await expect(page.locator('#joo-lazy-eager-count')).toHaveValue('1');
    await expect(page.locator('[data-joo-key="lazy.eager_count"] .joo-badge--user')).toHaveCount(0);
  });

  test('tools tab offers export, import and cache flush', async ({ page }) => {
    await page.goto(`${PAGE}&tab=tools`);
    await expect(page.getByRole('button', { name: 'Download JSON' })).toBeVisible();
    await expect(page.getByLabel('Settings file (JSON)')).toBeVisible();
    await page.getByRole('button', { name: 'Flush JOOservices caches' }).click();
    await expect(page.locator('.joo-settings h1')).toBeVisible();
  });
});

test('anonymous visitors are sent to the login page', async ({ page }) => {
  await page.goto(PAGE);
  await expect(page).toHaveURL(/wp-login\.php/);
});
