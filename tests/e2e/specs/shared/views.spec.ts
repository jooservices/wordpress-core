import { test, expect, type APIRequestContext } from '@playwright/test';
import {
  collectConsoleErrors,
  noHorizontalScroll,
  openView,
  runAxe,
  tapTargets,
} from '../../../../packages/test-kit/e2e/helpers';

// Every public view at the three viewports (the projects of playwright.config.ts): status, no console errors,
// no horizontal scroll, no serious axe violation, touch targets. Paths are discovered from the running demo.
// A theme can allow its own known third-party noise with E2E_ALLOW_CONSOLE (a regular expression).
const allow = process.env.E2E_ALLOW_CONSOLE ? [new RegExp(process.env.E2E_ALLOW_CONSOLE)] : [];

async function discover(request: APIRequestContext) {
  const home = await (await request.get('/')).text();
  const base = new URL(process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8080').origin;
  const links = [...home.matchAll(/href="([^"#?]+)"/g)]
    .map((m) => m[1])
    .filter((href) => href.startsWith(base) || href.startsWith('/'));
  const paths = [...new Set(links.map((href) => new URL(href, base).pathname))];
  const single = paths.find(
    (p) => /^\/[a-z0-9-]+\/[a-z0-9-]+\/$/.test(p) && !/^\/(category|tag|author|page|feed|comments|wp-[a-z]+)\//.test(p)
  );
  return { single: single ?? '/' };
}

test.describe('views', () => {
  let views: Record<string, { path: string; status: number }>;
  test.beforeAll(async ({ playwright }) => {
    const request = await playwright.request.newContext({
      baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8080',
    });
    const { single } = await discover(request);
    await request.dispose();
    views = {
      home: { path: '/', status: 200 },
      single: { path: single, status: 200 },
      category: { path: '/category/ai/', status: 200 },
      tag: { path: '/tag/mcp/', status: 200 },
      page: { path: '/about/', status: 200 },
      search: { path: '/?s=docker', status: 200 },
      empty: { path: '/?s=zzzzqqqq', status: 200 },
      notFound: { path: '/definitely-not-here/', status: 404 },
    };
  });

  for (const name of ['home', 'single', 'category', 'tag', 'page', 'search', 'empty', 'notFound']) {
    test(`${name} is sound`, async ({ page }) => {
      const view = views[name];
      const errors = collectConsoleErrors(page, [...allow, ...(name === 'notFound' ? [/status of 404/] : [])]);
      await openView(page, view.path, view.status);
      await expect(page.locator('html')).toHaveAttribute('lang', /.+/);
      await expect(page.locator('main, [role="main"]').first()).toBeVisible();
      await expect(page.locator('h1').first()).toBeVisible();
      await noHorizontalScroll(page);
      await runAxe(page);
      expect(await tapTargets(page, 'header, main, footer', 24), 'targets smaller than 24px').toEqual([]);
      errors.assertNone();
    });
  }
});
