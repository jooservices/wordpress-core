import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fromRoot } from './lib/paths.mjs';
import { check as themeCheck } from './theme-check.mjs';

const PNG_SIGNATURE = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]);
const SEMVER = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/;
const WP_VERSION = /^(0|[1-9]\d*)\.(0|[1-9]\d*)$/;

function headerFields(source, expression) {
  return Object.fromEntries([...source.matchAll(expression)].map((match) => [match[1].trim(), match[2].trim()]));
}

function firstHeading(source, expression) {
  return expression.exec(source)?.[1] ?? '';
}

function versionAtLeast(actual, minimum) {
  if (!WP_VERSION.test(actual) || !WP_VERSION.test(minimum)) return false;
  const [aMajor, aMinor] = actual.split('.').map(Number);
  const [mMajor, mMinor] = minimum.split('.').map(Number);
  return aMajor > mMajor || (aMajor === mMajor && aMinor >= mMinor);
}

/** Reads the dimensions in a PNG header, or returns null for invalid PNG data. */
export function pngDimensions(buffer) {
  if (buffer.length < 24 || !buffer.subarray(0, 8).equals(PNG_SIGNATURE)) return null;
  return { width: buffer.readUInt32BE(16), height: buffer.readUInt32BE(20) };
}

/** Reads package version metadata and the prepared ThemeForest preview image. */
export function readReleaseMetadata(root, themeSlug = 'jooservices') {
  const themeDir = join(root, 'themes', themeSlug);
  const pluginDir = join(root, 'plugins', 'jooservices-resume');
  const style = readFileSync(join(themeDir, 'style.css'), 'utf8');
  const themeReadme = readFileSync(join(themeDir, 'README.md'), 'utf8');
  const themeChangelog = readFileSync(join(themeDir, 'CHANGELOG.md'), 'utf8');
  const manifest = JSON.parse(readFileSync(join(themeDir, 'joo-theme.json'), 'utf8'));
  const pluginMain = readFileSync(join(pluginDir, 'jooservices-resume.php'), 'utf8');
  const pluginClass = readFileSync(join(pluginDir, 'src', 'Plugin.php'), 'utf8');
  const pluginReadme = readFileSync(join(pluginDir, 'readme.txt'), 'utf8');
  const productChangelog = readFileSync(join(root, 'docs', 'releases', 'CHANGELOG.md'), 'utf8');
  const previewPath = join(themeDir, 'release-assets', 'preview', '01_jooservices.png');
  const previewBytes = existsSync(previewPath) ? readFileSync(previewPath) : Buffer.alloc(0);
  const preview = pngDimensions(previewBytes);
  const themeHeaders = headerFields(style, /^([A-Za-z ]+):\s*(.+)$/gm);
  const pluginHeaders = headerFields(pluginMain, /^\s*\*\s*([A-Za-z ]+):\s*(.+)$/gm);

  return {
    theme: {
      name: themeHeaders['Theme Name'] ?? '',
      version: themeHeaders.Version ?? '',
      changelogVersion: firstHeading(themeChangelog, /^## (\d+\.\d+\.\d+)\b/m),
      testedUpTo: themeHeaders['Tested up to'] ?? '',
      requiresAtLeast: themeHeaders['Requires at least'] ?? '',
      requiresPhp: themeHeaders['Requires PHP'] ?? '',
      license: themeHeaders.License ?? '',
      coreRange: String(manifest.core ?? ''),
      readmeLinksChangelog: /CHANGELOG\.md/.test(themeReadme),
      docsChangelogVersion: /^## JOOservices theme (\d+\.\d+\.\d+)$/m.exec(productChangelog)?.[1] ?? '',
    },
    plugin: {
      name: pluginHeaders['Plugin Name'] ?? '',
      version: pluginHeaders.Version ?? '',
      sourceVersion: /public const VERSION\s*=\s*'([^']+)'/.exec(pluginClass)?.[1] ?? '',
      stableTag: /^Stable tag:\s*(\S+)/m.exec(pluginReadme)?.[1] ?? '',
      changelogVersion: firstHeading(pluginReadme, /^= (\d+\.\d+\.\d+) =/m),
      testedUpTo: /^Tested up to:\s*(\S+)/m.exec(pluginReadme)?.[1] ?? '',
      requiresAtLeast: pluginHeaders['Requires at least'] ?? '',
      readmeRequiresAtLeast: /^Requires at least:\s*(\S+)/m.exec(pluginReadme)?.[1] ?? '',
      requiresPhp: pluginHeaders['Requires PHP'] ?? '',
      readmeRequiresPhp: /^Requires PHP:\s*(\S+)/m.exec(pluginReadme)?.[1] ?? '',
      license: pluginHeaders.License ?? '',
      readmeLinksChangelog: /^== Changelog ==$/m.test(pluginReadme),
      docsChangelogVersion: /^## JOOservices Resume (\d+\.\d+\.\d+)$/m.exec(productChangelog)?.[1] ?? '',
    },
    preview: { path: previewPath, ...(preview ?? {}) },
  };
}

