// Builds JS (esbuild, ES modules) and CSS (Lightning CSS) with content-hashed names and writes assets.json.
// Usage: node tools/build-assets.mjs --root packages/theme-core [--src resources] [--out resources/build] [--watch]
//   JS entries:  <src>/js/*.js and <src>/blocks/*/view.js   -> logical names "name" and "blocks/<slug>/view"
//   CSS entries: <src>/css/*.css and <src>/blocks/*/style.css -> logical names "name" and "blocks/<slug>/style"
// Size guards (gzip): JS entry <= 25 KB, CSS entry <= 30 KB; override in <root>/build.config.json.
import { build } from 'esbuild';
import { bundleAsync } from 'lightningcss';
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { basename, dirname, join, relative, resolve } from 'node:path';
import { gzipSync } from 'node:zlib';
import { ROOT } from './lib/paths.mjs';

const args = new Map();
for (let i = 2; i < process.argv.length; i += 1) {
  const arg = process.argv[i];
  if (arg.startsWith('--'))
    args.set(
      arg.slice(2),
      process.argv[i + 1]?.startsWith('--') || process.argv[i + 1] === undefined ? true : process.argv[++i]
    );
}
const root = resolve(ROOT, String(args.get('root') ?? 'packages/theme-core'));
const src = join(root, String(args.get('src') ?? 'resources'));
const out = join(root, String(args.get('out') ?? 'resources/build'));
const config = {
  maxJsGzip: 25 * 1024,
  maxCssGzip: 30 * 1024,
  ...(existsSync(join(root, 'build.config.json'))
    ? JSON.parse(readFileSync(join(root, 'build.config.json'), 'utf8'))
    : {}),
};
// Chrome 111+, Safari 16.4+, Firefox 117+ (container queries, nesting, color-mix baseline).
const targets = { chrome: 111 << 16, safari: (16 << 16) | (4 << 8), firefox: 117 << 16 };

/** @param {string} dir @param {RegExp} re @returns {string[]} */
function list(dir, re) {
  return existsSync(dir)
    ? readdirSync(dir)
        .filter((f) => re.test(f))
        .sort()
        .map((f) => join(dir, f))
    : [];
}
/** @param {string} dir @param {string} file @returns {string[]} */
function blockEntries(dir, file) {
  return existsSync(dir)
    ? readdirSync(dir)
        .sort()
        .map((d) => join(dir, d, file))
        .filter((p) => existsSync(p))
    : [];
}
const hash = (buffer) => createHash('sha256').update(buffer).digest('hex').slice(0, 8);

rmSync(out, { recursive: true, force: true });
mkdirSync(out, { recursive: true });
const assets = {};
const problems = [];

// JS
const jsEntries = [
  ...list(join(src, 'js'), /\.js$/).filter((f) => !f.endsWith('.test.js')),
  ...blockEntries(join(src, 'blocks'), 'view.js'),
];
const logicalJs = (file) =>
  file.includes('/blocks/') ? `blocks/${basename(dirname(file))}/view` : basename(file, '.js');
if (jsEntries.length) {
  const result = await build({
    entryPoints: jsEntries.map((f) => ({ in: f, out: logicalJs(f) })),
    outdir: join(out, 'js'),
    bundle: true,
    splitting: true,
    format: 'esm',
    target: 'es2022',
    minify: true,
    legalComments: 'none',
    external: ['joo-runtime'], // provided by the WordPress script-module import map
    entryNames: '[dir]/[name]-[hash]',
    chunkNames: 'chunks/[name]-[hash]',
    metafile: true,
    write: true,
    logLevel: 'warning',
  });
  for (const [file, meta] of Object.entries(result.metafile.outputs)) {
    if (!meta.entryPoint) continue;
    const abs = resolve(file);
    const logical = logicalJs(resolve(ROOT, meta.entryPoint));
    const buffer = readFileSync(abs);
    const gzip = gzipSync(buffer).length;
    assets[logical] = { type: 'js', file: relative(out, abs), size: buffer.length, gzip };
    if (gzip > config.maxJsGzip) problems.push(`${logical}: ${gzip} B gzip exceeds ${config.maxJsGzip} B`);
  }
}

// CSS
const cssEntries = [...list(join(src, 'css'), /\.css$/), ...blockEntries(join(src, 'blocks'), 'style.css')];
const logicalCss = (file) =>
  file.includes('/blocks/') ? `blocks/${basename(dirname(file))}/style` : basename(file, '.css');
for (const file of cssEntries) {
  const { code } = await bundleAsync({ filename: file, minify: true, targets });
  const name = logicalCss(file);
  const target = join(out, 'css', `${name}-${hash(code)}.css`);
  mkdirSync(dirname(target), { recursive: true });
  writeFileSync(target, code);
  const gzip = gzipSync(code).length;
  assets[name] = { type: 'css', file: relative(out, target), size: code.length, gzip };
  if (gzip > config.maxCssGzip) problems.push(`${name}: ${gzip} B gzip exceeds ${config.maxCssGzip} B`);
}

const manifest = {
  version: 1,
  assets: Object.fromEntries(Object.entries(assets).sort(([a], [b]) => a.localeCompare(b))),
};
writeFileSync(join(out, 'assets.json'), JSON.stringify(manifest, null, 2) + '\n');
const rows = Object.entries(manifest.assets).map(
  ([k, v]) =>
    `${v.type.padEnd(3)} ${k.padEnd(34)} ${String(v.size).padStart(7)} B  ${String(v.gzip).padStart(6)} B gzip`
);
console.log(`built ${rows.length} asset(s) into ${relative(ROOT, out)}\n${rows.join('\n')}`);
if (problems.length) {
  console.error(`\nSize budget exceeded:\n${problems.join('\n')}`);
  process.exit(1);
}
void statSync;
