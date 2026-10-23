// @vitest-environment node
import { lstatSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { describe, expect, it } from 'vitest';
import { build, pluginHeader } from './build-plugin.mjs';
import { fromRoot } from './lib/paths.mjs';
import { walk } from './runtime-bundle.mjs';

const compatibilityNote = 'docs/modules/content.md';
const retiredSlug = ['jooservices', 'content'].join('-');
const retiredNames = [
  retiredSlug,
  ['JOOservices', 'Content'].join('\\'),
  ['JOOservices', 'Content'].join(String.fromCharCode(92).repeat(2)),
  ['JOO', 'CONTENT'].join('_'),
  ['joo', 'content'].join('_'),
  ['joo', 'content'].join('-'),
  ['JOOservices', 'Content'].join(' '),
];
const ignoredDirectories = new Set(['.git', 'artifacts', 'node_modules', 'vendor']);

function filesUnder(directory) {
  const files = [];
  for (const entry of readdirSync(directory)) {
    if (entry === '.env' || entry.startsWith('.env.')) continue;
    const path = join(directory, entry);
    if (lstatSync(path).isSymbolicLink()) continue;
    if (statSync(path).isDirectory()) {
      if (!ignoredDirectories.has(entry)) files.push(...filesUnder(path));
      continue;
    }
    files.push(path);
  }
  return files;
}

function staleIdentifiers(path) {
  const source = readFileSync(path);
  if (source.includes(0)) return [];
  const text = source.toString('utf8');
  const pathName = relative(fromRoot(), path);
  return retiredNames.filter((name) => text.includes(name) || pathName.includes(name));
}

describe('Resume plugin rename', () => {
  it('keeps retired identifiers out of the worktree except the compatibility note', () => {
    const root = fromRoot();
    const notePath = join(root, compatibilityNote);
    const note = readFileSync(notePath, 'utf8');
    expect(note).toContain('Compatibility note:');
    expect(note.split(retiredSlug).length - 1).toBe(1);

    const failures = filesUnder(root)
      .filter((path) => relative(root, path) !== compatibilityNote)
      .flatMap((path) => staleIdentifiers(path).map((name) => `${relative(root, path)}: ${name}`));
    expect(failures).toEqual([]);
  });

  it('stages the renamed plugin with the product header and no test files', () => {
    const result = build('jooservices-resume', { zip: false });
    const header = pluginHeader(readFileSync(join(result.stage, 'jooservices-resume.php'), 'utf8'));
    const entries = walk(result.stage);

    expect(result.zipPath).toBe('');
    expect(header['Plugin Name']).toBe('JOOservices Resume');
    expect(header['Text Domain']).toBe('jooservices-resume');
    expect(entries).toContain('jooservices-resume.php');
    expect(entries.some((entry) => entry.startsWith('tests/'))).toBe(false);
  }, 15_000);
});
