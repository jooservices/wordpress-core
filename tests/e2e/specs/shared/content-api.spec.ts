import { test, expect } from '@playwright/test';

// Public content routes over real HTTP against the Docker site (companion plugin active).
test.describe('content API', () => {
  for (const route of ['/companies', '/categories/tree', '/topics', '/featured', '/profile']) {
    test(`GET ${route} is public, JSON and cacheable`, async ({ request }) => {
      const res = await request.get(`/wp-json/joo/v1${route}`);
      expect(res.status()).toBe(200);
      expect(res.headers()['content-type']).toMatch(/application\/json/);
      expect(res.headers()['cache-control']).toBe('public, max-age=300, stale-while-revalidate=600');
      const etag = res.headers().etag;
      expect(etag).toMatch(/^W\//);
      const again = await request.get(`/wp-json/joo/v1${route}`, { headers: { 'If-None-Match': etag } });
      expect(again.status()).toBe(304);
    });
  }

  test('companies expose pagination headers', async ({ request }) => {
    const res = await request.get('/wp-json/joo/v1/companies?per_page=1');
    expect(res.headers()['x-wp-total']).toBeDefined();
    expect(res.headers()['x-wp-totalpages']).toBeDefined();
  });

  test('invalid arguments are rejected with 400', async ({ request }) => {
    for (const url of ['/companies?per_page=99', '/topics?limit=99', '/featured?limit=13', '/topics?strategy=random']) {
      expect((await request.get(`/wp-json/joo/v1${url}`)).status(), url).toBe(400);
    }
  });

  test('the companion post type is not public and users do not leak email', async ({ request }) => {
    expect((await request.get('/?post_type=joo_company')).status()).toBe(404);
    const users = await request.get('/wp-json/wp/v2/users');
    expect(await users.text()).not.toMatch(/@/);
  });
});
