import { expect, test, type Page } from '@playwright/test';
import { loginAsAdmin, noHorizontalScroll, runAxe } from '../../../../packages/test-kit/e2e/helpers';

const LIST = '/wp-admin/edit.php?post_type=joo_company';
const NEW = '/wp-admin/post-new.php?post_type=joo_company';
const SETTINGS = `${LIST}&page=joo-resume-settings`;
const TRANSFER = `${LIST}&page=joo-resume-transfer`;
const MARK = 'E2E-RESUME';

/** Reusable step: type into the field that currently has focus, then move on with Tab (keyboard only). */
async function typeThenTab(page: Page, ...values: string[]): Promise<void> {
  for (const value of values) {
    await page.keyboard.type(value);
    await page.keyboard.press('Tab');
  }
}

/** Reusable step: activate a button with the keyboard. */
async function pressButton(page: Page, name: string, nth = 0): Promise<void> {
  const button = page.getByRole('button', { name, exact: true }).nth(nth);
  await button.focus();
  await page.keyboard.press('Enter');
}

/** Reusable step: delete every company created by these specs (REST with the logged-in cookie and nonce). */
async function removeMarked(page: Page): Promise<void> {
  const nonce = await (await page.request.get('/wp-admin/admin-ajax.php?action=rest-nonce')).text();
  const headers = { 'X-WP-Nonce': nonce };
  const found = await page.request.get(
    `/wp-json/wp/v2/joo-companies?search=${MARK}&status=any&per_page=100&context=edit`,
    {
      headers,
    }
  );
  const companies = (await found.json()) as { id: number; title: { raw: string } }[];
  for (const company of companies) {
    if (company.title.raw.startsWith(MARK)) {
      await page.request.delete(`/wp-json/wp/v2/joo-companies/${company.id}?force=true`, { headers });
    }
  }
}

/** Reusable step: remove every custom-field definition and save. */
async function clearDefinitions(page: Page): Promise<void> {
  await page.goto(SETTINGS);
  const rows = page.locator('[data-joo-repeater="joo_def"] [data-joo-row]');
  while ((await rows.count()) > 0) await rows.first().getByRole('button', { name: 'Remove field' }).click();
  await page.getByRole('button', { name: 'Save settings' }).click();
  await expect(page.locator('.notice-success')).toBeVisible();
}

