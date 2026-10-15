import { test, expect } from '@playwright/test';
// @ts-expect-error plain JS helper without type declarations
import { extractCriticalRules, mergeAndMinify } from '../../../../tools/lib/critical.mjs';

test.describe('critical CSS extraction', () => {
  test('keeps rules for elements in the first viewport and drops the rest', async ({ page }) => {
    await page.setContent(`<!doctype html><html lang="en"><head><title>t</title><style>
      body { margin: 0; font-family: serif; }
      .joo-top { color: red; }
      .joo-top:hover { color: blue; }
      .joo-bottom { color: green; }
      .joo-unused { color: pink; }
      @media (min-width: 1px) { .joo-top { padding: 4px; } .joo-bottom { padding: 8px; } }
      @font-face { font-family: Demo; src: local(Arial); }
    </style></head><body><div class="joo-top">top</div><div style="height:4000px"></div><div class="joo-bottom">bottom</div></body></html>`);
    const rules: string[] = await extractCriticalRules(page);
    const css = rules.join('');
    expect(css).toContain('.joo-top');
    expect(css).toContain('body');
    expect(css).toContain('@font-face');
    expect(css).not.toContain('.joo-bottom');
    expect(css).not.toContain('.joo-unused');
    expect(css).toMatch(/@media[^{]*\{[^}]*joo-top/);
  });

  test('mergeAndMinify deduplicates and reports sizes', () => {
    const { css, size, gzip } = mergeAndMinify([
      ['.joo-a { color: red; }'],
      ['.joo-a { color: red; }', '.joo-b { margin: 0; }'],
    ]);
    expect(css.match(/joo-a/g)).toHaveLength(1);
    expect(css).toContain('.joo-b');
    expect(size).toBe(css.length);
    expect(gzip).toBeGreaterThan(0);
  });
});
