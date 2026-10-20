import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { loginAsAdmin, openView, runAxe } from '../../../../../../packages/test-kit/e2e/helpers';

type Term = { id: number; link: string };
type Post = { id: number; link: string };
type View = { name: string; path: string };

let context: BrowserContext | undefined;
let adminPage: Page | undefined;
let postId: number | undefined;
let categoryId: number | undefined;
let tagId: number | undefined;
let views: View[] = [];

async function requestWordPress<T>(page: Page, path: string, method = 'GET', data?: unknown): Promise<T> {
  const response = await page.evaluate(
    async ({ path, method, data }) => {
      const settings = (window as Window & { wpApiSettings?: { nonce?: string } }).wpApiSettings;
      if (!settings?.nonce) throw new Error('WordPress REST nonce is unavailable.');

      const result = await fetch(`/wp-json/wp/v2/${path}`, {
        method,
        credentials: 'same-origin',
        headers: {
          'X-WP-Nonce': settings.nonce,
          ...(data ? { 'Content-Type': 'application/json' } : {}),
        },
        ...(data ? { body: JSON.stringify(data) } : {}),
      });
      return { ok: result.ok, status: result.status, body: (await result.json()) as unknown };
    },
    { path, method, data }
  );

  expect(response.ok, `WordPress REST ${method} ${path} returned ${response.status}`).toBe(true);
  return response.body as T;
}

function pathFrom(link: string): string {
  const url = new URL(link);
  return `${url.pathname}${url.search}`;
}

test.beforeAll(async ({ browser, baseURL }) => {
  // A standalone context does not inherit the project baseURL, so pass it for relative navigation.
  context = await browser.newContext({ baseURL });
  adminPage = await context.newPage();
  await loginAsAdmin(adminPage);
  await expect(adminPage.locator('#wpadminbar')).toBeVisible();
  await adminPage.goto('/wp-admin/post-new.php');

  const suffix = Date.now().toString(36);
  const slug = `magazine-e2e-${suffix}`;
  const category = await requestWordPress<Term>(adminPage, 'categories', 'POST', {
    name: `Magazine E2E ${suffix}`,
    slug: `${slug}-category`,
  });
  categoryId = category.id;

  const tag = await requestWordPress<Term>(adminPage, 'tags', 'POST', {
    name: `Magazine E2E ${suffix}`,
    slug: `${slug}-tag`,
  });
  tagId = tag.id;

  const post = await requestWordPress<Post>(adminPage, 'posts', 'POST', {
    title: `Magazine fixture ${slug}`,
    slug,
    status: 'publish',
    content: `<p>Magazine fixture content ${slug}.</p>`,
    categories: [category.id],
    tags: [tag.id],
  });
  postId = post.id;

  views = [
    { name: 'home', path: '/' },
    { name: 'single', path: pathFrom(post.link) },
    { name: 'category', path: pathFrom(category.link) },
    { name: 'tag', path: pathFrom(tag.link) },
    { name: 'search', path: `/?s=${encodeURIComponent(slug)}` },
    { name: '404', path: `/${slug}-missing/` },
  ];
});

test.afterAll(async () => {
  try {
    if (adminPage) {
      if (postId) await requestWordPress(adminPage, `posts/${postId}?force=true`, 'DELETE');
      if (categoryId) await requestWordPress(adminPage, `categories/${categoryId}?force=true`, 'DELETE');
      if (tagId) await requestWordPress(adminPage, `tags/${tagId}?force=true`, 'DELETE');
    }
  } finally {
    await context?.close();
  }
});

for (const viewName of ['home', 'single', 'category', 'tag', 'search', '404']) {
  test(`${viewName} is accessible`, async ({ page }) => {
    const view = views.find((candidate) => candidate.name === viewName);
    expect(view, `${viewName} URL was prepared`).toBeDefined();
    expect([390, 820, 1440]).toContain(page.viewportSize()?.width);

    await openView(page, view!.path, viewName === '404' ? 404 : 200);
    await expect(page.getByRole('main')).toBeVisible();
    await runAxe(page);
  });
}
