import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { AXE_TAGS, openView } from '../../../../packages/test-kit/e2e/helpers';

type Block = {
  name: string;
  selector: string;
  fragment?: string;
};

const homeBlocks: Block[] = [
  { name: 'profile card', selector: '.wp-block-joo-profile-card' },
  { name: 'companies', selector: '.wp-block-joo-companies', fragment: 'companies' },
  { name: 'category tree', selector: '.wp-block-joo-category-tree', fragment: 'category-tree' },
  { name: 'top topics', selector: '.wp-block-joo-top-topics', fragment: 'top-topics' },
  { name: 'tag list', selector: '.wp-block-joo-tag-list', fragment: 'tag-list' },
  { name: 'slider', selector: '.wp-block-joo-featured-slider' },
  { name: 'search trigger', selector: '.wp-block-joo-search-trigger' },
  { name: 'theme toggle', selector: '.wp-block-joo-theme-toggle' },
];

const articleBlocks: Block[] = [
  { name: 'breadcrumbs', selector: '.wp-block-joo-breadcrumbs' },
  { name: 'table of contents', selector: '.wp-block-joo-toc' },
  { name: 'reading progress', selector: '.wp-block-joo-reading-progress' },
  { name: 'author box', selector: '.wp-block-joo-author-box' },
  { name: 'related posts', selector: '.wp-block-joo-related-posts' },
  { name: 'post meta', selector: '.wp-block-joo-post-meta' },
];

let articlePath = '';

async function revealBlock(page: Page, block: Block): Promise<void> {
  if (block.fragment) {
    const fragment = page.locator(`joo-lazy[name="${block.fragment}"]`).first();
    await expect(fragment, `${block.name} fragment is present`).toBeAttached();
    await fragment.scrollIntoViewIfNeeded();
  }

  const root = page.locator(block.selector).first();
  await expect(root, `${block.name} renders`).toBeVisible();
  await root.scrollIntoViewIfNeeded();
}

async function openRail(page: Page): Promise<void> {
  const rail = page.locator('#joo-rail');
  if (await rail.isVisible()) return;

  const button = page.locator('.joo-rail-btn');
  await expect(button, 'the responsive sidebar has a keyboard control').toBeVisible();
  await button.focus();
  await page.keyboard.press('Enter');
  await expect(rail).toHaveAttribute('data-open', 'true');
  await expect(rail).toBeVisible();
}

async function auditBlocks(page: Page, blocks: Block[]): Promise<void> {
  const width = page.viewportSize()?.width ?? 0;

  for (const block of blocks) {
    await revealBlock(page, block);
    const results = await new AxeBuilder({ page }).include(block.selector).withTags(AXE_TAGS).analyze();
    const violations = results.violations;
    const summary = violations.length
      ? violations
          .map(
            (violation) =>
              `${violation.impact ?? 'unknown'} ${violation.id} (${violation.nodes
                .map((node) => node.target.join(' '))
                .join(', ')})`
          )
          .join('; ')
      : 'no violations';
    console.info(`[axe ${width}px] ${block.name}: ${summary}`);

    expect(
      violations.map((violation) => `${violation.impact ?? 'unknown'} ${violation.id}: ${violation.help}`),
      `${block.name} has no axe findings at ${width}px`
    ).toEqual([]);
  }
}

async function walkKeyboard(page: Page, blocks: Block[]): Promise<void> {
  const targets = await page.evaluate(
    (specs) => {
      const found: Array<{ id: string; block: string }> = [];
      let index = 0;

      for (const spec of specs) {
        const roots = document.querySelectorAll(spec.selector);
        roots.forEach((root) => {
          root
            .querySelectorAll<HTMLElement>('a[href], button, input, select, textarea, [tabindex]')
            .forEach((element) => {
              if (
                element.tabIndex < 0 ||
                element.matches(':disabled') ||
                element.closest('[hidden], [inert], [aria-hidden="true"]') ||
                element.getClientRects().length === 0
              ) {
                return;
              }

              const id = String(index++);
              element.setAttribute('data-core-a11y-target', id);
              found.push({ id, block: spec.name });
            });
        });
      }

      return found;
    },
    blocks.map(({ name, selector }) => ({ name, selector }))
  );
  const tabStops = await page.evaluate(
    () =>
      Array.from(document.querySelectorAll<HTMLElement>('a[href], button, input, select, textarea, [tabindex]')).filter(
        (element) =>
          element.tabIndex >= 0 &&
          !element.matches(':disabled') &&
          !element.closest('[hidden], [inert], [aria-hidden="true"]') &&
          element.getClientRects().length > 0
      ).length
  );

  const reached = new Set<string>();
  const missingFocusIndicators = new Set<string>();

  await page.evaluate(() => {
    const active = document.activeElement;
    if (active instanceof HTMLElement && active !== document.body) active.blur();
  });

  for (let tab = 0; tab < Math.min(500, tabStops + 5); tab++) {
    await page.keyboard.press('Tab');
    const focus = await page.evaluate(() => {
      const active = document.activeElement;
      const id = active?.getAttribute('data-core-a11y-target');
      if (id === null || id === undefined) return null;

      const style = window.getComputedStyle(active);
      const visibleOutline = style.outlineStyle !== 'none' && Number.parseFloat(style.outlineWidth) > 0;
      const visibleShadow = style.boxShadow !== 'none';
      return { id, hasIndicator: visibleOutline || visibleShadow };
    });

    if (focus) {
      reached.add(focus.id);
      if (!focus.hasIndicator) {
        const target = targets.find((item) => item.id === focus.id);
        if (target) missingFocusIndicators.add(target.block);
      }
    }

    if (reached.size === targets.length) break;
  }

  const missing = targets.filter((target) => !reached.has(target.id));
  console.info(
    `[keyboard ${page.viewportSize()?.width ?? 0}px] ${reached.size}/${targets.length} block controls reached; ` +
      `${missingFocusIndicators.size} block types without a visible focus indicator`
  );
  expect(
    missing.map((target) => target.block),
    'block controls reachable with Tab'
  ).toEqual([]);
  expect([...missingFocusIndicators], 'block controls show a visible keyboard focus indicator').toEqual([]);
}

