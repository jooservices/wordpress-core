import { test, expect } from '@playwright/test';
import { noHorizontalScroll, runAxe, tapTargets } from '../../../../packages/test-kit/e2e/helpers';

// Negative controls: each helper must fail on a deliberately broken page.
test.describe('e2e helper negative controls', () => {
  test('noHorizontalScroll fails on an overflowing page', async ({ page }) => {
    await page.setContent('<html lang="en"><body><div style="width:3000px;height:10px">wide</div></body></html>');
    await expect(noHorizontalScroll(page)).rejects.toThrow();
  });

  test('noHorizontalScroll passes on a fitting page', async ({ page }) => {
    await page.setContent('<html lang="en"><body><p>fits</p></body></html>');
    await noHorizontalScroll(page);
  });

  test('runAxe fails on low contrast text', async ({ page }) => {
    await page.setContent(
      '<html lang="en"><head><title>t</title></head><body><main><p style="color:#bbb;background:#fff">Low contrast text</p></main></body></html>'
    );
    await expect(runAxe(page)).rejects.toThrow();
  });

  test('runAxe passes on an accessible page', async ({ page }) => {
    await page.setContent(
      '<html lang="en"><head><title>t</title></head><body><main><h1>Title</h1><p style="color:#111;background:#fff">Readable</p></main></body></html>'
    );
    await runAxe(page);
  });

  test('tapTargets reports tiny controls', async ({ page }) => {
    await page.setContent(
      '<html lang="en"><body><button style="width:10px;height:10px;padding:0">x</button><button style="min-width:48px;min-height:48px">ok</button></body></html>'
    );
    const small = await tapTargets(page, 'body', 44);
    expect(small).toHaveLength(1);
  });
});
