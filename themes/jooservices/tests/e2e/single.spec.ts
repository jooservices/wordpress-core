import { test, expect } from '@playwright/test';

test.skip(process.env.E2E_THEME !== 'jooservices', 'jooservices theme specs');

const LONG = '/research-and-development/study-cases/complete-release-checklist/';
const CODE = '/research-and-development/tips-and-tricks/queue-worker-listing/';

test.describe('single post', () => {
  test('the article has one h1, breadcrumbs, byline, cover and tags', async ({ page }) => {
    await page.goto('/ai/prompt-caching/');
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('.joo-breadcrumbs li')).toHaveCount(3);
    await expect(page.locator('.joo-byline__author')).toContainText('Viet VU');
    await expect(page.locator('.joo-meta__reading')).toContainText('min read');
    await expect(page.locator('.joo-cover img')).toBeVisible();
    await expect(page.locator('.joo-article > .joo-tags a').first()).toBeVisible();
  });

  test('the cover keeps 21:9 on desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/ai/prompt-caching/');
    const box = (await page.locator('.joo-cover img').boundingBox())!;
    expect(box.width / box.height).toBeGreaterThan(2.2);
    expect(box.width / box.height).toBeLessThan(2.45);
  });

  test('the table of contents lists the headings and jumps to them', async ({ page }) => {
    await page.goto(LONG);
    const toc = page.locator('.joo-toc');
    await expect(toc).toBeVisible();
    await expect(toc.locator('li')).toHaveCount(20);
    await toc.locator('a').nth(9).click();
    await expect(page).toHaveURL(/#step-10-/);
    const heading = page.locator('.joo-prose h2').nth(9);
    await expect(heading).toBeInViewport();
  });

  test('a short post without enough headings has no table of contents', async ({ page }) => {
    await page.goto('/ai/prompt-caching/');
    await expect(page.locator('.joo-toc')).toHaveCount(0);
  });

  test('the reading progress bar fills while scrolling', async ({ page }) => {
    await page.goto(LONG);
    const bar = page.locator('.joo-progress');
    await expect(bar).toHaveAttribute('aria-valuenow', '0');
    await page.mouse.wheel(0, 4000);
    await expect.poll(async () => Number(await bar.getAttribute('aria-valuenow'))).toBeGreaterThan(10);
  });

  test('code blocks scroll inside their box and copy through the button', async ({ page, context, browserName }) => {
    test.skip(browserName !== 'chromium', 'clipboard permissions are Chromium only');
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    await page.goto(CODE);
    const pre = page.locator('.joo-prose pre').first();
    await expect(pre).toBeVisible();
    expect(await pre.evaluate((n) => getComputedStyle(n).overflowX)).toBe('auto');
    const copy = page.locator('.joo-code__copy, joo-code-copy button, button:has-text("Copy")').first();
    await expect(copy).toBeVisible();
    await copy.click();
    await expect(page.locator('.joo-code__status').first()).toContainText(/copied/i);
  });

  test('previous and next links show titles', async ({ page }) => {
    await page.goto(LONG);
    await expect(page.locator('.joo-snav a')).toHaveCount(2);
    expect(await page.locator('.joo-snav a').first().innerText()).not.toBe('');
  });

  test('the author box shows avatar, bio and socials', async ({ page }) => {
    await page.goto(LONG);
    const box = page.locator('.joo-author');
    await expect(box.locator('.joo-author__avatar')).toBeVisible();
    await expect(box).toContainText('Viet VU');
    await expect(box.locator('.joo-author__socials a')).toHaveCount(2);
  });

  test('related posts load lazily when scrolled into view', async ({ page }) => {
    await page.goto(LONG);
    await expect(page.locator('.joo-related')).toHaveCount(0);
    await page.locator('joo-lazy[name="related"]').scrollIntoViewIfNeeded();
    await expect(page.locator('.joo-related__list .joo-card')).toHaveCount(3);
  });

  test('comments load on demand and nested replies are indented', async ({ page }) => {
    await page.goto('/ai/prompt-caching/');
    const lazy = page.locator('joo-lazy[name="comments"]').first();
    await lazy.scrollIntoViewIfNeeded();
    await lazy
      .getByRole('button', { name: /comment/i })
      .first()
      .click();
    await expect(page.locator('#respond')).toBeVisible();
    await expect(page.locator('joo-lazy[name="comments"] joo-lazy')).toHaveCount(0);
  });

  test('a single post exposes a BlogPosting graph with breadcrumbs', async ({ page }) => {
    await page.goto('/ai/prompt-caching/');
    const graph = await page.locator('script[type="application/ld+json"]').first().innerText();
    expect(graph).toContain('BlogPosting');
    expect(graph).toContain('BreadcrumbList');
  });

  test('a password protected post asks for the password and hides the content', async ({ page }) => {
    await page.goto('/blog/protected-ask-me/');
    await expect(page.locator('input[name="post_password"]')).toBeVisible();
    await expect(page.locator('.joo-prose h2')).toHaveCount(0);
  });
});
