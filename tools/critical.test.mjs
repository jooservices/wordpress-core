import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import { fromRoot } from './lib/paths.mjs';
import { criticalOutputDirectory, localizeSiteUrls } from './lib/critical.mjs';

describe('critical CSS output directory', () => {
  it('defaults to the theme critical directory and accepts a temporary output directory', () => {
    expect(criticalOutputDirectory('starter')).toBe(fromRoot('themes', 'starter', 'assets', 'critical'));
    expect(criticalOutputDirectory('starter', '/tmp/joowt-245/critical')).toBe('/tmp/joowt-245/critical');
    expect(criticalOutputDirectory('starter', 'artifacts/critical')).toBe(fromRoot('artifacts', 'critical'));
  });
});

describe('critical CSS site URLs', () => {
  it('removes the capture origin from local assets and keeps external origins', () => {
    const css =
      'a{src:url(http://127.0.0.1:8380/wp-content/theme/font.woff2)}b{src:url(https://cdn.example/font.woff2)}';

    expect(localizeSiteUrls(css, 'http://127.0.0.1:8380')).toBe(
      'a{src:url(/wp-content/theme/font.woff2)}b{src:url(https://cdn.example/font.woff2)}'
    );
  });
});

describe('critical CSS gzip budgets', () => {
  it.each(['starter', 'jooservices'])('%s templates stay within the 14 KiB gzip budget', (theme) => {
    for (const context of ['404', 'archive', 'home', 'page', 'search', 'single']) {
      const css = readFileSync(fromRoot('themes', theme, 'assets', 'critical', `${context}.css`));

      expect(gzipSync(css).byteLength, `${theme}/${context}.css`).toBeLessThanOrEqual(14 * 1024);
    }
  });
});
