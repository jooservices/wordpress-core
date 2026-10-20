// Runtime bundle of the core: which files ship in a theme zip.
// Usage: node tools/runtime-bundle.mjs list            files of the core that match packages/theme-core/.runtime-files
//        node tools/runtime-bundle.mjs check <dir>     a built core copy: only allowed files, no source maps, size limit
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fromRoot } from './lib/paths.mjs';
import { readCodeBudgets } from './lib/perf-budgets.mjs';

const MAX_BYTES = readCodeBudgets().runtimeBundle.maxBytes;
const coreDir = fromRoot('packages/theme-core');

/** Reads the allow list. */
export function readPatterns(file = join(coreDir, '.runtime-files')) {
  return readFileSync(file, 'utf8')
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line && !line.startsWith('#'));
}

/** Glob to RegExp: `**` any depth, `*` anything inside one path part. */
export function globToRegExp(glob) {
  const escaped = glob
    .replace(/[.+^${}()|[\]\\]/g, '\\$&')
    .replace(/\*\*\//g, '__DIRSTAR__')
    .replace(/\*\*/g, '__ANYSTAR__')
    .replace(/\*/g, '[^/]*')
    .replace(/__DIRSTAR__/g, '(?:.*/)?')
    .replace(/__ANYSTAR__/g, '.*');
  return new RegExp(`^${escaped}$`);
}

/** All files below a directory (relative paths). */
export function walk(dir, base = dir) {
  const out = [];
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name === 'node_modules') continue;
      out.push(...walk(full, base));
    } else out.push(relative(base, full));
  }
  return out.sort();
}

/**
 * Files of a directory that the allow list does not permit.
 * @returns {{allowed: string[], rejected: string[]}} Split of the files.
 */
export function classify(files, patterns) {
  const regexps = patterns.map(globToRegExp);
  const allowed = [];
  const rejected = [];
  for (const file of files)
    (regexps.some((re) => re.test(file)) && !file.endsWith('.map') ? allowed : rejected).push(file);
  return { allowed, rejected };
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const [command, target] = process.argv.slice(2);
  if (command === 'list') {
    const { allowed } = classify(walk(coreDir), readPatterns());
    const bytes = allowed.reduce((sum, file) => sum + statSync(join(coreDir, file)).size, 0);
    console.log(allowed.join('\n'));
    if (bytes > MAX_BYTES) {
      console.error(`FAIL runtime bundle is ${bytes} B, limit ${MAX_BYTES} B`);
      process.exitCode = 1;
    } else {
      console.error(`${allowed.length} files, ${bytes} B (limit ${MAX_BYTES} B)`);
    }
  } else if (command === 'check') {
    const dir = target ? join(process.cwd(), target) : '';
    if (!dir || !existsSync(dir)) {
      console.error('usage: node tools/runtime-bundle.mjs check <dir>');
      process.exit(2);
    }
    const files = walk(dir);
    const { rejected } = classify(files, readPatterns());
    const bytes = files.reduce((sum, file) => sum + statSync(join(dir, file)).size, 0);
    let failed = false;
    if (rejected.length) {
      failed = true;
      console.error(`FAIL ${rejected.length} unexpected file(s):\n  ${rejected.slice(0, 20).join('\n  ')}`);
    }
    if (bytes > MAX_BYTES) {
      failed = true;
      console.error(`FAIL core copy is ${(bytes / 1024).toFixed(0)} KB, limit ${MAX_BYTES / 1024} KB`);
    }
    if (!failed) console.log(`ok   ${files.length} files, ${(bytes / 1024).toFixed(0)} KB`);
    process.exit(failed ? 1 : 0);
  } else {
    console.error('usage: node tools/runtime-bundle.mjs list | check <dir>');
    process.exit(2);
  }
}
