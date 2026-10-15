import { test, expect } from '@playwright/test';
import { expectStatus } from '../../../../packages/test-kit/e2e/helpers';

// HTTP semantics that must hold for every theme built on core.
test.describe('http', () => {
  test('a real 404 is not cached and not indexed', async ({ request }) => {
    const response = await request.get('/definitely-not-here/', { maxRedirects: 0 });
    expect(response.status()).toBe(404);
    expect(response.headers()['x-robots-tag'] ?? (await response.text())).toMatch(/noindex/);
  });

  test('REST responses carry validators and honour If-None-Match', async ({ request }) => {
    const first = await request.get('/wp-json/joo/v1/categories/tree');
    expect(first.status()).toBe(200);
    const etag = first.headers()['etag'];
    expect(etag, 'ETag').toBeTruthy();
    expect(first.headers()['cache-control']).toMatch(/max-age|s-maxage/);
    const second = await request.get('/wp-json/joo/v1/categories/tree', { headers: { 'If-None-Match': etag } });
    expect(second.status()).toBe(304);
  });

  test('the HTML never leaks PHP errors', async ({ request }) => {
    for (const path of ['/', '/category/ai/', '/?s=docker', '/definitely-not-here/']) {
      const body = await (await request.get(path)).text();
      expect(body, path).not.toMatch(/Fatal error|Warning:|Notice:|Deprecated:|Stack trace/);
    }
  });

  test('draft and private posts are not public', async ({ request }) => {
    const feed = await (await request.get('/feed/')).text();
    expect(feed).not.toContain('Draft: unfinished thoughts');
    expect(feed).not.toContain('Private: only for me');
  });

  test('the companion post type is not browsable', async ({ request }) => {
    await expectStatus(request, '/?post_type=joo_company', 404);
  });

  test('the author archive does not expose the login name', async ({ request }) => {
    const response = await request.get('/?author=2', { maxRedirects: 0 });
    expect([301, 302, 404]).toContain(response.status());
    expect(response.headers()['location'] ?? '').not.toMatch(/\/author\/viet\/?$/);
  });
});
