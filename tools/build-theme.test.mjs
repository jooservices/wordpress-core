// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { EXCLUDED, styleHeader } from './build-theme.mjs';

describe('build-theme', () => {
  it('reads the style.css header', () => {
    const header = styleHeader('/*\nTheme Name: Acme\nVersion: 1.2.3\nText Domain: acme\n*/');
    expect(header.Version).toBe('1.2.3');
    expect(header['Theme Name']).toBe('Acme');
  });

  it('never ships tests, sources, the dev core link or source maps', () => {
    const shipped = [
      'style.css',
      'theme.json',
      'assets/build/css/theme-1.css',
      'assets/fonts/a.woff2',
      'templates/home.html',
    ];
    const dropped = [
      'core/core.php',
      'tests/e2e/a.spec.ts',
      'assets/src/css/a.css',
      'node_modules/x/index.js',
      'package.json',
      'budgets.json',
      'assets/build/css/a.css.map',
      'design/reference-demo.html',
      'demo/content.xml',
      '.DS_Store',
    ];
    for (const file of shipped)
      expect(
        EXCLUDED.some((re) => re.test(file)),
        file
      ).toBe(false);
    for (const file of dropped)
      expect(
        EXCLUDED.some((re) => re.test(file)),
        file
      ).toBe(true);
  });
});
