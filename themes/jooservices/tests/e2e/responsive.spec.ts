import { expect, test, type Page } from '@playwright/test';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { loginAsAdmin } from '../../../../packages/test-kit/e2e/helpers';

test.skip(process.env.E2E_THEME !== 'jooservices', 'jooservices theme specs');

const templateDirectory = fileURLToPath(new URL('../../templates/', import.meta.url));
const templateNames = readdirSync(templateDirectory)
  .filter((name) => name.endsWith('.html'))
  .map((name) => name.slice(0, -5))
  .sort();

const widths = [320, 360, 390, 430, 540, 768, 820, 1024, 1280, 1440, 1920, 2560, 3840];
const chromiumViewports = [
  ...widths.map((width) => ({ width, height: 900 })),
  { width: 390, height: 844 },
  { width: 844, height: 390 },
  { width: 820, height: 1180 },
  { width: 1180, height: 820 },
  { width: 1024, height: 768 },
  { width: 768, height: 1024 },
];
const crossEngineViewports = [
  { width: 390, height: 844 },
  { width: 820, height: 1180 },
  { width: 1440, height: 900 },
];

const primaryControls = [
  '.joo-nav button',
  '.joo-nav .wp-block-navigation-item__content',
  '.joo-search__link',
  '.joo-search__close',
  '.joo-theme-toggle__button',
  '.joo-lang--dropdown summary',
  '.joo-rail-btn',
  '.joo-term__tab',
  '.joo-term__close',
  '.joo-term__chip',
  '.joo-term__cmd',
  '.joo-term__input',
  '.joo-slider__dot',
  '.joo-slider__prev',
  '.joo-slider__next',
  '.joo-slider__pause',
  '.joo-tree__toggle',
  '.joo-companies__pager a',
  '.joo-companies__pager button',
  '.joo-pager a',
  '.joo-search-form input[type="search"]',
  '.joo-search-form button',
  '.joo-code__copy',
  '.joo-404 input',
  '.joo-404 button',
  '.joo-back-to-top',
].join(',');

type WpPost = { author: number; date: string; link: string };
type WpPage = { id: number; link: string; template: string };
type WpTerm = { id: number; link: string; slug: string };
type WpUser = { id: number; link: string; slug: string };

async function json<T>(page: Page, path: string): Promise<T> {
  const response = await page.request.get(path);
  expect(response.ok(), `WordPress REST request ${path}`).toBeTruthy();
  return (await response.json()) as T;
}

async function createPage(page: Page, title: string, slug: string, template: string): Promise<WpPage> {
  const response = await page.request.post('/wp-json/wp/v2/pages', {
    headers: { 'X-WP-Nonce': await page.evaluate(() => (window as any).wpApiSettings?.nonce ?? '') },
    data: {
      title,
      slug,
      status: 'publish',
      template,
      content:
        '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Responsive template fixture</h1><!-- /wp:heading -->' +
        '<!-- wp:paragraph --><p>This temporary page checks the layout at narrow and wide viewport sizes.</p><!-- /wp:paragraph -->',
    },
  });
  expect(response.ok(), `create responsive fixture page (${template || 'default'})`).toBeTruthy();
  return (await response.json()) as WpPage;
}

function pathFromLink(link: string): string {
  const url = new URL(link);
  return `${url.pathname}${url.search}`;
}

async function deletePage(page: Page, id: number): Promise<void> {
  const response = await page.request.delete(`/wp-json/wp/v2/pages/${id}?force=true`, {
    headers: { 'X-WP-Nonce': await page.evaluate(() => (window as any).wpApiSettings?.nonce ?? '') },
  });
  expect(response.ok(), `delete responsive fixture page ${id}`).toBeTruthy();
}

