// @vitest-environment node
import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, relative } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';
import {
  checksumManifest,
  createDemoContent,
  problems,
  readVersions,
  releaseNotes,
  sanitizeDemoXml,
} from './release.mjs';
import { compareArtifacts, sha256, sortedFiles } from './lib/reproducible.mjs';
import { build } from './wxr-generator/generate.mjs';
import { writeGeneratedDemo } from './demo-package.mjs';
import { checkRelease, releaseProblems, readReleaseMetadata } from './release-check.mjs';
import { check } from './theme-check.mjs';
import { render } from './perf-report.mjs';

const tempDirectories = [];
const tempDirectory = () => {
  const dir = mkdtempSync(join(tmpdir(), 'jooservices-release-'));
  tempDirectories.push(dir);
  return dir;
};

afterEach(() => {
  for (const dir of tempDirectories.splice(0)) rmSync(dir, { recursive: true, force: true });
});

describe('release', () => {
  const ok = {
    theme: '1.2.3',
    themeChangelog: '1.2.3',
    plugin: '2.3.4',
    pluginConstant: '2.3.4',
    pluginStableTag: '2.3.4',
    coreRange: '^0.1',
  };

  it('reads canonical theme and plugin versions and changelog mirrors', () => {
    const root = tempDirectory();
    const theme = join(root, 'theme');
    mkdirSync(theme);
    writeFileSync(join(theme, 'style.css'), '/*\nVersion: 1.2.3\n*/\n');
    writeFileSync(join(theme, 'CHANGELOG.md'), '# Changelog\n\n## 1.2.3\n');
    writeFileSync(join(theme, 'joo-theme.json'), '{"core":"^0.1"}');
    const versions = readVersions(
      theme,
      '/**\n * Version: 2.3.4\n */',
      "public const VERSION = '2.3.4';",
      'Stable tag: 2.3.4'
    );
    expect(versions).toEqual({
      theme: '1.2.3',
      themeChangelog: '1.2.3',
      plugin: '2.3.4',
      pluginConstant: '2.3.4',
      pluginStableTag: '2.3.4',
      coreRange: '^0.1',
    });
  });

  it('accepts consistent strict SemVer values and rejects stale mirrors', () => {
    expect(problems(ok)).toEqual([]);
    expect(problems({ ...ok, theme: '1.2', themeChangelog: '1.2.2', pluginStableTag: '2.3.3', coreRange: '' })).toEqual(
      [
        'theme version "1.2" is not strict semver',
        'theme changelog version differs from style.css',
        'plugin Stable tag differs from plugin header',
        'theme manifest has no core compatibility range',
      ]
    );
  });

  it('uses JOOS-keyed commit messages and falls back to the changelog', () => {
    expect(releaseNotes(['Merge branch foo', 'JOOS-103: add package builder'], '# prior notes')).toBe(
      '# Release notes\n\n- JOOS-103: add package builder\n'
    );
    expect(releaseNotes(['merge only'], '  # prior notes\n')).toBe('# prior notes');
  });

  it('fictionalizes owner and company data in the sale demo export', () => {
    const xml = [
      '<wp:author_login><![CDATA[viet]]></wp:author_login>',
      '<wp:author_email><![CDATA[viet@example.test]]></wp:author_email>',
      '<wp:author_display_name><![CDATA[Viet VU]]></wp:author_display_name>',
      '<wp:author_first_name><![CDATA[Viet]]></wp:author_first_name>',
      '<wp:author_last_name><![CDATA[VU]]></wp:author_last_name>',
      '<title>JOOservices Ltd and SGCarmart</title>',
      '<wp:post_name><![CDATA[sgcarmart]]></wp:post_name>',
      '<url>https://jooservices.com</url><url>https://www.sgcarmart.com</url>',
      '<content:encoded><![CDATA[hello@jooservices.com]]></content:encoded>',
      '<url>https://github.com/jooservices</url>',
    ].join('\n');
    const clean = sanitizeDemoXml(xml, [
      { name: 'JOOservices Ltd', url: 'https://jooservices.com' },
      { name: 'SGCarmart', url: 'https://www.sgcarmart.com' },
    ]);
    expect(clean).toContain('Example Company 1 and Example Company 2');
    expect(clean).toContain('Taylor Example');
    expect(clean).toContain('taylor@example.test');
    expect(clean).toContain('<wp:post_name><![CDATA[example-company-2]]></wp:post_name>');
    expect(clean).toContain('hello@example.test');
    expect(clean).not.toContain('Viet VU');
    expect(clean).not.toContain('SGCarmart');
    expect(clean).not.toContain('jooservices.com');
    expect(clean).not.toContain('sgcarmart.com');
  });

  it('builds a checked, fictionalized Tier B package with reusable licensed content', () => {
    const root = tempDirectory();
    const source = join(root, 'source');
    const target = join(root, 'Demo-Content');
    writeGeneratedDemo(build(42), source);
    createDemoContent(source, target);

    const xml = readFileSync(join(target, 'content.xml'), 'utf8');
    const manifest = JSON.parse(readFileSync(join(target, 'manifest.json'), 'utf8'));
    const checksums = JSON.parse(readFileSync(join(target, 'checksums.json'), 'utf8'));
    const licenses = readFileSync(join(target, 'LICENSES.md'), 'utf8');
    const attribution = readFileSync(join(target, 'ATTRIBUTION.md'), 'utf8');

    expect(manifest).toMatchObject({ tier: 'B', license: 'CC-BY-4.0', generatedMedia: true, languages: ['en', 'vi'] });
    expect(xml).toContain('<wp:author_login><![CDATA[taylor]]></wp:author_login>');
    expect(xml).not.toContain('Viet VU');
    expect(xml).not.toContain('sgcarmart.com');
    expect(licenses).toContain('Buyers may copy, adapt, publish, and use it commercially');
    expect(licenses).toContain('CC BY 4.0');
    expect(attribution).toContain('media/demo-12-800x600.png');
    expect(JSON.parse(readFileSync(join(target, 'widgets.json'), 'utf8'))).toEqual({ sidebars: [], widgets: [] });
    expect(JSON.parse(readFileSync(join(target, 'patterns.json'), 'utf8'))).toMatchObject({
      registeredWithTheme: true,
      patterns: [],
    });
    expect(JSON.parse(readFileSync(join(target, 'structure.json'), 'utf8')).themeOptions).toEqual({
      nav_menu_locations: { primary: 'joo-primary', footer: 'joo-footer' },
      profile_user: 'taylor',
    });
    expect(Object.keys(checksums).sort()).toEqual(
      sortedFiles(target)
        .filter((file) => !file.endsWith('/checksums.json'))
        .map((file) => relative(target, file).replaceAll('\\', '/'))
        .sort()
    );
    expect(Object.values(checksums).every((sum) => /^[a-f0-9]{64}$/.test(sum))).toBe(true);
    expect(sortedFiles(target).reduce((sum, file) => sum + readFileSync(file).length, 0)).toBeLessThan(
      40 * 1024 * 1024
    );
  });

  it('creates sorted checksum manifests and SHA-256 hashes', () => {
    const root = tempDirectory();
    writeFileSync(join(root, 'b.txt'), 'bravo');
    writeFileSync(join(root, 'a.txt'), 'alpha');
    const manifest = checksumManifest(root);
    expect(
      manifest
        .split('\n')
        .slice(0, 2)
        .map((line) => line.slice(66))
    ).toEqual(['a.txt', 'b.txt']);
    expect(sha256(Buffer.from('alpha'))).toBe(manifest.split('\n')[0].slice(0, 64));
  });

  it('compares all artifacts by content and reports missing or changed outputs', () => {
    expect(
      compareArtifacts(
        { 'theme.zip': Buffer.from('same'), 'plugin.zip': Buffer.from('old') },
        { 'theme.zip': Buffer.from('same'), 'plugin.zip': Buffer.from('new'), 'demo.zip': Buffer.from('new') }
      )
    ).toEqual([
      { name: 'demo.zip', firstHash: '', secondHash: sha256(Buffer.from('new')), identical: false },
      {
        name: 'plugin.zip',
        firstHash: sha256(Buffer.from('old')),
        secondHash: sha256(Buffer.from('new')),
        identical: false,
      },
      {
        name: 'theme.zip',
        firstHash: sha256(Buffer.from('same')),
        secondHash: sha256(Buffer.from('same')),
        identical: true,
      },
    ]);
  });
});

