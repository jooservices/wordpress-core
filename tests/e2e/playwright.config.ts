import { defineConfig, devices } from '@playwright/test';
import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8082';
const theme = process.env.E2E_THEME ?? '';
const reports = process.env.E2E_REPORT_DIR ?? '../../artifacts/reports/e2e';
const responsiveSpec = 'themes/jooservices/tests/e2e/responsive.spec.ts';
const fixtureSpecs = theme ? new URL(`../fixtures/themes/${theme}/tests/e2e/`, import.meta.url) : null;
const fixtureTheme = fixtureSpecs ? existsSync(fileURLToPath(fixtureSpecs)) : false;

export default defineConfig({
  // Fixture themes run their own consumer smoke suite; packaged themes also run the shared suite.
  testDir: '../..',
  testMatch: fixtureTheme
    ? [`tests/fixtures/themes/${theme}/tests/e2e/**/*.spec.ts`]
    : ['tests/e2e/specs/**/*.spec.ts', ...(theme ? [`themes/${theme}/tests/e2e/**/*.spec.ts`] : [])],
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
      ...(theme === 'jooservices' ? { testIgnore: [responsiveSpec] } : {}),
    },
    {
      name: 'tablet',
      use: { viewport: { width: 820, height: 1180 }, hasTouch: true },
      ...(theme === 'jooservices' ? { testIgnore: [responsiveSpec] } : {}),
    },
    {
      name: 'desktop',
      use: { viewport: { width: 1440, height: 900 } },
      ...(theme === 'jooservices' ? { testIgnore: [responsiveSpec] } : {}),
    },
    ...(theme === 'jooservices'
      ? [
          {
            name: 'responsive-chromium',
            testMatch: [responsiveSpec],
            use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } },
          },
          {
            name: 'responsive-webkit',
            testMatch: [responsiveSpec],
            use: { ...devices['Desktop Safari'], viewport: { width: 1440, height: 900 } },
          },
          {
            name: 'responsive-firefox',
            testMatch: [responsiveSpec],
            use: { ...devices['Desktop Firefox'], viewport: { width: 1440, height: 900 } },
          },
        ]
      : []),
    // Cross-engine runs (E2E_ENGINES=webkit,firefox): WebKit is the Safari engine. Each engine runs at the three
    // device viewports; `<engine>` is the 1440x900 desktop project. Firefox has no mobile emulation (`isMobile`
    // is a Chromium and WebKit option), so its phone project is the 390x844 viewport with touch and a 2x pixel ratio.
    ...crossEngineProjects('webkit', 'Desktop Safari', true),
    ...crossEngineProjects('firefox', 'Desktop Firefox', false),
  ],
});

function crossEngineProjects(engine: 'webkit' | 'firefox', device: string, mobileEmulation: boolean) {
  if (
    !process.env.E2E_ENGINES?.split(',')
      .map((name) => name.trim())
      .includes(engine)
  )
    return [];
  // The jooservices responsive spec has its own per-engine projects above.
  const ignore = theme === 'jooservices' ? { testIgnore: [responsiveSpec] } : {};
  return [
    {
      name: `${engine}-phone`,
      use: {
        ...devices[device],
        viewport: { width: 390, height: 844 },
        hasTouch: true,
        isMobile: mobileEmulation,
        deviceScaleFactor: 2,
      },
      ...ignore,
    },
    {
      name: `${engine}-tablet`,
      use: { ...devices[device], viewport: { width: 820, height: 1180 }, hasTouch: true },
      ...ignore,
    },
    { name: engine, use: { ...devices[device], viewport: { width: 1440, height: 900 } }, ...ignore },
  ];
}
