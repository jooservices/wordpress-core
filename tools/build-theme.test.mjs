// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { addBundledPlugin, EXCLUDED, styleHeader } from './build-theme.mjs';

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
      'release-assets/preview/01_jooservices.png',
      'release-assets/README.md',
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

  it('copies the companion plugin archive into the theme package', () => {
    const root = mkdtempSync(join(tmpdir(), 'jooservices-theme-package-'));
    const stage = join(root, 'theme');
    const pluginZip = join(root, 'jooservices-resume.zip');
    writeFileSync(pluginZip, 'zip bytes');

    try {
      const destination = addBundledPlugin(stage, pluginZip, 'jooservices-resume');
      expect(destination).toBe(join(stage, 'plugins', 'jooservices-resume.zip'));
      expect(readFileSync(destination, 'utf8')).toBe('zip bytes');
    } finally {
      rmSync(root, { recursive: true, force: true });
    }
  });

  it('fails if the requested companion plugin archive is missing', () => {
    const root = mkdtempSync(join(tmpdir(), 'jooservices-theme-package-'));
    const missingPlugin = join(root, 'missing.zip');

    try {
      expect(() => addBundledPlugin(join(root, 'theme'), missingPlugin, 'jooservices-resume')).toThrow(
        `bundled plugin archive is missing: ${missingPlugin}`
      );
    } finally {
      rmSync(root, { recursive: true, force: true });
    }
  });
});