async function templateRoutes(page: Page, temporaryPages: WpPage[]): Promise<Record<string, string>> {
  const [posts, categories, tags, users] = await Promise.all([
    json<WpPost[]>(
      page,
      '/wp-json/wp/v2/posts?per_page=1&status=publish&orderby=date&order=desc&_fields=author,date,link'
    ),
    json<WpTerm[]>(page, '/wp-json/wp/v2/categories?per_page=100&_fields=id,link,slug'),
    json<WpTerm[]>(page, '/wp-json/wp/v2/tags?per_page=100&_fields=id,link,slug'),
    json<WpUser[]>(page, '/wp-json/wp/v2/users?who=authors&per_page=100&_fields=id,link,slug'),
  ]);
  expect(posts.length, 'a published post is needed to exercise the single and archive templates').toBeGreaterThan(0);
  const post = posts[0];
  const category = categories.find((term) => term.slug === 'ai') ?? categories[0];
  const tag = tags.find((term) => term.slug === 'mcp') ?? tags[0];
  const author = users.find((user) => user.id === post.author) ?? users[0];
  expect(category, 'a category is needed to exercise the category template').toBeTruthy();
  expect(tag, 'a tag is needed to exercise the tag template').toBeTruthy();
  expect(author, 'an author is needed to exercise the author template').toBeTruthy();

  const fullPage = temporaryPages.find((item) => item.template === 'page-full')!;
  const canvasPage = temporaryPages.find((item) => item.template === 'page-canvas')!;
  const defaultPage = temporaryPages.find((item) => item.template === '')!;
  const archiveDate = post.date.slice(0, 7).replace('-', '');
  const routes: Record<string, string> = {
    '404': `/joowp-responsive-missing-${Date.now()}/`,
    archive: `/?m=${archiveDate}`,
    author: pathFromLink(author.link),
    category: pathFromLink(category.link),
    home: '/',
    // index.html and home.html are byte-identical fallback and posts-index layouts. The home route exercises the same markup.
    index: '/',
    'page-canvas': new URL(canvasPage.link).pathname,
    'page-full': new URL(fullPage.link).pathname,
    page: new URL(defaultPage.link).pathname,
    search: '/?s=responsive',
    single: new URL(post.link).pathname,
    tag: pathFromLink(tag.link),
  };
  const missing = templateNames.filter((name) => !(name in routes));
  expect(missing, 'every HTML template needs a responsive route').toEqual([]);
  return routes;
}

async function responsiveIssues(page: Page): Promise<string[]> {
  return page.evaluate(() => {
    const width = document.documentElement.clientWidth;
    const issues: string[] = [];
    const visible = (element: HTMLElement) => {
      const rect = element.getBoundingClientRect();
      const style = getComputedStyle(element);
      return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
    };
    const shortName = (element: HTMLElement) => {
      const label = element.getAttribute('aria-label') || element.textContent?.trim() || '';
      return `${element.tagName.toLowerCase()}${element.className ? `.${String(element.className).trim().replace(/\s+/g, '.')}` : ''} "${label.slice(0, 36)}"`;
    };

    if (document.documentElement.scrollWidth > width) {
      issues.push(`document scrollWidth ${document.documentElement.scrollWidth}px exceeds ${width}px`);
    }

    document.querySelectorAll<HTMLElement>('body *').forEach((element) => {
      if (!visible(element)) return;
      const rect = element.getBoundingClientRect();
      const hasHorizontalScroller = Boolean(
        element.closest('.joo-slider__track, .joo-prose pre, .joo-prose table, .joo-term__log')
      );
      if (!hasHorizontalScroller && (rect.left < -1 || rect.right > width + 1)) {
        issues.push(`${shortName(element)} extends to ${Math.round(rect.left)}..${Math.round(rect.right)}px`);
      }

      const overflowX = getComputedStyle(element).overflowX;
      const overflowY = getComputedStyle(element).overflowY;
      // These components intentionally crop decorative images, scrollable code, carousel slides, or ellipsized titles.
      const intentionalClip = element.matches(
        '.joo-slider, .joo-code, .joo-card__thumb, .joo-cover, .joo-term__title, .joo-topic__intro'
      );
      const clipsHorizontally =
        (overflowX === 'hidden' || overflowX === 'clip') && element.scrollWidth > element.clientWidth + 1;
      const clipsVertically =
        (overflowY === 'hidden' || overflowY === 'clip') && element.scrollHeight > element.clientHeight + 1;
      if (!intentionalClip && !hasHorizontalScroller && (clipsHorizontally || clipsVertically)) {
        issues.push(`${shortName(element)} clips content inside an overflow-hidden container`);
      }
    });
    return issues;
  });
}

