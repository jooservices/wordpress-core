import { test, expect, type Page } from '@playwright/test';

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

test.describe('terminal dock', () => {
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
    page.on('request', (r) => requests.push(r.url()));
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
    expect(requests.filter((u) => !u.endsWith('.woff2'))).toEqual([]);
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