test.describe('Resume admin screens', () => {
  test.describe.configure({ mode: 'serial' });

  test.describe('every viewport', () => {
    test.beforeEach(async ({ page }) => {
      await loginAsAdmin(page);
    });

    for (const [label, path] of [
      ['company list', LIST],
      ['company editor', NEW],
      ['settings', SETTINGS],
      ['import and export', TRANSFER],
    ] as const) {
      test(`${label}: no serious axe issue and no horizontal scroll`, async ({ page }) => {
        await page.goto(path);
        await expect(page.locator('h1').first()).toBeVisible();
        await runAxe(page, { include: '#wpbody-content' });
        await noHorizontalScroll(page);
      });
    }
  });

  test.describe('desktop flow', () => {
    test.skip(
      ({ browserName, viewport }) => browserName !== 'chromium' || (viewport?.width ?? 0) < 1200,
      'desktop Chromium only'
    );

    // A crashed earlier run may have left test companies behind: start clean, and clean up after.
    test.beforeAll(async ({ browser }, testInfo) => {
      if (testInfo.project.name !== 'desktop') return;
      testInfo.setTimeout(180_000);
      const page = await browser.newPage({ baseURL: process.env.E2E_BASE_URL });
      await loginAsAdmin(page);
      await removeMarked(page);
      await clearDefinitions(page);
      await page.close();
    });

    test.afterAll(async ({ browser }, testInfo) => {
      if (testInfo.project.name !== 'desktop') return;
      testInfo.setTimeout(180_000);
      const page = await browser.newPage({ baseURL: process.env.E2E_BASE_URL });
      await loginAsAdmin(page);
      await removeMarked(page);
      await clearDefinitions(page);
      await page.close();
    });

    test.beforeEach(async ({ page }) => {
      await loginAsAdmin(page);
    });

    test('a custom field is defined in the settings and appears on the company screen', async ({ page }) => {
      await clearDefinitions(page);
      await page.goto(SETTINGS);
      await pressButton(page, 'Add field');
      await expect(page.locator('[data-joo-live]').first()).toContainText('field 1 added');
      await typeThenTab(page, `${MARK} Team size`, 'e2e_team');
      await page.getByLabel('Type').first().selectOption('number');
      await page.getByRole('button', { name: 'Save settings' }).click();
      await expect(page.locator('.notice-success')).toBeVisible();
      await expect(page.getByRole('cell', { name: `${MARK} Team size` })).toBeVisible();

      await page.goto(NEW);
      await expect(page.getByLabel(`${MARK} Team size (private)`)).toBeVisible();

      await page.goto(SETTINGS);
      await page.locator('#joo_def_0_key').fill('Bad Key');
      // The browser would stop this on the pattern attribute: check the server side rule too.
      await page
        .locator('form[action*="admin-post"]')
        .evaluate((form) => ((form as HTMLFormElement).noValidate = true));
      await page.getByRole('button', { name: 'Save settings' }).click();
      await expect(page.locator('.notice-error')).toContainText('lowercase letters');
    });

    test('a company is entered with the keyboard, reordered, validated and saved', async ({ page }) => {
      await page.goto(NEW);
      await page.locator('#title').fill(`${MARK} Acme`);
      await page.locator('#_joo_company_role').fill('CTO');
      await page.locator('#_joo_company_from').fill('2020-01');
      await page.locator('#joo_city').fill('Hanoi');
      await page.locator('#joo_technologies').fill('PHP, Go, PHP');

      await pressButton(page, 'Add role');
      await typeThenTab(page, 'Engineer', '2020-01', '2021-12');
      await pressButton(page, 'Add role');
      await typeThenTab(page, 'Lead', '2022-01');
      await expect(page.locator('[data-joo-repeater="joo_roles"] [data-joo-live]')).toContainText('role 2 added');

      await pressButton(page, 'Move role up', 1);
      await expect(page.locator('[name="joo_roles[0][title]"]')).toHaveValue('Lead');
      await expect(page.locator('[data-joo-repeater="joo_roles"] [data-joo-live]')).toContainText(
        'moved to position 1 of 2'
      );

      await pressButton(page, 'Add achievement');
      await typeThenTab(page, 'Cut infrastructure cost', '40', '%');

      await page.locator('#publish').click();
      await expect(page.locator('#message, .notice-success').first()).toBeVisible();
      await expect(page.locator('[name="joo_roles[0][title]"]')).toHaveValue('Lead');
      await expect(page.locator('[name="joo_roles[1][title]"]')).toHaveValue('Engineer');
      await expect(page.locator('#joo_technologies')).toHaveValue('PHP, Go');
      await expect(page.locator('[name="joo_achievements[0][metric]"]')).toHaveValue('40');
      await expect(page.locator('#joo_city')).toHaveValue('Hanoi');

      await pressButton(page, 'Add role');
      await typeThenTab(page, 'Early', '2018-01', '2018-12');
      await page.locator('#publish').click();
      await expect(page.locator('.notice-warning')).toContainText('must lie inside the period at the company');
      await expect(page.locator('[name="joo_roles[1][title]"]')).toHaveValue('Engineer');
      await expect(page.locator('[data-joo-repeater="joo_roles"] [data-joo-row]')).toHaveCount(2);

      await page.goto(`${LIST}&s=${MARK}`);
      await expect(page.locator('.column-title', { hasText: `${MARK} Acme` })).toBeVisible();
      await expect(page.locator('td.column-current').first()).toHaveText('Yes');
    });

    test('export downloads each format, import checks first and creates nothing from an invalid file', async ({
      page,
    }) => {
      await page.goto(TRANSFER);
      const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.getByRole('link', { name: 'JOOservices Resume (JSON)' }).click(),
      ]);
      const own = JSON.parse(await (await import('node:fs/promises')).readFile((await download.path())!, 'utf8'));
      expect(own.format).toBe('jooservices-resume');
      expect(JSON.stringify(own.companies)).toContain(`${MARK} Acme`);

      const invalid = {
        work: [
          { name: `${MARK} Import A`, position: 'Dev', startDate: '2020-01' },
          { name: '', startDate: 'nope' },
        ],
      };
      await page.locator('#joo_import_file').setInputFiles({
        name: 'bad.json',
        mimeType: 'application/json',
        buffer: Buffer.from(JSON.stringify(invalid)),
      });
      await page.getByRole('button', { name: 'Check the file' }).click();
      await expect(page.getByText('1 company has errors')).toBeVisible();
      await expect(page.getByRole('button', { name: /^Import \d+ compan/ })).toHaveCount(0);

      const valid = {
        work: [
          {
            name: `${MARK} Import A`,
            position: 'Dev',
            startDate: '2020-01',
            endDate: '2021-01',
            highlights: ['Did A'],
          },
          { name: `${MARK} Import B`, position: 'Lead', startDate: '2021-02' },
        ],
      };
      await page
        .locator('#joo_import_file')
        .setInputFiles({ name: 'good.json', mimeType: 'application/json', buffer: Buffer.from(JSON.stringify(valid)) });
      await page.getByRole('button', { name: 'Check the file' }).click();
      await runAxe(page, { include: '#wpbody-content' });
      await page.getByRole('button', { name: 'Import 2 companies' }).click();
      await expect(page.locator('.notice-success')).toContainText('2 companies imported');
      await page.goto(`${LIST}&s=${MARK}+Import`);
      await expect(page.locator('.column-title', { hasText: `${MARK} Import B` })).toBeVisible();
      await expect(page.locator('.column-title', { hasText: `${MARK} Import A` })).toBeVisible();
    });

    test('the list filters current companies and a visitor cannot open the screens', async ({ page, browser }) => {
      await page.goto(`${LIST}&s=${MARK}&joo_filter=past`);
      await expect(page.locator('.column-title', { hasText: `${MARK} Import A` })).toBeVisible();
      await expect(page.locator('.column-title', { hasText: `${MARK} Import B` })).toHaveCount(0);
      const guest = await browser.newPage({ baseURL: process.env.E2E_BASE_URL });
      await guest.goto(SETTINGS);
      await expect(guest).toHaveURL(/wp-login\.php/);
      await guest.goto(TRANSFER);
      await expect(guest).toHaveURL(/wp-login\.php/);
      await guest.close();
    });
  });
});