async function assertPrimaryTargets(page: Page): Promise<void> {
  const undersized = await page.evaluate((selector) => {
    const failures: string[] = [];
    document.querySelectorAll<HTMLElement>(selector).forEach((element) => {
      const rect = element.getBoundingClientRect();
      const style = getComputedStyle(element);
      if (rect.width === 0 || rect.height === 0 || style.visibility === 'hidden' || style.display === 'none') return;
      if (rect.width < 44 || rect.height < 44) {
        failures.push(
          `${element.tagName.toLowerCase()}.${String(element.className).trim().replace(/\s+/g, '.')} ${Math.round(rect.width)}x${Math.round(rect.height)}`
        );
      }
    });
    return failures;
  }, primaryControls);
  expect(undersized, 'visible primary controls must be at least 44x44 CSS pixels').toEqual([]);
}

async function assertResponsiveLayout(page: Page, template: string): Promise<void> {
  const behavior = await page.evaluate(() => {
    const shell = document.querySelector<HTMLElement>('.joo-shell');
    const rail = document.querySelector<HTMLElement>('.joo-layout:not(.joo-layout--full) .joo-rail');
    const cards = [...document.querySelectorAll<HTMLElement>('.joo-card')];
    const dock = document.querySelector<HTMLElement>('.joo-term');
    return {
      shellWidth: shell?.getBoundingClientRect().width ?? 0,
      railDisplay: rail ? getComputedStyle(rail).display : null,
      cardColumns: cards.map((card) => getComputedStyle(card).gridTemplateColumns.split(' ').length),
      dockPosition: dock ? getComputedStyle(dock).position : null,
    };
  });
  if (template !== '404' && template !== 'page-full' && template !== 'page-canvas') {
    expect(behavior.railDisplay).toBe(behavior.shellWidth <= 900 ? 'none' : 'flex');
  }
  if (behavior.shellWidth <= 640 && behavior.cardColumns.length > 0) {
    expect(
      behavior.cardColumns.every((columns) => columns === 1),
      'post cards collapse to one column'
    ).toBeTruthy();
  }
  if (behavior.dockPosition) {
    expect(behavior.dockPosition).toBe(page.viewportSize()!.width <= 900 ? 'static' : 'sticky');
  }
}

async function assertNoScrollOrClipping(page: Page, label: string): Promise<void> {
  const issues = await responsiveIssues(page);
  expect(issues, `${label}: no horizontal scroll or clipped content`).toEqual([]);
}

async function zoomText(page: Page): Promise<void> {
  await page.evaluate(() => {
    document.querySelectorAll<HTMLElement>('body *').forEach((element) => {
      const hasText = [...element.childNodes].some(
        (node) => node.nodeType === Node.TEXT_NODE && node.textContent?.trim()
      );
      if (!hasText && !element.matches('input, textarea, select, button')) return;
      const size = Number.parseFloat(getComputedStyle(element).fontSize);
      if (Number.isFinite(size)) element.style.setProperty('font-size', `${size * 2}px`, 'important');
    });
  });
}

