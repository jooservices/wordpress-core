// Generate the licence-clean Tier B WordPress demo import archive.
// Usage: node tools/demo-package.mjs [--seed 42] [--out artifacts/package/jooservices-demo-content.zip]
import { execFileSync } from 'node:child_process';
import { mkdirSync, rmSync, writeFileSync, statSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { build } from './wxr-generator/generate.mjs';
import { createDemoContent } from './release.mjs';
import { archiveEntries, normalizeTimestamps } from './lib/reproducible.mjs';
import { ROOT } from './lib/paths.mjs';

const flag = (name, fallback) => {
  const args = process.argv.slice(2);
  return args.includes(name) ? args[args.indexOf(name) + 1] : fallback;
};

export function writeGeneratedDemo(result, directory) {
  mkdirSync(join(directory, 'media'), { recursive: true });
  writeFileSync(join(directory, 'content.xml'), result.xml);
  for (const item of result.media) writeFileSync(join(directory, 'media', item.name), item.buffer);
  writeFileSync(join(directory, 'menus.json'), `${JSON.stringify(result.menus, null, 2)}\n`);
  writeFileSync(join(directory, 'settings.json'), `${JSON.stringify(result.settings, null, 2)}\n`);
  writeFileSync(join(directory, 'manifest.json'), `${JSON.stringify(result.manifest, null, 2)}\n`);
}

export function buildDemoPackage({ seed = 42, output = 'artifacts/package/jooservices-demo-content.zip' } = {}) {
  const tempRoot = join(ROOT, 'artifacts', '.demo-package');
  const source = join(tempRoot, 'source');
  const content = join(tempRoot, 'content');
  const out = join(ROOT, output);
  rmSync(tempRoot, { recursive: true, force: true });
  mkdirSync(source, { recursive: true });
  mkdirSync(dirname(out), { recursive: true });
  writeGeneratedDemo(build(seed), source);
  createDemoContent(source, content);
  normalizeTimestamps(content);
  rmSync(out, { force: true });
  execFileSync('zip', ['-q', '-X', out, ...archiveEntries(content, { includeRoot: false })], { cwd: content });
  const size = statSync(out).size;
  if (size >= 40 * 1024 * 1024) throw new Error(`demo package exceeds 40 MB (${size} bytes)`);
  rmSync(tempRoot, { recursive: true, force: true });
  return { output: relative(ROOT, out), size };
}

if (import.meta.url === `file://${process.argv[1]}`) {
  try {
    const result = buildDemoPackage({
      seed: Number(flag('--seed', '42')),
      output: flag('--out', 'artifacts/package/jooservices-demo-content.zip'),
    });
    console.log(`wrote ${result.output} (${Math.ceil(result.size / 1024)} KB)`);
  } catch (error) {
    console.error(`demo package failed: ${error.message}`);
    process.exit(1);
  }
}
