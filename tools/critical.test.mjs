import { describe, expect, it } from 'vitest';
import { localizeSiteUrls } from './lib/critical.mjs';

describe('critical CSS site URLs', () => {
  it('removes the capture origin from local assets and keeps external origins', () => {
    const css =
      'a{src:url(http://127.0.0.1:8380/wp-content/theme/font.woff2)}b{src:url(https://cdn.example/font.woff2)}';

    expect(localizeSiteUrls(css, 'http://127.0.0.1:8380')).toBe(
      'a{src:url(/wp-content/theme/font.woff2)}b{src:url(https://cdn.example/font.woff2)}'
    );
  });
});
