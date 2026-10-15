import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8080';
const theme = process.env.E2E_THEME ?? '';
const reports = '../../artifacts/reports/e2e';

export default defineConfig({
  // Shared specs plus the specs of the theme under test (themes/<slug>/tests/e2e).
  testDir: '../..',
  testMatch: ['tests/e2e/specs/**/*.spec.ts', ...(theme ? [`themes/${theme}/tests/e2e/**/*.spec.ts`] : [])],
  // Admin specs change shared settings and share one WordPress session store: run one test at a time.
  fullyParallel: false,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  timeout: 45_000,
  expect: { timeout: 10_000 },
  reporter: [
    ['list'],
    ['junit', { outputFile: `${reports}/junit.xml` }],
    ['html', { outputFolder: `${reports}/html`, open: 'never' }],
  ],
  outputDir: `${reports}/artifacts`,
  use: { baseURL, trace: 'retain-on-failure', screenshot: 'only-on-failure', reducedMotion: 'reduce' },
  projects: [
    {
      name: 'phone',
      use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 },
    },
    { name: 'tablet', use: { viewport: { width: 820, height: 1180 }, hasTouch: true } },
    { name: 'desktop', use: { viewport: { width: 1440, height: 900 } } },
    // Cross-engine runs (E2E_ENGINES=webkit,firefox): WebKit is the Safari engine.
    ...(process.env.E2E_ENGINES?.includes('webkit')
      ? [{ name: 'webkit', use: { ...devices['Desktop Safari'], viewport: { width: 1440, height: 900 } } }]
      : []),
    ...(process.env.E2E_ENGINES?.includes('firefox')
      ? [{ name: 'firefox', use: { ...devices['Desktop Firefox'], viewport: { width: 1440, height: 900 } } }]
      : []),
  ],
});
