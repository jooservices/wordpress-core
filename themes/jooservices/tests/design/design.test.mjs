// @vitest-environment node
// Design contract of the jooservices theme: tokens, fonts, contrast and template integrity.
import { existsSync, readFileSync, statSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as fontkit from 'fontkit';
import { describe, expect, it } from 'vitest';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const read = (path) => readFileSync(join(root, path), 'utf8');
const json = (path) => JSON.parse(read(path));

const toRgb = (hex) => {
  const h = hex.replace('#', '');
  const full = h.length === 3 ? [...h].map((c) => c + c).join('') : h;
  return [0, 2, 4].map((i) => parseInt(full.slice(i, i + 2), 16));
};
const luminance = ([r, g, b]) => {
  const c = [r, g, b].map((v) => {
    const s = v / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
};
const contrast = (a, b) => {
  const [hi, lo] = [luminance(toRgb(a)), luminance(toRgb(b))].sort((x, y) => y - x);
  return (hi + 0.05) / (lo + 0.05);
};
const palette = (file) => Object.fromEntries(json(file).settings.color.palette.map((p) => [p.slug, p.color]));
const schemeNames = ['dark', 'light', 'ocean', 'forest', 'paper'];

describe('design tokens', () => {
  const tokens = json('design/tokens.json');
  const dark = palette('theme.json');
  const light = palette('styles/light.json');

  it('theme.json, the dark variation and tokens.json agree', () => {
    for (const [slug, value] of Object.entries(tokens.dark)) expect(dark[slug], `dark ${slug}`).toBe(value);
    expect(palette('styles/dark.json')).toEqual(dark);
    for (const [slug, value] of Object.entries(tokens.light)) expect(light[slug], `light ${slug}`).toBe(value);
  });

  it('every runtime scheme in tokens.css matches its Site Editor variation', () => {
    const css = read('assets/src/css/components/tokens.css');
    for (const scheme of schemeNames) {
      const colors = palette(`styles/${scheme}.json`);
      const start = css.indexOf(`:root[data-theme='${scheme}']`);
      const end = css.indexOf('\n}', start);
      const block = css.slice(start, end);
      expect(start, `${scheme} runtime token block`).toBeGreaterThanOrEqual(0);
      for (const [slug, value] of Object.entries(colors)) {
        const [r, g, b] = toRgb(value);
        expect(block, `${scheme} ${slug}`).toContain(`--wp--preset--color--${slug}: rgb(${r} ${g} ${b});`);
      }
    }
  });

  it('every named variation has the same palette slugs as the base theme', () => {
    for (const scheme of schemeNames) {
      expect(palette(`styles/${scheme}.json`), scheme).toHaveProperty('background');
      expect(Object.keys(palette(`styles/${scheme}.json`)), scheme).toEqual(Object.keys(dark));
    }
  });

  it('palette slugs match the design spec', () => {
    for (const slug of [
      'background',
      'surface',
      'raised',
      'ink',
      'muted',
      'line',
      'accent',
      'accent-ink',
      'pin',
      'ok',
      'err',
      'chip',
      'code',
    ])
      expect(dark, slug).toHaveProperty(slug);
  });

  for (const scheme of schemeNames) {
    const colors = palette(`styles/${scheme}.json`);
    it(`${scheme}: contrast targets hold`, () => {
      expect(contrast(colors.ink, colors.background), 'ink on background').toBeGreaterThanOrEqual(7);
      expect(contrast(colors.ink, colors.surface), 'ink on surface').toBeGreaterThanOrEqual(7);
      expect(contrast(colors.muted, colors.background), 'muted on background').toBeGreaterThanOrEqual(4.5);
      expect(contrast(colors.muted, colors.surface), 'muted on surface').toBeGreaterThanOrEqual(4.5);
      expect(contrast(colors.accent, colors.surface), 'accent on surface').toBeGreaterThanOrEqual(4.5);
      expect(contrast(colors.accent, colors.background), 'accent on background').toBeGreaterThanOrEqual(4.5);
      expect(contrast(colors['accent-ink'], colors.accent), 'accent-ink on accent').toBeGreaterThanOrEqual(4.5);
      expect(contrast(colors.pin, colors.surface), 'pin on surface').toBeGreaterThanOrEqual(4.5);
    });
  }

  it('the 404 status colour is readable on the terminal background in both schemes', () => {
    // The 404 terminal stays dark in every palette, so it uses a fixed dark canvas and red.
    const css = read('assets/src/css/components/notfound.css');
    expect(css).toContain('color: rgb(255 107 107);');
    expect(css).toContain('background: rgb(6 9 13);');
    expect(contrast('#ff6b6b', '#06090d')).toBeGreaterThanOrEqual(4.5);
  });
});

describe('layout stability', () => {
  it('renders fragments with the page by default to keep visible sidebar content stable', () => {
    expect(json('joo-theme.json').lazy.fragments).toBe(false);
  });

  it('reserves the desktop terminal dock column in each critical template', () => {
    const dockRule =
      '.joo-term{pointer-events:auto;flex-direction:row-reverse;flex:none;align-self:flex-start;width:40px;min-width:40px;';
    for (const context of ['archive', 'home', 'page', 'search', 'single']) {
      expect(read(`assets/critical/${context}.css`), context).toContain(dockRule);
    }
  });
});

describe('fonts', () => {
  const fonts = json('joo-theme.json').perf.fonts;
  const files = fonts.flatMap((f) => f.files);

  it('every file exists and stays under 60 KB', () => {
    for (const file of files) {
      expect(existsSync(join(root, file)), file).toBe(true);
      expect(statSync(join(root, file)).size, file).toBeLessThanOrEqual(60 * 1024);
    }
  });

  it('preloads at most two files', () => {
    expect(fonts.filter((f) => f.preload).flatMap((f) => f.files).length).toBeLessThanOrEqual(2);
  });

  it('each family has a metric-adjusted fallback', () => {
    for (const family of new Set(fonts.map((f) => f.family))) {
      expect(
        fonts.some((f) => f.family === family && f.fallback?.sizeAdjust),
        family
      ).toBe(true);
    }
  });

  it('the Vietnamese subsets contain every Vietnamese letter', () => {
    const letters = 'ăâđêôơưÁÀẢÃẠẮẰẲẴẶẤẦẨẪẬÉÈẺẼẸẾỀỂỄỆÍÌỈĨỊÓÒỎÕỌỐỒỔỖỘỚỜỞỠỢÚÙỦŨỤỨỪỬỮỰÝỲỶỸỴ';
    for (const file of files.filter((f) => f.includes('vietnamese') || f.includes('latin'))) {
      const font = fontkit.openSync(join(root, file));
      const missing = [...letters].filter((ch) => font.hasGlyphForCodePoint(ch.codePointAt(0)) === false);
      if (file.includes('vietnamese')) {
        // Precomposed letters outside Basic Latin live in the Vietnamese file; Latin-1 ones in the Latin file.
        const needed = [...letters].filter((ch) => ch.codePointAt(0) > 0xff);
        expect(
          needed.filter((ch) => !font.hasGlyphForCodePoint(ch.codePointAt(0))),
          file
        ).toEqual([]);
      } else {
        expect(
          [...'Tieng Viet ABC xyz 0123'].filter((ch) => !font.hasGlyphForCodePoint(ch.codePointAt(0))),
          file
        ).toEqual([]);
        expect(missing.length).toBeGreaterThan(0); // proves the split: Latin alone is not enough for Vietnamese
      }
    }
  });
});

describe('templates', () => {
  it('every template part referenced by a template exists', () => {
    for (const file of [
      'home',
      'index',
      'single',
      'page',
      'page-full',
      'archive',
      'category',
      'tag',
      'author',
      'search',
      '404',
    ]) {
      const html = read(`templates/${file}.html`);
      for (const [, slug] of html.matchAll(/wp:template-part \{"slug":"([a-z-]+)"/g)) {
        expect(existsSync(join(root, `parts/${slug}.html`)), `${file} -> ${slug}`).toBe(true);
      }
    }
  });

  it('the 404 has no rail and no terminal dock; other views have one dock', () => {
    const notFound = read('templates/404.html');
    expect(notFound).not.toContain('wp:template-part {"slug":"rail"');
    expect(notFound).not.toContain('terminal-dock');
    for (const file of ['home', 'single', 'category', 'search'])
      expect(read(`templates/${file}.html`)).toContain('wp:joo/terminal-dock');
  });
});
