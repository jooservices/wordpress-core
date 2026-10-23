// Builds the installable zip of a theme: built assets, the runtime part of the core in core/, no tests or sources.
// Usage: node tools/build-theme.mjs --theme jooservices
import { execFileSync } from 'node:child_process';
import { cpSync, existsSync, mkdirSync, readFileSync, rmSync, statSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fromRoot } from './lib/paths.mjs';
import { normalizeTimestamps, sortedRelativeFiles } from './lib/reproducible.mjs';
import { classify, readPatterns, walk } from './runtime-bundle.mjs';
import { build as buildPlugin } from './build-plugin.mjs';

/** Theme paths that never ship. */
export const EXCLUDED = [
  /^core(\/|$)/, // replaced by the runtime copy
  /^node_modules(\/|$)/,
  /^tests(\/|$)/,
  /^design(\/|$)/,
  /^demo(\/|$)/,
  /^release-assets(\/|$)/,
  /^assets\/src(\/|$)/,
  /^package\.json$/,
  /^budgets\.json$/,
  /(^|\/)\.DS_Store$/,
  /\.map$/,
];

/** Files of the theme directory that belong in the zip (relative paths). */
export function themeFiles(dir) {
  return walk(dir).filter((file) => !EXCLUDED.some((re) => re.test(file)));
}

/** Reads `Header: value` pairs of style.css. */
export function styleHeader(css) {
  return Object.fromEntries([...css.matchAll(/^([A-Za-z ]+):\s*(.+)$/gm)].map((m) => [m[1].trim(), m[2].trim()]));
}

/** Copy a built companion-plugin zip into the installable theme package. */
export function addBundledPlugin(stage, pluginZip, slug) {
  if (!existsSync(pluginZip)) throw new Error(`bundled plugin archive is missing: ${pluginZip}`);
  const destination = join(stage, 'plugins', `${slug}.zip`);
  mkdirSync(dirname(destination), { recursive: true });
  cpSync(pluginZip, destination);
  return destination;
}

export function buildTheme(
  slug = 'jooservices',
  { outDir = fromRoot('artifacts/package'), bundledPluginSlug = null, zip = true } = {}
) {
  const themeDir = fromRoot('themes', slug);
  const coreDir = fromRoot('packages/theme-core');
  const header = styleHeader(readFileSync(join(themeDir, 'style.css'), 'utf8'));
  const version = header.Version;
  if (!version) throw new Error('style.css has no Version header');
  const out = resolve(outDir);
  mkdirSync(out, { recursive: true });
  const stage = join(out, slug);
  rmSync(stage, { recursive: true, force: true });
  mkdirSync(stage, { recursive: true });

  // Fresh builds so the zip never carries stale hashed files.
  execFileSync('node', [fromRoot('tools/build-assets.mjs'), '--root', 'packages/theme-core'], { stdio: 'ignore' });
  execFileSync(
    'node',
    [fromRoot('tools/build-assets.mjs'), '--root', `themes/${slug}`, '--src', 'assets/src', '--out', 'assets/build'],
    { stdio: 'ignore' }
  );
  for (const file of themeFiles(themeDir)) {
    mkdirSync(join(stage, file, '..'), { recursive: true });
    cpSync(join(themeDir, file), join(stage, file));
  }
  const { allowed } = classify(walk(coreDir), readPatterns());
  for (const file of allowed) {
    mkdirSync(join(stage, 'core', file, '..'), { recursive: true });
    cpSync(join(coreDir, file), join(stage, 'core', file));
  }
  if (bundledPluginSlug) {
    const plugin = buildPlugin(bundledPluginSlug);
    addBundledPlugin(stage, plugin.zipPath, bundledPluginSlug);
  }

  // Checks of the staged tree.
  const problems = [];
  for (const required of [
    'style.css',
    'theme.json',
    'functions.php',
    'joo-theme.json',
    'core/core.php',
    'assets/build/assets.json',
    'templates/index.html',
  ]) {
    if (!existsSync(join(stage, required))) problems.push(`missing ${required}`);
  }
  if (!/^\d+\.\d+\.\d+$/.test(version)) problems.push(`Version "${version}" is not semver`);
  for (const file of walk(stage)) {
    if (/\.php$/.test(file)) {
      try {
        execFileSync('php', ['-l', join(stage, file)], { stdio: 'ignore' });
      } catch {
        problems.push(`php -l failed: ${file}`);
      }
    }
  }
  const coreFiles = walk(join(stage, 'core'));
  const rejected = classify(coreFiles, readPatterns()).rejected;
  if (rejected.length) problems.push(`unexpected core files: ${rejected.slice(0, 5).join(', ')}`);
  if (problems.length) throw new Error(`package FAILED:\n  ${problems.join('\n  ')}`);

  const files = walk(stage).length;
  if (!zip) return { slug, version, stage, files, size: 0, zipPath: '' };

  const zipPath = join(out, `${slug}-${version}.zip`);
  rmSync(zipPath, { force: true });
  normalizeTimestamps(stage);
  execFileSync('zip', ['-q', '-X', zipPath, ...sortedRelativeFiles(stage).map((file) => `${slug}/${file}`)], {
    cwd: out,
  });
  const size = statSync(zipPath).size;
  console.log(
    `built ${relative(fromRoot(), zipPath)}: ${files} files, ${(size / 1024).toFixed(0)} KB (core ${coreFiles.length} files)`
  );
  // The zip must open and list the theme folder at its root.
  const listing = execFileSync('unzip', ['-Z1', zipPath], { encoding: 'utf8' }).split('\n').filter(Boolean);
  if (!listing.every((entry) => entry.startsWith(`${slug}/`)))
    throw new Error('zip entries are not all below the theme folder');
  return { slug, version, stage, zipPath, files, size };
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = process.argv.slice(2);
  const slug = args.includes('--theme') ? args[args.indexOf('--theme') + 1] : 'jooservices';
  const outDir = args.includes('--out') ? args[args.indexOf('--out') + 1] : fromRoot('artifacts/package');
  const pluginArg = args.indexOf('--include-plugin');
  const bundledPluginSlug = pluginArg >= 0 ? args[pluginArg + 1] : null;
  if (pluginArg >= 0 && !bundledPluginSlug) throw new Error('--include-plugin requires a plugin slug');
  try {
    const result = buildTheme(slug, { outDir, bundledPluginSlug });
    console.log(
      `built ${relative(fromRoot(), result.zipPath)}: ${result.files} files, ${(result.size / 1024).toFixed(0)} KB (core ${walk(join(result.stage, 'core')).length} files)`
    );
  } catch (error) {
    console.error(`build-theme: ${error.message}`);
    process.exit(1);
  }
}