/** Returns release-blocking version, compatibility, license, and preview problems. */
export function releaseProblems(metadata, { latestWordPress = '' } = {}) {
  const problems = [];
  const { theme, plugin, preview } = metadata;
  const validTheme = SEMVER.test(theme.version);
  const validPlugin = SEMVER.test(plugin.version);

  if (!validTheme) problems.push(`theme style.css Version "${theme.version}" is not strict semver`);
  if (!validPlugin) problems.push(`plugin Version "${plugin.version}" is not strict semver`);
  if (validTheme && theme.changelogVersion !== theme.version)
    problems.push(`theme changelog ${theme.changelogVersion || '(missing)'} does not match ${theme.version}`);
  if (validTheme && theme.docsChangelogVersion !== theme.version)
    problems.push(
      `release docs theme changelog ${theme.docsChangelogVersion || '(missing)'} does not match ${theme.version}`
    );
  if (validPlugin && plugin.sourceVersion !== plugin.version)
    problems.push(`plugin Plugin::VERSION ${plugin.sourceVersion || '(missing)'} does not match ${plugin.version}`);
  if (validPlugin && plugin.stableTag !== plugin.version)
    problems.push(`plugin Stable tag ${plugin.stableTag || '(missing)'} does not match ${plugin.version}`);
  if (validPlugin && plugin.changelogVersion !== plugin.version)
    problems.push(`plugin changelog ${plugin.changelogVersion || '(missing)'} does not match ${plugin.version}`);
  if (validPlugin && plugin.docsChangelogVersion !== plugin.version)
    problems.push(
      `release docs Resume changelog ${plugin.docsChangelogVersion || '(missing)'} does not match ${plugin.version}`
    );
  if (!theme.coreRange) problems.push('theme joo-theme.json has no core compatibility range');
  if (!theme.readmeLinksChangelog) problems.push('theme README.md must link to CHANGELOG.md');
  if (!plugin.readmeLinksChangelog) problems.push('plugin readme.txt must include a changelog section');

  for (const [product, values] of Object.entries({ theme, plugin })) {
    if (!WP_VERSION.test(values.requiresAtLeast)) problems.push(`${product} minimum WordPress version is invalid`);
    if (!WP_VERSION.test(values.testedUpTo)) problems.push(`${product} Tested up to version is invalid`);
    if (
      WP_VERSION.test(values.testedUpTo) &&
      WP_VERSION.test(values.requiresAtLeast) &&
      !versionAtLeast(values.testedUpTo, values.requiresAtLeast)
    ) {
      problems.push(
        `${product} Tested up to ${values.testedUpTo} is below Requires at least ${values.requiresAtLeast}`
      );
    }
    if (!/^\d+\.\d+(?:\.\d+)?$/.test(values.requiresPhp)) problems.push(`${product} minimum PHP version is invalid`);
    if (!/GPL/i.test(values.license)) problems.push(`${product} license is not GPL compatible`);
  }
  if (plugin.readmeRequiresAtLeast !== plugin.requiresAtLeast)
    problems.push('plugin readme Requires at least differs from its plugin header');
  if (plugin.readmeRequiresPhp !== plugin.requiresPhp)
    problems.push('plugin readme Requires PHP differs from its plugin header');
  if (theme.requiresAtLeast !== plugin.requiresAtLeast)
    problems.push('theme and Resume plugin minimum WordPress versions differ');
  if (theme.requiresPhp !== plugin.requiresPhp) problems.push('theme and Resume plugin minimum PHP versions differ');
  if (theme.testedUpTo !== plugin.testedUpTo) problems.push('theme and Resume plugin Tested up to versions differ');
  if (!preview.width || !preview.height) problems.push('ThemeForest primary preview is missing or is not a PNG');
  else if (preview.width !== 590 || preview.height !== 300)
    problems.push(`ThemeForest primary preview must be 590x300, found ${preview.width}x${preview.height}`);
  if (latestWordPress && theme.testedUpTo !== latestWordPress.split('.').slice(0, 2).join('.')) {
    problems.push(`Tested up to ${theme.testedUpTo} but the release gates ran on WordPress ${latestWordPress}`);
  }
  return problems;
}

/** Runs static theme and release metadata checks for a checkout. */
export function checkRelease(root, themeSlug = 'jooservices', options = {}) {
  const metadata = readReleaseMetadata(root, themeSlug);
  const problems = releaseProblems(metadata, options);
  problems.push(...themeCheck(join(root, 'themes', themeSlug)));
  return problems;
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = process.argv.slice(2);
  const slug = args.includes('--theme') ? args[args.indexOf('--theme') + 1] : 'jooservices';
  const wp = args.includes('--wp') ? args[args.indexOf('--wp') + 1] : '';
  const found = checkRelease(fromRoot(), slug, { latestWordPress: wp });
  if (found.length) {
    console.error(`release check failed:\n  ${found.join('\n  ')}`);
    process.exit(1);
  }
  const { theme, plugin } = readReleaseMetadata(fromRoot(), slug);
  console.log(`ok ${slug} ${theme.version} + ${plugin.name} ${plugin.version}; preview 590x300; GPL compatible`);
}
