import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { fromRoot } from '../../../../tools/lib/paths.mjs';

const root = fromRoot('packages', 'theme-core', 'resources');
const php = readFileSync(fromRoot('packages', 'theme-core', 'src', 'I18n', 'JsStrings.php'), 'utf8');
const mapped = new Set([...php.matchAll(/'([^']+)'\s*=>\s*__\(/gu)].map((m) => m[1]));

const sources = (dir) =>
  readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) return entry.name === '__fixtures__' ? [] : sources(path);
    const shipped = /\.js$/u.test(entry.name) && !/\.test\.js$|test-i18n\.js$/u.test(entry.name);
    const inScope = path.includes(`${join('resources', 'js')}`) || /view\.js$/u.test(entry.name);
    return shipped && inScope ? [path] : [];
  });

describe('JS strings go through the runtime config', () => {
  const files = [...sources(join(root, 'js')), ...sources(join(root, 'blocks'))];

  it('finds the shipped JavaScript sources', () => {
    expect(files.length).toBeGreaterThan(10);
  });

  it('has a PHP __() entry (JsStrings::map) for every t("key") used in JS', () => {
    const missing = [];
    for (const file of files) {
      const code = readFileSync(file, 'utf8');
      for (const [, key] of code.matchAll(/\bt\(\s*'([^']+)'/gu)) {
        if (!mapped.has(key)) missing.push(`${file.replace(root, '')}: ${key}`);
      }
    }
    expect(missing).toEqual([]);
  });
});
