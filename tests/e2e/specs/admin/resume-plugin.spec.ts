import { expect, test } from '@playwright/test';
import { loginAsAdmin } from '../../../../packages/test-kit/e2e/helpers';

test.describe('JOOservices Resume admin', () => {
  test.skip(({ browserName, isMobile }) => browserName !== 'chromium' || !!isMobile, 'desktop Chromium only');

  test('activates cleanly with the theme and loads its company and profile screens', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/wp-admin/plugins.php');

    const plugin = page.locator('tr').filter({ hasText: 'JOOservices Resume' }).first();
    await expect(plugin).toContainText('JOOservices Resume');

    const deactivate = plugin.getByRole('link', { name: 'Deactivate' });
    if (await deactivate.count()) await deactivate.click();

    await page.goto('/wp-admin/plugins.php');
    await page
      .locator('tr')
      .filter({ hasText: 'JOOservices Resume' })
      .first()
      .getByRole('link', { name: 'Activate' })
      .click();

    await page.goto('/wp-admin/plugins.php');
    await expect(page.locator('tr').filter({ hasText: 'JOOservices Resume' }).first()).toHaveClass(/active/);

    await page.goto('/wp-admin/edit.php?post_type=joo_company');
    await expect(page.getByRole('heading', { name: 'Companies' })).toBeVisible();

    await page.goto('/wp-admin/profile.php');
    await expect(page.getByRole('heading', { name: 'JOOservices Resume profile' })).toBeVisible();
  });
});
