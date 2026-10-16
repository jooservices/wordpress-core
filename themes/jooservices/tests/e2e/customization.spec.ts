import { test, expect } from '@playwright/test';
import { loginAsAdmin, noHorizontalScroll } from '../../../../packages/test-kit/e2e/helpers';

test.skip(process.env.E2E_THEME !== 'jooservices', 'jooservices theme specs');

const SETTINGS = '/wp-admin/themes.php?page=joo-settings';

async function resetTab(page, tab: string) {
  await page.goto(`${SETTINGS}&tab=${tab}`);
  await page.getByRole('button', { name: 'Reset this tab to defaults' }).click();
  await page.locator('dialog button.button-primary').click();
}

test('buyer settings save, affect the front end and reset to defaults', async ({ page }) => {
  await loginAsAdmin(page);
  let completed = false;
  try {
    await resetTab(page, 'general');
    await resetTab(page, 'content');
    await resetTab(page, 'appearance');
    await resetTab(page, 'terminal');

    await page.goto(`${SETTINGS}&tab=terminal`);
    await page.locator('#joo-terminal-enabled').uncheck();
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.locator('.notice-success')).toBeVisible();
    await page.goto('/');
    await expect(page.locator('joo-terminal')).toHaveCount(0);

    await page.goto(`${SETTINGS}&tab=terminal`);
    await page.locator('#joo-terminal-enabled').check();
    await page.locator('#joo-terminal-open-by-default').check();
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.locator('.notice-success')).toBeVisible();
    for (const width of [390, 820, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto('/');
      await expect(page.locator('joo-terminal')).toHaveAttribute('data-state', 'open');
    }
    await resetTab(page, 'terminal');

    await page.goto(`${SETTINGS}&tab=general`);
    await page.locator('#joo-features-rail').uncheck();
    await page.locator('#joo-features-slider').uncheck();
    await page.locator('#joo-features-back-to-top').uncheck();
    await page.locator('#joo-features-theme-toggle').uncheck();
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.locator('.notice-success')).toBeVisible();

    await page.goto(`${SETTINGS}&tab=content`);
    await page.locator('[data-joo-key="content.rail_profile"] input[type="checkbox"]').uncheck();
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.locator('.notice-success')).toBeVisible();

    await page.goto(`${SETTINGS}&tab=appearance`);
    await page.locator('#joo-appearance-card-density').selectOption('compact');
    await page.locator('#joo-appearance-date-format').selectOption('Y-m-d');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.locator('.notice-success')).toBeVisible();

    for (const width of [390, 820, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto('/');
      await expect(page.locator('body')).toHaveClass(/joo-card-density--compact/);
      await expect(page.locator('.joo-slider')).toHaveCount(0);
      await expect(page.locator('#joo-rail')).toHaveCount(0);
      await expect(page.locator('.joo-back-to-top')).toHaveCount(0);
      await expect(page.locator('.joo-theme-toggle__button')).toHaveCount(0);
      await expect(page.locator('.joo-meta__date').first()).toHaveText(/^\d{4}-\d{2}-\d{2}$/);
      await noHorizontalScroll(page);
    }

    await page.goto(`${SETTINGS}&tab=general`);
    await page.locator('#joo-features-rail').check();
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.locator('.notice-success')).toBeVisible();

    for (const width of [390, 820, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto('/');
      if (width <= 900) await page.locator('.joo-rail-btn').click();
      await expect(page.locator('#joo-rail .joo-profile')).toHaveCount(0);
      await expect(page.locator('#joo-rail joo-lazy[name="companies"]')).toHaveCount(1);
      await noHorizontalScroll(page);
    }

    await resetTab(page, 'general');
    await resetTab(page, 'content');
    await resetTab(page, 'appearance');
    await page.goto('/');
    await expect(page.locator('body')).not.toHaveClass(/joo-card-density--compact/);
    await expect(page.locator('#joo-rail')).toBeAttached();
    await expect(page.locator('.joo-back-to-top')).toBeVisible();
    await expect(page.locator('#top')).toHaveCount(1);
    await expect(page.locator('.joo-theme-toggle__button')).toHaveCount(1);
    completed = true;
  } finally {
    if (!completed) {
      for (const tab of ['general', 'content', 'appearance', 'terminal']) {
        try {
          await resetTab(page, tab);
        } catch {
          // A crashed page cannot reset its options; each viewport starts by resetting every tab.
        }
      }
    }
  }
});
