import { expect, test, type APIRequestContext, type Page } from '@playwright/test';
import { loginAsAdmin, noHorizontalScroll, runAxe, useRtl } from '../../../../packages/test-kit/e2e/helpers';

const MARK = 'E2E-FE';
const LONG = 'Supercalifragilistic'.repeat(12);

type Headers = Record<string, string>;

/** Reusable step: the REST nonce of the logged-in administrator. */
async function restHeaders(page: Page): Promise<Headers> {
  return { 'X-WP-Nonce': await (await page.request.get('/wp-admin/admin-ajax.php?action=rest-nonce')).text() };
}

async function createCompany(
  request: APIRequestContext,
  headers: Headers,
  name: string,
  meta: Record<string, unknown>
): Promise<void> {
  const response = await request.post('/wp-json/wp/v2/joo-companies', {
    headers,
    data: {
      title: name,
      status: 'publish',
      meta: { _joo_company_role: 'Engineer', _joo_company_from: '2018-01', ...meta },
    },
  });
  expect(response.status(), `create ${name}`).toBe(201);
}

async function createPage(
  request: APIRequestContext,
  headers: Headers,
  slug: string,
  content: string
): Promise<string> {
  const response = await request.post('/wp-json/wp/v2/pages', {
    headers,
    data: { title: `${MARK} ${slug}`, slug: `${MARK.toLowerCase()}-${slug}`, status: 'publish', content },
  });
  expect(response.status(), `page ${slug}`).toBe(201);
  return `/${MARK.toLowerCase()}-${slug}/`;
}

/** Reusable step: delete every company and page created by this spec. */
async function removeMarked(page: Page): Promise<void> {
  const headers = await restHeaders(page);
  for (const type of ['joo-companies', 'pages']) {
    const found = await page.request.get(`/wp-json/wp/v2/${type}?search=${MARK}&status=any&per_page=100&context=edit`, {
      headers,
    });
    for (const item of (await found.json()) as { id: number; title: { raw: string } }[]) {
      if (item.title.raw.startsWith(MARK))
        await page.request.delete(`/wp-json/wp/v2/${type}/${item.id}?force=true`, { headers });
    }
  }
}

test.describe('Resume front end', () => {
  test.describe.configure({ mode: 'serial' });
  let pages: { rich: string; many: string; empty: string; current: string };

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(240_000);
    const page = await browser.newPage({ baseURL: process.env.E2E_BASE_URL });
    await loginAsAdmin(page);
    await removeMarked(page);
    const headers = await restHeaders(page);
    const achievements = JSON.stringify({
      v: 1,
      items: Array.from({ length: 10 }, (_, i) => ({
        text: `Achievement ${i + 1}`,
        metric: String((i + 1) * 10),
        unit: '%',
        url: '',
      })),
    });
    const roles = JSON.stringify({
      v: 1,
      items: [
        { title: 'Senior', from: '2020-01', to: '2020-12', summary: 'Led' },
        { title: 'Junior', from: '2018-01', to: '2019-12', summary: '' },
      ],
    });
    await createCompany(page.request, headers, `${MARK} Long ${LONG}`, {
      _joo_company_to: '2020-12',
      _joo_company_summary: LONG.slice(0, 190),
      _joo_company_roles: roles,
      _joo_company_achievements: achievements,
      _joo_company_technologies: JSON.stringify(['PHP', 'Go', 'TypeScript']),
    });
    for (let i = 0; i < 5; i++) {
      await createCompany(page.request, headers, `${MARK} Company ${i}`, {
        _joo_company_from: `${2000 + i}-01`,
        _joo_company_to: `${2000 + i}-12`,
      });
    }
    pages = {
      rich: await createPage(page.request, headers, 'rich', '<!-- wp:shortcode -->[joo_resume]<!-- /wp:shortcode -->'),
      many: await createPage(page.request, headers, 'many', '[joo_resume order="date"]'),
      empty: await createPage(page.request, headers, 'empty', '[joo_resume current="yes"]'),
      current: await createPage(page.request, headers, 'current', '<!-- wp:joo/resume {"currentOnly":true} /-->'),
    };
    for (let i = 0; i < 25; i++) {
      await createCompany(page.request, headers, `${MARK} Extra ${i}`, {
        _joo_company_from: `${1970 + i}-01`,
        _joo_company_to: `${1970 + i}-12`,
      });
    }
    await page.close();
  });

  test.afterAll(async ({ browser }) => {
    test.setTimeout(240_000);
    const page = await browser.newPage({ baseURL: process.env.E2E_BASE_URL });
    await loginAsAdmin(page);
    await removeMarked(page);
    await page.close();
  });

  test('30 companies and long text: semantic list, no serious axe issue, no horizontal scroll', async ({ page }) => {
    await page.goto(pages.many);
    await expect(page.locator('.joo-resume__item').filter({ hasText: MARK })).toHaveCount(31);
    await expect(page.locator('ol.joo-resume__list')).toHaveCount(1);
    await expect(page.locator('.joo-resume time[datetime]').first()).toBeVisible();
    await runAxe(page, { include: 'main' });
    await noHorizontalScroll(page);
    await page.goto(pages.rich);
    await expect(page.locator('.joo-resume__roles li')).toHaveCount(2);
    await expect(page.locator('.joo-resume__achievements li')).toHaveCount(10);
    await expect(page.locator('.joo-resume__tags li')).toHaveCount(3);
    await runAxe(page, { include: 'main' });
    await noHorizontalScroll(page);
  });

  test('the current-only shortcode and block show only current companies', async ({ page }) => {
    // The empty state itself is covered by the integration tests: the demo always has a current company.
    for (const path of [pages.empty, pages.current]) {
      await page.goto(path);
      const items = page.locator('.joo-resume__item');
      expect(await items.count()).toBeGreaterThan(0);
      expect(await page.locator('.joo-resume__item:not(.is-current)').count()).toBe(0);
      await runAxe(page, { include: 'main' });
      await noHorizontalScroll(page);
    }
  });

  test('right to left layout does not overflow', async ({ page }) => {
    await useRtl(page);
    await page.goto(pages.rich);
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await noHorizontalScroll(page);
  });

  test('print style hides the navigation and keeps companies together', async ({ page }) => {
    await page.goto(pages.rich);
    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('.joo-resume__item').first()).toBeVisible();
    expect(
      await page
        .locator('.joo-resume__item')
        .first()
        .evaluate((el) => getComputedStyle(el).breakInside)
    ).toBe('avoid');
    await expect(page.locator('header.joo-header')).toBeHidden();
    await page.screenshot({ path: test.info().outputPath('resume-print.png'), fullPage: true });
  });

  test('REST is public, cacheable and does not leak private data', async ({ request }) => {
    const response = await request.get('/wp-json/jooservices-resume/v1/resume');
    expect(response.status()).toBe(200);
    const etag = response.headers()['etag'];
    expect(etag).toMatch(/^"[a-f0-9]{32}"$/);
    expect(response.headers()['cache-control']).toContain('public');
    expect(JSON.stringify(await response.json())).not.toContain('compensation');
    const cached = await request.get('/wp-json/jooservices-resume/v1/resume', { headers: { 'If-None-Match': etag } });
    expect(cached.status()).toBe(304);
    const work = await request.get('/wp-json/jooservices-resume/v1/resume/json-resume');
    expect(((await work.json()) as { work: unknown[] }).work.length).toBeGreaterThan(5);
  });
});