test('every shipped template reflows across the device matrix without clipping', async ({
  page,
  browserName,
}, testInfo) => {
  test.skip(
    browserName === 'chromium' && ['phone', 'tablet'].includes(testInfo.project.name),
    'run the Chromium matrix once'
  );
  test.setTimeout(20 * 60 * 1000);

  const viewports = browserName === 'chromium' ? chromiumViewports : crossEngineViewports;
  await loginAsAdmin(page);
  await page.goto('/wp-admin/post-new.php?post_type=page');
  const suffix = `${Date.now()}-${Math.floor(Math.random() * 10000)}`;
  const created: WpPage[] = [];

  try {
    created.push(await createPage(page, 'Responsive default fixture', `joo-responsive-default-${suffix}`, ''));
    created.push(await createPage(page, 'Responsive full width fixture', `joo-responsive-full-${suffix}`, 'page-full'));
    created.push(await createPage(page, 'Responsive canvas fixture', `joo-responsive-canvas-${suffix}`, 'page-canvas'));
    const routes = await templateRoutes(page, created);
    const homeSource = readFileSync(join(templateDirectory, 'home.html'), 'utf8');
    const indexSource = readFileSync(join(templateDirectory, 'index.html'), 'utf8');
    expect(indexSource, 'the index fallback shares the home layout').toBe(homeSource);

    for (const viewport of viewports) {
      await page.setViewportSize(viewport);
      for (const template of templateNames) {
        const route = routes[template];
        const response = await page.goto(route, { waitUntil: 'load' });
        expect(response, `${template} at ${viewport.width}x${viewport.height} returned a page`).not.toBeNull();
        expect(response!.status(), `${template} status at ${viewport.width}x${viewport.height}`).toBe(
          template === '404' ? 404 : 200
        );
        await assertNoScrollOrClipping(page, `${template} at ${viewport.width}x${viewport.height}`);
        await assertPrimaryTargets(page);
        await assertResponsiveLayout(page, template);
      }
    }

    // 200% text-only zoom is emulated by doubling the computed size of rendered text while keeping the viewport fixed.
    // It runs at 390px (typical phone) and at 320px (narrowest supported width, where wrapping is hardest).
    for (const width of [390, 320]) {
      await page.setViewportSize({ width, height: 844 });
      for (const template of templateNames) {
        await page.goto(routes[template], { waitUntil: 'load' });
        await zoomText(page);
        await assertNoScrollOrClipping(page, `${template} at 200% text-only zoom, ${width}px`);
        await assertPrimaryTargets(page);
      }
    }

    // 400% browser zoom leaves a 320 CSS px layout viewport (1280 / 4), so it is tested explicitly: every template is
    // loaded fresh at 320x256 and checked for reflow, clipping, target sizes and the rail, card and dock behaviour.
    await page.setViewportSize({ width: 320, height: 256 });
    for (const template of templateNames) {
      const response = await page.goto(routes[template], { waitUntil: 'load' });
      expect(response!.status(), `${template} status at 400% zoom`).toBe(template === '404' ? 404 : 200);
      expect(
        await page.evaluate(() => document.documentElement.clientWidth),
        `${template} 400% zoom layout viewport`
      ).toBeLessThanOrEqual(320);
      await assertNoScrollOrClipping(page, `${template} at 400% zoom (320px reflow)`);
      await assertPrimaryTargets(page);
      await assertResponsiveLayout(page, template);
    }
  } finally {
    for (const fixture of created.reverse()) await deletePage(page, fixture.id);
  }
});

test('navigation, rail and dock controls keep their targets at each breakpoint', async ({
  page,
  browserName,
}, testInfo) => {
  test.skip(['phone', 'tablet'].includes(testInfo.project.name));
  test.setTimeout(5 * 60 * 1000);

  const viewports = browserName === 'chromium' ? chromiumViewports : crossEngineViewports;
  for (const viewport of viewports) {
    await page.setViewportSize(viewport);
    await page.goto('/');
    await assertPrimaryTargets(page);

    const railButton = page.locator('.joo-rail-btn');
    if (await railButton.isVisible()) {
      await railButton.click();
      await page.locator('joo-lazy[name="category-tree"]').scrollIntoViewIfNeeded();
      await assertPrimaryTargets(page);
      await assertNoScrollOrClipping(page, `open rail at ${viewport.width}x${viewport.height}`);
    }

    const navigationOpen = page.locator('.joo-nav .wp-block-navigation__responsive-container-open');
    expect(await navigationOpen.isVisible(), `navigation breakpoint at ${viewport.width}px`).toBe(
      viewport.width <= 600
    );
    if (await navigationOpen.isVisible()) {
      await navigationOpen.click();
      await expect(page.locator('.joo-nav .wp-block-navigation__responsive-container.is-menu-open')).toBeVisible();
      await assertPrimaryTargets(page);
      await assertNoScrollOrClipping(page, `open navigation overlay at ${viewport.width}x${viewport.height}`);
    }

    const dockTab = page.locator('.joo-term__tab');
    if (await dockTab.isVisible()) {
      await dockTab.scrollIntoViewIfNeeded();
      await dockTab.click();
      await expect(page.locator('.joo-term')).toHaveAttribute('data-state', 'open');
      expect(
        await page.locator('.joo-term').evaluate((element) => getComputedStyle(element).position),
        `open dock positioning at ${viewport.width}px`
      ).toBe(viewport.width <= 900 ? 'fixed' : 'sticky');
      await assertPrimaryTargets(page);
      await assertNoScrollOrClipping(page, `open terminal dock at ${viewport.width}x${viewport.height}`);
      await page.keyboard.press('Escape');
    }
  }
});
