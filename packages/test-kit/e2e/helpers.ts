import { expect, type APIRequestContext, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

export const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

/** Navigate and assert the HTTP status of the main document. */
export async function openView(page: Page, path: string, status = 200): Promise<void> {
  const response = await page.goto(path, { waitUntil: 'load' });
  expect(response, `no response for ${path}`).not.toBeNull();
  expect(response!.status(), `HTTP status of ${path}`).toBe(status);
}

/** Assert the status (and optionally a header substring) of a URL without a browser page. */
export async function expectStatus(
  request: APIRequestContext,
  path: string,
  status: number,
  headers: Record<string, string | RegExp> = {}
): Promise<void> {
  const response = await request.get(path, { maxRedirects: 0 });
  expect(response.status(), `HTTP status of ${path}`).toBe(status);
  for (const [name, expected] of Object.entries(headers)) {
    const value = response.headers()[name.toLowerCase()] ?? '';
    if (expected instanceof RegExp) expect(value, `header ${name} of ${path}`).toMatch(expected);
    else expect(value, `header ${name} of ${path}`).toContain(expected);
  }
}

/** Collect console errors and page errors; call `.assertNone()` at the end of the test. */
export function collectConsoleErrors(page: Page, allow: RegExp[] = []) {
  const errors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error' && !allow.some((re) => re.test(message.text()))) {
      errors.push(`console.error: ${message.text()}`);
    }
  });
  page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));
  return {
    errors,
    assertNone() {
      expect(errors, 'console and page errors').toEqual([]);
    },
  };
}

/** Run axe and fail on serious or critical violations. */
export async function runAxe(
  page: Page,
  options: { include?: string; exclude?: string[]; tags?: string[] } = {}
): Promise<void> {
  let builder = new AxeBuilder({ page }).withTags(options.tags ?? AXE_TAGS);
  if (options.include) builder = builder.include(options.include);
  for (const selector of options.exclude ?? []) builder = builder.exclude(selector);
  const results = await builder.analyze();
  const blocking = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
  expect(
    blocking.map(
      (v) =>
        `${v.id} (${v.impact}): ${v.nodes
          .slice(0, 3)
          .map((n) => n.target.join(' '))
          .join(' | ')}`
    ),
    'axe violations'
  ).toEqual([]);
}

/** The document must not scroll horizontally. */
export async function noHorizontalScroll(page: Page): Promise<void> {
  const overflow = await page.evaluate(() => {
    const el = document.documentElement;
    return { scrollWidth: el.scrollWidth, clientWidth: el.clientWidth };
  });
  expect(overflow.scrollWidth, `horizontal overflow ${JSON.stringify(overflow)}`).toBeLessThanOrEqual(
    overflow.clientWidth
  );
}

/** Interactive elements inside the given scope must be at least `min` CSS pixels (WCAG 2.2 target size). */
export async function tapTargets(page: Page, scope = 'body', min = 24): Promise<string[]> {
  return page.evaluate(
    ({ scope, min }) => {
      const small: string[] = [];
      const root = document.querySelector(scope) ?? document.body;
      root.querySelectorAll<HTMLElement>('a[href], button, input, select, textarea, summary').forEach((el) => {
        const rect = el.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) return;
        const inline =
          getComputedStyle(el).display === 'inline' && el.tagName === 'A' && el.textContent && el.closest('p, li');
        if (inline) return; // inline text links are exempt by WCAG 2.5.8
        if (rect.width < min || rect.height < min)
          small.push(
            `${el.tagName.toLowerCase()} "${(el.textContent ?? '').trim().slice(0, 24)}" ${Math.round(rect.width)}x${Math.round(rect.height)}`
          );
      });
      return small;
    },
    { scope, min }
  );
}

/** Set the colour scheme before the page loads (the theme reads localStorage key joo-theme). */
export async function setScheme(page: Page, scheme: 'dark' | 'light'): Promise<void> {
  await page.addInitScript((value) => {
    try {
      window.localStorage.setItem('joo-theme', value);
    } catch {
      /* storage unavailable */
    }
  }, scheme);
}