test.describe('theme-core block accessibility', () => {
  test.beforeAll(async ({ playwright }) => {
    const request = await playwright.request.newContext({
      baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8082',
    });
    try {
      const response = await request.get('/wp-json/wp/v2/posts?per_page=50&_fields=link,slug,content');
      expect(response.ok(), 'seeded posts are available for the single template').toBeTruthy();
      const posts: Array<{ link: string; slug: string; content: { rendered: string } }> = await response.json();
      const tocPost = posts.find(
        (post) =>
          post.slug === 'complete-release-checklist' && (post.content.rendered.match(/<h[2-4]\b/gi) ?? []).length >= 3
      );
      expect(tocPost, 'the seeded TOC fixture has at least three headings').toBeTruthy();
      articlePath = new URL(tocPost!.link).pathname;
    } finally {
      await request.dispose();
    }
  });

  test('home blocks pass axe and a keyboard walkthrough', async ({ page }) => {
    test.slow();
    await openView(page, '/');
    await openRail(page);
    await auditBlocks(page, homeBlocks);
    await walkKeyboard(page, homeBlocks);
  });

  test('article blocks pass axe and a keyboard walkthrough', async ({ page }) => {
    test.slow();
    await openView(page, articlePath);
    await auditBlocks(page, articleBlocks);
    await walkKeyboard(page, articleBlocks);
  });

  test('home controls respond to keyboard activation', async ({ page }) => {
    test.slow();
    await openView(page, '/');
    await openRail(page);
    for (const block of homeBlocks) await revealBlock(page, block);

    const searchLink = page.locator('.wp-block-joo-search-trigger .joo-search__link').first();
    const searchDialog = page.locator('.wp-block-joo-search-trigger dialog').first();
    await searchLink.focus();
    await page.keyboard.press('Enter');
    await expect(searchDialog).toBeVisible();
    await expect(page.locator('#joo-search-input')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(searchDialog).not.toBeVisible();
    await expect(searchLink).toBeFocused();
    await openRail(page);

    const theme = page.locator('.wp-block-joo-theme-toggle').first();
    const themeButton = theme.locator('button');
    const schemes = (await theme.getAttribute('data-schemes'))?.split(',') ?? [];
    const beforeTheme = await page.locator('html').getAttribute('data-theme');
    await themeButton.focus();
    await page.keyboard.press('Space');
    if (schemes.length > 1) {
      await expect.poll(() => page.locator('html').getAttribute('data-theme')).not.toBe(beforeTheme);
    }

    const slider = page.locator('.wp-block-joo-featured-slider');
    const track = slider.locator('.joo-slider__track');
    const dots = slider.locator('.joo-slider__dot');
    const current = await dots.evaluateAll((items) =>
      items.findIndex((item) => item.getAttribute('aria-current') === 'true')
    );
    await track.focus();
    await page.keyboard.press('ArrowRight');
    await expect(dots.nth((current + 1) % (await dots.count()))).toHaveAttribute('aria-current', 'true');

    const companies = page.locator('.wp-block-joo-companies joo-companies').first();
    const nextPage = companies.locator('.joo-companies__pager a[rel="next"]');
    await expect(nextPage, 'companies pager has a next page in the seeded fixture').toBeVisible();
    await nextPage.focus();
    await page.keyboard.press('Enter');
    await expect(companies).toHaveAttribute('data-page', '2');
    await expect(companies.locator('[role="status"]')).toContainText('Page 2');

    const treeToggle = page.locator('.wp-block-joo-category-tree .joo-tree__toggle:visible').first();
    await expect(treeToggle, 'category tree has a keyboard toggle in the seeded fixture').toBeVisible();
    const expanded = await treeToggle.getAttribute('aria-expanded');
    await treeToggle.focus();
    await page.keyboard.press('Space');
    await expect(treeToggle).toHaveAttribute('aria-expanded', expanded === 'true' ? 'false' : 'true');
  });

  test('table of contents works with the keyboard', async ({ page }) => {
    await openView(page, articlePath);
    const toc = page.locator('.wp-block-joo-toc').first();
    await expect(toc).toBeVisible();

    const summary = toc.locator('summary');
    const wasOpen = await toc.evaluate((element) => (element as HTMLDetailsElement).open);
    await summary.focus();
    await page.keyboard.press('Space');
    await expect.poll(() => toc.evaluate((element) => (element as HTMLDetailsElement).open)).toBe(!wasOpen);
    if (wasOpen) {
      await page.keyboard.press('Space');
      await expect(toc).toHaveAttribute('open', '');
    }

    const link = toc.locator('a[href^="#"]').first();
    const href = await link.getAttribute('href');
    await link.focus();
    await page.keyboard.press('Enter');
    await expect.poll(() => new URL(page.url()).hash).toBe(href);
  });
});
