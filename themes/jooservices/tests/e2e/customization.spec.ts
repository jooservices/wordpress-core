import { test, expect } from '@playwright/test';
import { loginAsAdmin, noHorizontalScroll } from '../../../../packages/test-kit/e2e/helpers';

test.skip(process.env.E2E_THEME !== 'jooservices', 'jooservices theme specs');

const SETTINGS = '/wp-admin/themes.php?page=joo-settings';

async function resetTab(page, tab: string) {
  await page.goto(`${SETTINGS}&tab=${tab}`);
  await page.getByRole('button', { name: 'Reset this tab to defaults' }).click();
  // The confirm button submits a form: wait for the reload before the next navigation (a goto during it fails with ERR_ABORTED).
  await Promise.all([page.waitForEvent('load'), page.locator('dialog button.button-primary').click()]);
}

/** Reusable step: the seeded owner profile (content.profile_user_id) must survive a reset of the content tab. */
const PROFILE_USER = '[name="joo_settings[content.profile_user_id]"]';
const PROFILE_LABEL = /Viet VU/; // the owner of the deterministic demo (docker/scripts/seed.php)

// A long browser journey on a shared, busy Docker host: one retry. Every run starts by resetting all tabs and ends by restoring the owner profile.
test.describe.configure({ retries: 1 });

test('buyer settings save, affect the front end and reset to defaults', async ({ page }) => {
  test.setTimeout(180_000);
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
    // The journey's page may have crashed: restore on a fresh page of the same session.
    const restore = async () => {
      const fresh = await page.context().newPage();
      try {
        await fresh.goto(`${SETTINGS}&tab=content`);
        // selectOption({ label }) takes a string, not a pattern: resolve the option by its text first.
        const owner = await fresh
          .locator(`${PROFILE_USER} option`, { hasText: PROFILE_LABEL })
          .first()
          .getAttribute('value');
        await fresh.locator(PROFILE_USER).selectOption(owner ?? '');
        await fresh.getByRole('button', { name: 'Save changes' }).click();
        await expect(fresh.locator('.notice-success')).toBeVisible();
      } finally {
        await fresh.close();
      }
    };
    if (!completed) {
      const fresh = await page.context().newPage();
      for (const tab of ['general', 'content', 'appearance', 'terminal']) {
        try {
          await resetTab(fresh, tab);
        } catch {
          // Each run starts by resetting every tab, so a failed cleanup is repaired by the next run.
        }
      }
      await fresh.close();
    }
    await restore();
  }
});
