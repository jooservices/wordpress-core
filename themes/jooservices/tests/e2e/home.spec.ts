import { test, expect } from '@playwright/test';

test.skip(process.env.E2E_THEME !== 'jooservices', 'jooservices theme specs');
// Autoplay would move the slider under the assertions.
test.use({ reducedMotion: 'reduce' });

test.describe('home', () => {
  test('the rail shows profile, companies, categories, topics and tags in order', async ({ page }) => {
    await page.goto('/');
    const rail = page.locator('#joo-rail');
    for (const name of ['companies', 'category-tree', 'top-topics', 'tag-list']) {
      await page.locator(`joo-lazy[name="${name}"]`).scrollIntoViewIfNeeded();
      await expect(page.locator(`joo-lazy[name="${name}"] .joo-lazy__placeholder`)).toHaveCount(0);
    }
    const order = await rail.evaluate((node) =>
      [
        ...node.querySelectorAll(
          '.joo-profile, .joo-companies, .joo-tree, .joo-topics, .joo-tags.wp-block-joo-tag-list'
        ),
      ].map((e) => e.className.split(' ')[0])
    );
    expect(order).toEqual(['joo-profile', 'joo-companies', 'joo-tree', 'joo-topics', 'joo-tags']);
    await expect(page.locator('.joo-profile__name')).toHaveText('Viet VU');
    await expect(page.locator('.joo-company--pinned')).toContainText('JOOservices Ltd');
  });

  test('the featured slider has five slides, working controls and an eager first image', async ({ page }) => {
    await page.goto('/');
    const slides = page.locator('.joo-slide');
    await expect(slides).toHaveCount(5);
    await expect(slides.first().locator('img')).toHaveAttribute('fetchpriority', 'high');
    await expect(slides.nth(1).locator('img')).toHaveAttribute('loading', 'lazy');
    const track = page.locator('.joo-slider__track');
    const before = await track.evaluate((n) => n.scrollLeft);
    await page.getByRole('button', { name: 'Next slide' }).click();
    await expect.poll(() => track.evaluate((n) => n.scrollLeft)).toBeGreaterThan(before);
    await page.getByRole('button', { name: 'Previous slide' }).click();
    await expect.poll(() => track.evaluate((n) => n.scrollLeft)).toBeLessThanOrEqual(before + 1);
    await page.getByRole('button', { name: 'Go to slide 3' }).click();
    await expect(page.getByRole('button', { name: 'Go to slide 3' })).toHaveAttribute('aria-current', 'true');
  });

  test('autoplay can be paused and is off when reduced motion is requested', async ({ browser }) => {
    const motion = await browser.newContext({ reducedMotion: 'no-preference' });
    const page = await motion.newPage();
    await page.goto('/');
    const pause = page.locator('.joo-slider__pause');
    const label = await pause.getAttribute('aria-label');
    await pause.click();
    await expect(pause).not.toHaveAttribute('aria-label', label ?? '');
    await motion.close();
    const calm = await browser.newContext({ reducedMotion: 'reduce' });
    const still = await calm.newPage();
    await still.goto('/');
    await still.waitForTimeout(8000);
    expect(await still.locator('.joo-slider__track').evaluate((n) => n.scrollLeft)).toBe(0);
    await calm.close();
  });

  test('featured posts are not repeated in the latest list', async ({ page }) => {
    await page.goto('/');
    const featured = await page
      .locator('.joo-slide__title a')
      .evaluateAll((a) => a.map((x) => (x as HTMLAnchorElement).href));
    const listed = await page
      .locator('.joo-card__title a')
      .evaluateAll((a) => a.map((x) => (x as HTMLAnchorElement).href));
    expect(listed.length).toBeGreaterThan(5);
    for (const href of featured) expect(listed).not.toContain(href);
  });

  test('cards show thumbnail, meta, title, excerpt, read link and tags', async ({ page }) => {
    await page.goto('/');
    const card = page.locator('.joo-card').first();
    await expect(card.locator('.joo-card__thumb img')).toBeVisible();
    await expect(card.locator('.joo-meta__category')).toBeVisible();
    await expect(card.locator('.joo-meta__date')).toBeVisible();
    await expect(card.locator('.joo-card__title a')).toBeVisible();
    await expect(card.locator('.wp-block-post-excerpt__more-link')).toContainText('Read');
    await expect(card.locator('.joo-tags a').first()).toBeVisible();
  });

  test('pagination reaches page two', async ({ page }) => {
    await page.goto('/');
    await page.locator('.joo-pager a', { hasText: '2' }).first().click();
    await expect(page).toHaveURL(/\/page\/2\/$/);
    await expect(page.locator('.joo-card').first()).toBeVisible();
  });

  test('companies paginate in place without leaving the page', async ({ page }) => {
    await page.goto('/');
    await page.locator('joo-lazy[name="companies"]').scrollIntoViewIfNeeded();
    await expect(page.locator('.joo-companies__list')).toBeVisible();
    const first = await page.locator('.joo-companies__list .joo-company__name').first().innerText();
    await page.locator('.joo-companies__pager a', { hasText: '2' }).first().click();
    await expect
      .poll(() => page.locator('.joo-companies__list .joo-company__name').first().innerText())
      .not.toBe(first);
    expect(new URL(page.url()).pathname).toBe('/');
  });

  test('the category tree toggles children', async ({ page }) => {
    await page.goto('/');
    await page.locator('joo-lazy[name="category-tree"]').scrollIntoViewIfNeeded();
    const toggle = page.locator('.joo-tree__toggle').first();
    await expect(toggle).toBeVisible();
    const expanded = await toggle.getAttribute('aria-expanded');
    await toggle.click();
    await expect(toggle).not.toHaveAttribute('aria-expanded', expanded ?? '');
  });

  test('the head has one title, description and canonical and a WebSite graph', async ({ page }) => {
    await page.goto('/');
    expect(await page.locator('head > title').count()).toBe(1);
    await expect(page.locator('meta[name="description"]')).toHaveCount(1);
    await expect(page.locator('link[rel="canonical"]')).toHaveCount(1);
    const graph = await page.locator('script[type="application/ld+json"]').first().innerText();
    expect(graph).toContain('WebSite');
  });
});
