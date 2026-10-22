// Regenerates docs/assets/responsive/*.png with Playwright Chromium.
// Usage: node themes/jooservices/tests/responsive-screenshots.mjs [url]
// Without a URL it renders the design reference (themes/jooservices/design/reference-demo.html), which uses the same
// rail, card and dock breakpoints as the theme. Pass a running site URL to capture the real theme instead.
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const out = `${root}docs/assets/responsive`;
const target = process.argv[2] ?? pathToFileURL(`${root}themes/jooservices/design/reference-demo.html`).href;
const selectors = process.argv[2]
  ? { rail: '.joo-rail-btn', dock: '.joo-term__tab' }
  : { rail: '#railBtn', dock: '#tdTab' };

mkdirSync(out, { recursive: true });
const browser = await chromium.launch();

async function shot(name, width, height, action) {
  const page = await browser.newPage({ viewport: { width, height } });
  await page.goto(target);
  await page.waitForTimeout(500);
  if (action) await action(page);
  await page.waitForTimeout(400);
  await page.screenshot({ path: `${out}/${name}.png` });
  const fits = await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth);
  console.log(`${name}: ${fits ? 'no horizontal scroll' : 'HORIZONTAL SCROLL'}`);
  await page.close();
}

await shot('wide-1440', 1440, 900);
await shot('rail-fold-closed-820', 820, 1180);
await shot('rail-fold-open-820', 820, 1180, (page) => page.click(selectors.rail));
await shot('dock-sheet-open-820', 820, 1180, (page) => page.click(selectors.dock));
await shot('card-collapse-390', 390, 844);
await shot('card-collapse-320', 320, 640);
await browser.close();
