import { test, expect, type Locator, type Page } from '@playwright/test';
import { readdirSync, readFileSync, mkdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

// Renders every core block stylesheet on a fixture page in dir="rtl" (no WordPress site needed) and checks that
// direction-sensitive blocks mirror: icons point the reading way, rows do not overlap, the progress bar grows from the right.
const root = join(dirname(fileURLToPath(import.meta.url)), '../../../..');
const blocksDir = join(root, 'packages/theme-core/resources/blocks');
const evidenceDir = resolve(root, process.env.E2E_EVIDENCE_DIR ?? 'artifacts/evidence/JOOWT-279');
const blocks = readdirSync(blocksDir).sort();
const css = [
  readFileSync(join(root, 'packages/theme-core/resources/css/core.css'), 'utf8'),
  ...blocks.map((block) => readFileSync(join(blocksDir, block, 'style.css'), 'utf8')),
].join('\n');

const img =
  '<img alt="" width="64" height="64" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">';
const links = '<li><a href="#">github</a></li><li><a href="#">linkedin</a></li><li><a href="#">x</a></li>';
const arrow = (glyph: string) => `<span class="joo-arrow" aria-hidden="true">${glyph}</span>`;

// One entry per directory under blocks/: the test below fails when a block is added without a fixture.
const markup: Record<string, string> = {
  'author-box': `<aside class="joo-author">${img.replace('<img', '<img class="joo-author__avatar"')}<div class="joo-author__body">
    <p class="joo-author__name"><a href="#">أحمد علي</a> <span class="joo-author__title">مهندس برمجيات</span></p>
    <p class="joo-author__bio">نبذة قصيرة عن الكاتب.</p><ul class="joo-author__socials">${links}</ul></div></aside>`,
  breadcrumbs: `<nav class="joo-breadcrumbs joo-breadcrumbs--chevron"><ol><li><a href="#">الرئيسية</a></li><li><a href="#">المقالات</a></li><li><span>مقال</span></li></ol></nav>`,
  'category-tree': `<div class="joo-tree"><ul><li><div class="joo-tree__row"><button class="joo-tree__toggle" id="open" aria-expanded="true"></button><a href="#">فئة</a><span class="joo-tree__count">4</span></div>
    <div class="joo-tree__children"><ul><li><div class="joo-tree__row"><button class="joo-tree__toggle" id="shut" aria-expanded="false"></button><a href="#">فئة فرعية</a><span class="joo-tree__count">2</span></div></li></ul></div></li></ul></div>`,
  companies: `<section><p class="joo-company--pinned"><span class="joo-company__label">الحالية</span></p><ul class="joo-companies__list">
    <li><div class="joo-company__row"><strong class="joo-company__name">شركة</strong><span class="joo-company__range">2020 – 2024</span></div></li></ul>
    <nav class="joo-companies__pager"><a rel="prev" href="#">${arrow('←')}</a><a href="#">1</a><a href="#" aria-current="page">2</a><a rel="next" href="#">${arrow('→')}</a></nav></section>`,
  'featured-slider': `<section class="joo-slider"><div class="joo-slider__track"><article class="joo-slide"><div class="joo-slide__media">${img}</div>
    <div class="joo-slide__body"><h3 class="joo-slide__title">عنوان الشريحة</h3><p>ملخص قصير.</p></div></article></div>
    <div class="joo-slider__controls"><button class="joo-slider__prev">${arrow('←')}</button><span class="joo-slider__dots"><button class="joo-slider__dot"></button><button class="joo-slider__dot" aria-current="true"></button></span><button class="joo-slider__next">${arrow('→')}</button></div></section>`,
  'lang-switcher': `<nav class="joo-lang"><ul><li><a href="#" aria-current="true">العربية</a></li><li><a href="#">English</a></li><li><a href="#">עברית</a></li></ul></nav>`,
  'not-found': `<section><h1 class="joo-404__title">404</h1><div class="joo-404__transcript"><span class="joo-404__ps">$</span> <span class="joo-404__err">غير موجود</span></div>
    <form class="joo-404__search"><input type="search"><button type="button">بحث</button></form></section>`,
  'post-meta': `<div class="joo-meta"><time>١ يناير</time><span>٥ دقائق</span><a href="#">المؤلف</a></div>`,
  'profile-card': `<section><a class="joo-profile__who" href="#">${img.replace('<img', '<img class="joo-profile__avatar"')}<span class="joo-profile__name">أحمد علي</span><span class="joo-profile__title">مهندس</span></a>
    <p class="joo-profile__bio">نبذة قصيرة.</p><ul class="joo-profile__socials">${links}</ul><a class="joo-profile__more" href="#">عرض الملف ${arrow('→')}</a></section>`,
  'reading-progress': `<div class="joo-progress"><span class="joo-progress__bar" id="bar"></span></div>`,
  'related-posts': `<aside><h2>مقالات ذات صلة</h2><ul class="joo-related__list"><li class="joo-card"><a class="joo-card__media" href="#">${img}</a><h3 class="joo-card__title"><a href="#">مقال أول</a></h3></li>
    <li class="joo-card"><a class="joo-card__media" href="#">${img}</a><h3 class="joo-card__title"><a href="#">مقال ثان</a></h3></li></ul></aside>`,
  'search-trigger': `<div class="joo-search"><a class="joo-search__link" href="#">بحث</a>
    <dialog class="joo-search__dialog" open style="position:static;margin:0;box-sizing:border-box"><form><input type="search" placeholder="ابحث"><button type="button" class="joo-search__close">×</button></form></dialog></div>`,
  'tag-list': `<section><h2>الوسوم</h2><div class="joo-tags__items"><a href="#">وسم<sup>3</sup></a><a href="#">آخر<sup>1</sup></a></div></section>`,
  'terminal-dock': '',
  'theme-toggle': `<button class="joo-theme-toggle__button" data-color-scheme="light"><span class="joo-theme-toggle__sun">☀</span><span class="joo-theme-toggle__moon">☾</span></button>`,
  toc: `<div class="joo-toc"><nav><ol><li class="joo-toc__l2"><a href="#">مقدمة</a></li><li class="joo-toc__l3"><a href="#">تفاصيل</a></li><li class="joo-toc__l4"><a href="#">المزيد</a></li></ol></nav></div>`,
  'top-topics': `<section><ul class="joo-topics__list"><li><a class="joo-topic" href="#"><span class="joo-topic__img"><span class="joo-topic__ph">رب</span></span>
    <span class="joo-topic__text"><strong>موضوع <i>5</i></strong><span class="joo-topic__intro">مقدمة قصيرة عن الموضوع.</span></span></a></li></ul></section>`,
};

const page_ = (
  dir: 'rtl' | 'ltr',
  body: string
) => `<!doctype html><html lang="${dir === 'rtl' ? 'ar' : 'en'}" dir="${dir}"><head>
<meta charset="utf-8"><title>RTL fixture</title><style>body{margin:16px;font:16px sans-serif}${css}</style></head><body>${body}</body></html>`;

const fixture = (dir: 'rtl' | 'ltr') =>
  page_(
    dir,
    blocks
      .filter((block) => markup[block])
      .map((block) => `<div data-block="${block}" style="margin:0 0 24px">${markup[block]}</div>`)
      .join('\n')
  );

const pseudo = (loc: Locator) => loc.evaluate((el) => getComputedStyle(el, '::before').content);
const box = async (loc: Locator) => (await loc.boundingBox())!;

// `a` must sit entirely on the inline-start side (right in RTL) of `b`.
async function expectBefore(a: Locator, b: Locator) {
  const [x, y] = [await box(a), await box(b)];
  expect(x.x).toBeGreaterThanOrEqual(y.x + y.width - 0.5);
}

// Children of every flex/grid container must not overlap each other.
async function expectNoOverlap(page: Page) {
  const overlaps = await page.evaluate(() => {
    const out: string[] = [];
    for (const parent of document.querySelectorAll<HTMLElement>('body *')) {
      const display = getComputedStyle(parent).display;
      if (!/flex|grid/.test(display)) continue;
      const rects = [...parent.children]
        .filter((c) => getComputedStyle(c).display !== 'none' && getComputedStyle(c).position === 'static')
        .map((c) => ({ c, r: c.getBoundingClientRect() }))
        .filter(({ r }) => r.width > 0 && r.height > 0);
      for (let i = 0; i < rects.length; i++) {
        for (let j = i + 1; j < rects.length; j++) {
          const a = rects[i].r;
          const b = rects[j].r;
          const w = Math.min(a.right, b.right) - Math.max(a.left, b.left);
          const h = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
          if (w > 1 && h > 1)
            out.push(`${parent.className || parent.tagName}: ${rects[i].c.className} x ${rects[j].c.className}`);
        }
      }
    }
    return out;
  });
  expect(overlaps).toEqual([]);
}

const save = async (target: Page | Locator, name: string, info: { project: { name: string } }, full = false) => {
  mkdirSync(evidenceDir, { recursive: true });
  const path = join(evidenceDir, `${name}-${info.project.name}.png`);
  await (full ? (target as Page).screenshot({ path, fullPage: true }) : (target as Locator).screenshot({ path }));
};

test.describe('core blocks in RTL', () => {
  test('the fixture covers every core block', () => {
    expect(Object.keys(markup).sort()).toEqual(blocks);
  });

  test('every block mirrors, does not overlap, and leaves screenshots as evidence', async ({ page }, info) => {
    await page.setContent(fixture('rtl'));
    const block = (name: string) => page.locator(`[data-block="${name}"]`);

    // Icons point the reading way.
    expect(await pseudo(page.locator('#shut'))).toBe('"◂"');
    expect(await pseudo(page.locator('#open'))).toBe('"▾"');
    expect(await pseudo(page.locator('.joo-breadcrumbs li + li').first())).toBe('"‹"');
    for (const arrowEl of await page.locator('.joo-arrow').all()) {
      expect(await arrowEl.evaluate((el) => getComputedStyle(el).transform)).toBe('matrix(-1, 0, 0, 1, 0, 0)');
    }

    // The progress bar grows from the right.
    expect(await page.locator('#bar').evaluate((el) => getComputedStyle(el).transformOrigin.split(' ')[0])).not.toBe(
      '0px'
    );

    // Breadcrumb items run right to left.
    const crumbs = block('breadcrumbs').locator('li');
    await expectBefore(crumbs.nth(0), crumbs.nth(1));
    await expectBefore(crumbs.nth(1), crumbs.nth(2));

    // The tree count sits at the inline end (left), clear of the label; the nested row is inset from the right edge by 1rem.
    const row = page.locator('.joo-tree__row').first();
    const count = await box(row.locator('.joo-tree__count'));
    const label = await box(row.locator('a'));
    expect(count.x + count.width).toBeLessThanOrEqual(label.x + 0.5);
    const outer = await box(row);
    const inner = await box(page.locator('.joo-tree__children .joo-tree__row'));
    expect(outer.x + outer.width - (inner.x + inner.width)).toBeCloseTo(16, 0);

    // Avatars and media sit on the right, before the text.
    await expectBefore(
      block('author-box').locator('.joo-author__avatar'),
      block('author-box').locator('.joo-author__body')
    );
    await expectBefore(
      block('profile-card').locator('.joo-profile__avatar'),
      block('profile-card').locator('.joo-profile__name')
    );
    await expectBefore(block('top-topics').locator('.joo-topic__img'), block('top-topics').locator('.joo-topic__text'));

    // Previous controls sit on the right, next on the left.
    await expectBefore(
      block('featured-slider').locator('.joo-slider__prev'),
      block('featured-slider').locator('.joo-slider__next')
    );
    await expectBefore(block('companies').locator('[rel=prev]'), block('companies').locator('[rel=next]'));

    // Language items run right to left; the TOC levels indent from the right.
    await expectBefore(block('lang-switcher').locator('li').nth(0), block('lang-switcher').locator('li').nth(1));
    const l2 = await box(block('toc').locator('.joo-toc__l2 a'));
    const l4 = await box(block('toc').locator('.joo-toc__l4 a'));
    expect(l2.x + l2.width - (l4.x + l4.width)).toBeGreaterThan(16);

    await expectNoOverlap(page);
    expect(
      await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)
    ).toBe(true);

    for (const name of blocks.filter((b) => markup[b])) {
      // The reading-progress wrapper has no visible box because its content is fixed to the viewport.
      const target = name === 'reading-progress' ? block(name).locator('.joo-progress') : block(name);
      await save(target, `rtl-${name}`, info);
    }
    await save(page, 'core-blocks-rtl', info, true);
  });

  test('the terminal dock opens at the left edge in RTL', async ({ page }, info) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.setContent(
      page_(
        'rtl',
        `<joo-terminal class="joo-term" data-state="open"><button class="joo-term__tab"><span>$_</span><em>terminal</em></button>
         <div class="joo-term__panel"><div class="joo-term__bar"><span>~/site</span><button class="joo-term__close">×</button></div></div></joo-terminal>`
      )
    );
    const term = await box(page.locator('.joo-term'));
    expect(term.x).toBeLessThanOrEqual(0.5);
    const tab = await box(page.locator('.joo-term__tab'));
    const panel = await box(page.locator('.joo-term__panel'));
    // The tab keeps the screen edge and the panel opens inward (to the right), without overlap.
    expect(panel.x).toBeGreaterThanOrEqual(tab.x + tab.width - 0.5);
    // The close button sits at the inline end of the bar (left), after the path label.
    await expectBefore(page.locator('.joo-term__bar span'), page.locator('.joo-term__close'));
    await save(page, 'rtl-terminal-dock', info);
  });

  test('keeps the LTR icons in LTR', async ({ page }) => {
    await page.setContent(fixture('ltr'));
    expect(await pseudo(page.locator('#shut'))).toBe('"▸"');
    expect(await pseudo(page.locator('.joo-breadcrumbs li + li').first())).toBe('"›"');
    expect(
      await page
        .locator('.joo-arrow')
        .first()
        .evaluate((el) => getComputedStyle(el).transform)
    ).toBe('none');
  });
});
