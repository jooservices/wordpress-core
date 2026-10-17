// Renders 1200x630 social cards (Open Graph size) for posts that have no usable featured image, in the theme's
// look: dark grid, accent pill with the category, the title in the theme font and the site name.
// Used by tools/live-import.mjs; the PNGs land in the given directory.
import { launchBrowser } from './lib/browser.mjs';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fromRoot } from './lib/paths.mjs';

const font = (file) =>
  `data:font/woff2;base64,${readFileSync(fromRoot('themes/jooservices/assets/fonts', file)).toString('base64')}`;
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

/** Size of the title text so that long titles still fit. */
export function titleSize(title) {
  const n = [...title].length;
  return n > 110 ? 44 : n > 80 ? 52 : n > 50 ? 60 : 68;
}

/** Hue (0-360) derived from a slug so each card differs but stays on-brand. */
export function hueOf(slug) {
  let h = 0;
  for (const ch of slug) h = (h * 31 + ch.charCodeAt(0)) % 360;
  return 170 + (h % 80); // teal to blue
}

export function cardHtml({ title, category, slug }) {
  const hue = hueOf(slug);
  return `<!doctype html><meta charset="utf-8"><style>
@font-face{font-family:Plex;src:url(${font('ibm-plex-sans-latin.woff2')});font-weight:400 600;unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+20AC,U+2122}
@font-face{font-family:Plex;src:url(${font('ibm-plex-sans-vietnamese.woff2')});font-weight:400 600;unicode-range:U+0102-0103,U+0110-0111,U+0128-0129,U+0168-0169,U+01A0-01A1,U+01AF-01B0,U+0300-0301,U+0303-0304,U+0308-0309,U+0323,U+0329,U+1EA0-1EF9,U+20AB}
@font-face{font-family:Mono;src:url(${font('jetbrains-mono-latin.woff2')});font-weight:400 700}
*{box-sizing:border-box;margin:0}
body{width:1200px;height:630px;background:radial-gradient(900px 500px at 85% 0%,hsl(${hue} 70% 22% / .9),transparent),#0a0e13;color:#d9e3ef;font-family:Plex,system-ui,sans-serif;position:relative;overflow:hidden}
.grid{position:absolute;inset:0;background-image:linear-gradient(#1e2935 1px,transparent 1px),linear-gradient(90deg,#1e2935 1px,transparent 1px);background-size:48px 48px;opacity:.45;mask-image:linear-gradient(90deg,transparent,#000)}
.wrap{position:absolute;inset:64px;display:flex;flex-direction:column;justify-content:space-between}
.pill{align-self:flex-start;font:600 24px Mono;letter-spacing:.1em;text-transform:uppercase;color:#5cc8ff;border:2px solid #5cc8ff66;border-radius:999px;padding:6px 22px}
h1{font-size:${titleSize(title)}px;line-height:1.15;font-weight:600;text-wrap:balance;max-width:1000px}
.brand{font:600 34px Mono;color:#d9e3ef}.brand span{color:#5cc8ff}
</style><div class="grid"></div><div class="wrap"><div class="pill">${esc(category || 'Blog')}</div><h1>${esc(title)}</h1><div class="brand">joo<span>services</span>.com</div></div>`;
}

/**
 * @param {Array<{ title: string, category: string, slug: string, file: string }>} cards Cards to render.
 * @param {string} dir Output directory.
 */
export async function renderCards(cards, dir) {
  if (!cards.length) return;
  const browser = await launchBrowser();
  const page = await browser.newPage({ viewport: { width: 1200, height: 630 } });
  for (const card of cards) {
    await page.setContent(cardHtml(card), { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: join(dir, card.file), type: 'png' });
  }
  await browser.close();
}