describe('release metadata validation', () => {
  const valid = {
    theme: {
      version: '0.1.0',
      changelogVersion: '0.1.0',
      testedUpTo: '7.1',
      requiresAtLeast: '6.6',
      requiresPhp: '8.1',
      license: 'GPL-2.0-or-later',
      coreRange: '^0.1',
      readmeLinksChangelog: true,
      docsChangelogVersion: '0.1.0',
    },
    plugin: {
      version: '0.1.0',
      sourceVersion: '0.1.0',
      stableTag: '0.1.0',
      changelogVersion: '0.1.0',
      testedUpTo: '7.1',
      requiresAtLeast: '6.6',
      readmeRequiresAtLeast: '6.6',
      requiresPhp: '8.1',
      readmeRequiresPhp: '8.1',
      license: 'GPL-2.0-or-later',
      readmeLinksChangelog: true,
      docsChangelogVersion: '0.1.0',
    },
    preview: { width: 590, height: 300 },
  };

  it('accepts the product versions and a matching supported WordPress version', () => {
    expect(releaseProblems(valid, { latestWordPress: '7.1.3' })).toEqual([]);
  });

  it('blocks bad requirements, stale metadata, unsupported licensing, and a wrong preview size', () => {
    const bad = {
      ...valid,
      theme: {
        ...valid.theme,
        version: '0.1',
        testedUpTo: '6.5',
        requiresAtLeast: 'latest',
        requiresPhp: '8.x',
        license: 'Proprietary',
        readmeLinksChangelog: false,
      },
      plugin: {
        ...valid.plugin,
        sourceVersion: '0.0.9',
        stableTag: '0.0.9',
        changelogVersion: '0.0.9',
        readmeRequiresAtLeast: '6.5',
        readmeRequiresPhp: '8.0',
        testedUpTo: '7.0',
        readmeLinksChangelog: false,
        docsChangelogVersion: '0.0.9',
      },
      preview: { width: 1200, height: 900 },
    };
    expect(releaseProblems(bad, { latestWordPress: '7.1.3' })).toEqual(
      expect.arrayContaining([
        'theme style.css Version "0.1" is not strict semver',
        'plugin Plugin::VERSION 0.0.9 does not match 0.1.0',
        'plugin Stable tag 0.0.9 does not match 0.1.0',
        'theme minimum WordPress version is invalid',
        'theme minimum PHP version is invalid',
        'theme license is not GPL compatible',
        'plugin readme Requires at least differs from its plugin header',
        'theme and Resume plugin minimum WordPress versions differ',
        'theme and Resume plugin Tested up to versions differ',
        'ThemeForest primary preview must be 590x300, found 1200x900',
      ])
    );
  });

  it('reports a mismatching changelog and a Tested up to below Requires at least', () => {
    const found = releaseProblems({
      ...valid,
      theme: {
        ...valid.theme,
        version: '1.0.0',
        changelogVersion: '0.9.0',
        docsChangelogVersion: '0.9.0',
        testedUpTo: '6.0',
        requiresAtLeast: '6.6',
      },
      plugin: { ...valid.plugin, version: 'x' },
    });
    expect(found).toEqual(
      expect.arrayContaining([
        'theme changelog 0.9.0 does not match 1.0.0',
        'release docs theme changelog 0.9.0 does not match 1.0.0',
        'plugin Version "x" is not strict semver',
        expect.stringContaining('Tested up to 6.0 is below Requires at least 6.6'),
      ])
    );
  });

  it('blocks missing or invalid preview files and detects the current release metadata', () => {
    expect(releaseProblems({ ...valid, preview: {} })).toContain(
      'ThemeForest primary preview is missing or is not a PNG'
    );
    const metadata = readReleaseMetadata(process.cwd());
    expect(metadata.theme.version).toBe('0.1.0');
    expect(metadata.plugin.version).toBe('0.1.0');
    expect(metadata.theme.docsChangelogVersion).toBe('0.1.0');
    expect(metadata.plugin.docsChangelogVersion).toBe('0.1.0');
    expect(metadata.preview).toMatchObject({ width: 590, height: 300 });
    expect(checkRelease(process.cwd())).toEqual([]);
  });
});

describe('theme-check', () => {
  it('reports a theme without header fields or screenshot', () => {
    expect(check('/nonexistent/acme').length).toBeGreaterThan(5);
  });
});

describe('perf-report', () => {
  it('renders one row per page', () => {
    const md = render('t', [
      {
        name: 'home',
        scores: { performance: 1, accessibility: 1, 'best-practices': 1, seo: 1 },
        metrics: { 'cumulative-layout-shift': 0.01, 'total-blocking-time': 0, 'largest-contentful-paint': 2000 },
        bytes: { css: 1, js: 2, fonts: 3 },
        problems: [],
      },
    ]);
    expect(md).toContain('| home | 1 | 1 | 1 | 1 | 0.010 | 0 | 2000 | 1 / 2 / 3 | ok |');
  });
});
