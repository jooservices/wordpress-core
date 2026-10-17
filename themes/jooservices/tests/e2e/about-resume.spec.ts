import { test, expect } from '@playwright/test';
import { noHorizontalScroll, runAxe } from '../../../../packages/test-kit/e2e/helpers';

test.skip(process.env.E2E_THEME !== 'jooservices', 'jooservices theme specs');

// JOOS-122: the About page renders the companies with the resume plugin's timeline (roles, achievements, technologies and
// public custom fields), and the rail card reads the newest role and the summary. The data is the deterministic demo
// (tools/wxr-generator/data/topics.json).
test.describe('About timeline and rail card (resume fields)', () => {
  test('About lists every company with roles, achievements, technologies and public custom fields', async ({
    page,
  }) => {
    await page.goto('/about/');
    const items = page.locator('.joo-main .joo-resume__item');
    await expect(items).toHaveCount(6);

    const first = items.first();
    await expect(first.locator('.joo-resume__company')).toHaveText('JOOservices Ltd');
    await expect(first.locator('.joo-resume__summary')).toContainText('Developer tooling');
    await expect(first.locator('.joo-resume__role')).toContainText('Founder');
    await expect(first.locator('.joo-resume__roles li')).toHaveCount(2);
    await expect(first.locator('.joo-resume__achievements li').first()).toContainText('marketplace review');
    await expect(first.locator('.joo-resume__tags')).toContainText('Laravel');
    await expect(first.locator('.joo-resume__custom')).toContainText('Team size');
    await expect(first.locator('.joo-resume__custom')).toContainText('AI agents and developer tooling');

    // Only the public custom fields are rendered, anywhere on the page.
    const text = await page.locator('.joo-main').innerText();
    expect(text).not.toContain('Internal note');
    expect(text).not.toContain('Not for publication');
    await expect(page.locator('.joo-main #companies')).toHaveCount(0);
  });

  test('the About timeline has no accessibility violations and no horizontal scroll', async ({ page }) => {
    await page.goto('/about/');
    await runAxe(page, { include: '.joo-resume' });
    await noHorizontalScroll(page);
  });

  test('the rail card shows the newest role and the summary of each company', async ({ page }) => {
    await page.goto('/');
    // Below 900 px the rail is a drawer behind its button.
    if ((page.viewportSize()?.width ?? 1440) <= 900) await page.locator('.joo-rail-btn').click();
    const pinned = page.locator('#joo-rail .joo-company--pinned');
    await expect(pinned.locator('.joo-company__name')).toContainText('JOOservices Ltd');
    await expect(pinned.locator('.joo-company__role')).toHaveText('Founder & CTO');
    await expect(pinned.locator('.joo-company__desc')).toContainText('Developer tooling');
    const sgcarmart = page.locator('#joo-rail .joo-company', { hasText: 'SGCarmart' });
    await expect(sgcarmart.locator('.joo-company__desc')).toContainText('Vehicle marketplace');
  });
});
