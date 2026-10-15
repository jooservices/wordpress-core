import { test, expect } from '@playwright/test';
import {
  collectConsoleErrors,
  expectStatus,
  noHorizontalScroll,
  openView,
  runAxe,
} from '../../../../packages/test-kit/e2e/helpers';

// Theme-agnostic smoke checks, parameterised by the running demo (E2E_THEME is only used in reports).
test.describe('smoke', () => {
  test('home renders without errors or horizontal scroll', async ({ page }) => {
    const errors = collectConsoleErrors(page);
    await openView(page, '/');
    await expect(page.locator('html')).toHaveAttribute('lang', /.+/);
    await noHorizontalScroll(page);
    errors.assertNone();
  });

  test('a missing URL returns a real 404', async ({ request }) => {
    await expectStatus(request, '/definitely-missing-url/', 404);
  });

  test('home passes the automated accessibility scan', async ({ page }) => {
    await openView(page, '/');
    await runAxe(page);
  });
});
