import { test, expect, type APIRequestContext } from '@playwright/test';
import {
  collectConsoleErrors,
  noHorizontalScroll,
  openView,
  runAxe,
} from '../../../../../../packages/test-kit/e2e/helpers';

async function routePaths(request: APIRequestContext) {
  const [postsResponse, categoriesResponse, tagsResponse] = await Promise.all([
    request.get('/wp-json/wp/v2/posts?per_page=1&author_exclude=0&_fields=link'),
    request.get('/wp-json/wp/v2/categories?slug=ai&_fields=link'),
    request.get('/wp-json/wp/v2/tags?slug=mcp&_fields=link'),
  ]);
  const posts = await postsResponse.json();
  const categories = await categoriesResponse.json();
  const tags = await tagsResponse.json();

  expect(posts, 'seed at least one post before the portfolio E2E smoke').not.toHaveLength(0);
  expect(categories, 'seed the ai category before the portfolio E2E smoke').not.toHaveLength(0);
  expect(tags, 'seed the mcp tag before the portfolio E2E smoke').not.toHaveLength(0);

  return {
    home: '/',
    single: new URL(posts[0].link).pathname,
    category: new URL(categories[0].link).pathname,
    tag: new URL(tags[0].link).pathname,
    search: '/?s=portfolio',
    notFound: '/definitely-missing-portfolio-fixture/',
  };
}

test.describe('portfolio fixture smoke', () => {
  let paths: Awaited<ReturnType<typeof routePaths>>;

  test.beforeAll(async ({ request }) => {
    paths = await routePaths(request);
  });

  for (const view of ['home', 'single', 'category', 'tag', 'search', 'notFound'] as const) {
    test(`${view} renders accessibly`, async ({ page }) => {
      const errors = collectConsoleErrors(page, view === 'notFound' ? [/status of 404/] : []);
      const expectedStatus = view === 'notFound' ? 404 : 200;
      await openView(page, paths[view], expectedStatus);
      await expect(page.locator('html')).toHaveAttribute('lang', /.+/);
      await expect(page.locator('main, [role="main"]').first()).toBeVisible();
      await noHorizontalScroll(page);
      await runAxe(page);
      errors.assertNone();
    });
  }
});
