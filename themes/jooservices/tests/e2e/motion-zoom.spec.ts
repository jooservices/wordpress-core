import { test, expect, type Page } from '@playwright/test';

test.skip(process.env.E2E_THEME !== 'jooservices', 'jooservices theme specs');

// These tests set their own viewport and motion preference, so the project size does not matter.
const PAGES = ['/', '/definitely-missing/'];

const noHorizontalScroll = (page: Page) =>
  page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth);

const openRailIfCollapsed = async (page: Page) => {
  const button = page.locator('.joo-rail-btn');
  if (await button.isVisible()) await button.click();
};

test.describe('reduced motion (WCAG 2.3.3, 2.2.2)', () => {
  test.use({ reducedMotion: 'no-preference' });

  test('AC1: reduce turns off autoplay, smooth scrolling, caret animation and transitions', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');
    const track = page.locator('.joo-slider__track');
    await expect(track).toBeVisible();

    // Autoplay never starts: the track stays on the first slide for longer than the 6.5 s default interval.
    await page.waitForTimeout(7500);
    expect(await track.evaluate((n) => n.scrollLeft)).toBe(0);
    await expect(page.locator('.joo-slider__pause')).toBeHidden();

    // Smooth scrolling is off everywhere it is declared.
    expect(await page.evaluate(() => getComputedStyle(document.documentElement).scrollBehavior)).toBe('auto');
    expect(await track.evaluate((n) => getComputedStyle(n).scrollBehavior)).toBe('auto');

    // Transitions are removed from the cards.
    const card = page.locator('.joo-card').first();
    await expect(card).toBeVisible();
    expect(await card.evaluate((n) => getComputedStyle(n).transitionDuration)).toMatch(/^0s(, 0s)*$/);

    // The 404 caret has no animation (a probe element carries the class, so the check does not depend on markup).
    const caret = await page.evaluate(() => {
      const probe = document.createElement('span');
      probe.className = 'joo-404__caret';
      document.body.append(probe);
      const name = getComputedStyle(probe).animationName;
      probe.remove();
      return name;
    });
    expect(caret).toBe('none');
  });

  test('AC1: reduce also stops the terminal panel animating and scrolling smoothly', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');
    await page.locator('.joo-term__tab').click();
    const styles = await page.locator('.joo-term__log').evaluate((n) => {
      const s = getComputedStyle(n);
      return { behavior: s.scrollBehavior, animation: s.animationName, transition: s.transitionDuration };
    });
    expect(styles).toEqual({ behavior: 'auto', animation: 'none', transition: '0s' });
  });

  test('AC2: with normal motion a pause control is present whenever autoplay is enabled', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.goto('/');
    await expect(page.locator('joo-slider')).toHaveAttribute('data-autoplay', '1');
    const pause = page.locator('.joo-slider__pause');
    await expect(pause).toBeVisible();
    await expect(pause).toHaveAccessibleName(/pause/i);
    await pause.click();
    await expect(pause).toHaveAccessibleName(/start/i);
  });
});

test.describe('zoom and reflow (WCAG 1.4.4, 1.4.10)', () => {
  test.use({ reducedMotion: 'reduce' });

  for (const path of PAGES) {
    test(`AC3: ${path} at 320 px (400% zoom of 1280 px) has no horizontal scroll and keeps its function`, async ({
      page,
    }) => {
      await page.setViewportSize({ width: 320, height: 256 });
      await page.goto(path);
      expect(await noHorizontalScroll(page)).toBe(true);
      await expect(page.locator('h1').first()).toBeVisible();
      // Every visible link and button stays inside the 320 px column.
      const overflowing = await page.evaluate(() =>
        [...document.querySelectorAll<HTMLElement>('main a, main button, header a, header button')]
          .filter((el) => el.getClientRects().length > 0)
          .filter((el) => el.getBoundingClientRect().right > document.documentElement.clientWidth + 1)
          .map((el) => el.className || el.tagName)
      );
      expect(overflowing).toEqual([]);
    });
  }

  test('AC3: the slider, rail and terminal stay operable at 320 px', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 256 });
    await page.goto('/');
    await page.getByRole('button', { name: 'Next slide' }).click();
    await expect(page.getByRole('button', { name: 'Go to slide 2' })).toHaveAttribute('aria-current', 'true');
    await openRailIfCollapsed(page);
    await expect(page.locator('#joo-rail')).toBeVisible();
    await page.locator('.joo-term__tab').click();
    await expect(page.locator('.joo-term__input')).toBeVisible();
    expect(await noHorizontalScroll(page)).toBe(true);
  });

  for (const path of PAGES) {
    test(`AC4: ${path} with text-only zoom of 200% keeps all content and function`, async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 800 });
      await page.goto(path);
      const before = await page.locator('body *:visible').count();
      // Text-only zoom: scale the root font size and leave the viewport alone.
      await page.addStyleTag({ content: 'html { font-size: 200% !important; }' });
      await expect
        .poll(() => page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize)))
        .toBeGreaterThanOrEqual(32);
      expect(await noHorizontalScroll(page)).toBe(true);
      expect(await page.locator('body *:visible').count()).toBeGreaterThanOrEqual(before);
      // No heading, link or button text is clipped by its own box.
      const clipped = await page.evaluate(() =>
        [...document.querySelectorAll<HTMLElement>('h1, h2, h3, main a, main button, header a, header button')]
          .filter((el) => el.getClientRects().length > 0)
          .filter((el) => {
            const style = getComputedStyle(el);
            const hides = style.overflow !== 'visible' && style.textOverflow !== 'ellipsis';
            return hides && (el.scrollWidth > el.clientWidth + 1 || el.scrollHeight > el.clientHeight + 1);
          })
          .map((el) => el.className || el.tagName)
      );
      expect(clipped).toEqual([]);
      await expect(page.locator('h1').first()).toBeVisible();
    });
  }

  test('AC4: the terminal still opens and runs a command with 200% text', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('/');
    await page.addStyleTag({ content: 'html { font-size: 200% !important; }' });
    await page.locator('.joo-term__tab').click();
    await page.locator('.joo-term__input').fill('help');
    await page.keyboard.press('Enter');
    await expect(page.locator('.joo-term__status')).toContainText('Command help.');
  });
});
