import { test, expect, type Locator, type Page } from '@playwright/test';
import { runAxe } from '../../../../packages/test-kit/e2e/helpers';

test.skip(process.env.E2E_THEME !== 'jooservices', 'jooservices theme specs');

const open = async (page: Page) => {
  await page.goto('/');
  await page.locator('.joo-term__tab').click();
  await expect(page.locator('.joo-term__panel')).toBeVisible();
};
const run = async (page: Page, command: string) => {
  await page.locator('.joo-term__input').fill(command);
  await page.keyboard.press('Enter');
};

const tabTo = async (page: Page, target: Locator) => {
  for (let index = 0; index < 200; index += 1) {
    await page.keyboard.press('Tab');
    if (await target.evaluate((element) => element === document.activeElement)) return;
  }
  throw new Error('Could not reach the requested control with Tab.');
};

test.describe('terminal dock', () => {
  test('keyboard flow opens, announces output, leaves by Tab, and closes with focus restored', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');
    const dock = page.locator('joo-terminal');
    const trigger = page.locator('.joo-term__tab');
    await tabTo(page, trigger);
    await page.keyboard.press('Enter');
    const input = page.locator('.joo-term__input');
    await expect(input).toBeFocused();
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('.joo-term__log')).toHaveAttribute('aria-live', 'off');
    await expect(page.locator('.joo-term__status')).toHaveAttribute('aria-live', 'polite');
    expect(await page.locator('.joo-term__log').evaluate((node) => getComputedStyle(node).scrollBehavior)).toBe('auto');
    await runAxe(page, { include: '.joo-term' });

    await page.keyboard.type('help');
    await page.keyboard.press('Enter');
    await expect(page.locator('.joo-term__status')).toContainText('Command help.');
    await expect(page.locator('.joo-term__status')).toContainText('terminal log');

    await page.keyboard.press('Tab');
    expect(await dock.evaluate((node) => node.contains(document.activeElement))).toBe(false);
    await page.keyboard.press('Shift+Tab');
    await expect(input).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(dock).toHaveAttribute('data-state', 'closed');
    await expect(trigger).toBeFocused();
  });

  test('desktop: a sticky right column that opens, takes focus and closes with Escape', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    const dock = page.locator('joo-terminal');
    await expect(dock).toHaveAttribute('data-state', 'closed');
    const frame = (await page.locator('.joo-frame').boundingBox())!;
    const closed = (await dock.boundingBox())!;
    expect(closed.x).toBeGreaterThan(frame.x + frame.width / 2);
    expect(await dock.evaluate((n) => getComputedStyle(n).position)).toBe('sticky');
    await page.locator('.joo-term__tab').click();
    await expect(dock).toHaveAttribute('data-state', 'open');
    await expect(page.locator('.joo-term__input')).toBeFocused();
    const panel = (await page.locator('.joo-term__panel').boundingBox())!;
    expect(Math.round(panel.width)).toBe(390);
    const shell = (await page.locator('.joo-shell').boundingBox())!;
    expect(shell.x + shell.width).toBeLessThanOrEqual(panel.x + 1); // content reflows, the dock does not cover it
    await page.keyboard.press('Escape');
    await expect(dock).toHaveAttribute('data-state', 'closed');
    await expect(page.locator('.joo-term__tab')).toBeFocused();
  });

  test('the backtick key toggles the dock when no field has focus', async ({ page }) => {
    await page.goto('/');
    await page.keyboard.press('`');
    await expect(page.locator('joo-terminal')).toHaveAttribute('data-state', 'open');
  });

  test('ls, cat, tree and help work and the shell is read only', async ({ page }) => {
    await open(page);
    await run(page, 'ls');
    await expect(page.locator('.joo-term__log')).toContainText('ai');
    await run(page, 'tree');
    await expect(page.locator('.joo-term__log')).toContainText('prompt-caching');
    await run(page, 'help');
    await expect(page.locator('.joo-term__log')).toContainText('ls');
    await run(page, 'rm -rf /');
    await expect(page.locator('.joo-term__log')).toContainText(/read-only|not found|unknown/i);
  });

  test('grep finds posts and theme switches the scheme', async ({ page }) => {
    await open(page);
    await run(page, 'grep -ri docker .');
    await expect(page.locator('.joo-term__log')).toContainText(/docker/i);
    await run(page, 'theme light');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
  });

  test('cd then cat opens a post', async ({ page }) => {
    await open(page);
    await run(page, 'cd ai');
    await run(page, 'ls');
    await expect(page.locator('.joo-term__log')).toContainText('prompt-caching');
    await run(page, 'open prompt-caching');
    await expect(page).toHaveURL(/prompt-caching/);
  });

  test('the terminal makes no extra requests, writes no storage and sets no cookies', async ({ page, context }) => {
    await open(page);
    const requests: string[] = [];
    page.on('request', (r) => {
      // Lazy images may load while the page scrolls; the terminal itself must not fetch anything.
      if (['fetch', 'xhr', 'document', 'script', 'websocket'].includes(r.resourceType())) requests.push(r.url());
    });
    for (const cmd of [
      'ls',
      'tree',
      'help',
      'cd ai',
      'ls',
      'cd ..',
      'grep -ri docker .',
      'cat ai/prompt-caching',
      'pwd',
      'whoami',
      'history',
    ]) {
      await run(page, cmd);
    }
    expect(requests).toEqual([]);
    expect(await page.evaluate(() => Object.keys(localStorage).filter((k) => k.startsWith('joo-term')))).toEqual([]);
    const cookies = await context.cookies();
    expect(cookies.filter((c) => /term/i.test(c.name))).toEqual([]);
  });

  test('phone: the closed dock is a bar after the page and the open dock is a bottom sheet', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');
    const dock = page.locator('joo-terminal');
    expect(await dock.evaluate((n) => getComputedStyle(n).position)).toBe('static');
    await page.locator('.joo-term__tab').scrollIntoViewIfNeeded();
    await page.locator('.joo-term__tab').click();
    expect(await dock.evaluate((n) => getComputedStyle(n).position)).toBe('fixed');
    const panel = (await page.locator('.joo-term__panel').boundingBox())!;
    expect(panel.y + panel.height).toBeLessThanOrEqual(844);
    expect(panel.width).toBeGreaterThan(350);
  });
});
