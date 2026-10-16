// @vitest-environment node
import { mkdtempSync, mkdirSync, readFileSync, rmSync, statSync, utimesSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { basename, join } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';
import {
  ARCHIVE_EPOCH,
  archiveEntries,
  normalizeTimestamps,
  sortedFiles,
  sortedRelativeFiles,
} from './lib/reproducible.mjs';

const roots = [];
function root() {
  const path = mkdtempSync(join(tmpdir(), 'jooservices-repro-'));
  roots.push(path);
  return path;
}

afterEach(() => {
  for (const path of roots.splice(0)) rmSync(path, { recursive: true, force: true });
});

describe('reproducible build helpers', () => {
  it('lists files in stable sorted order and handles a missing directory', () => {
    const dir = root();
    mkdirSync(join(dir, 'z'), { recursive: true });
    writeFileSync(join(dir, 'z', 'b.txt'), 'b');
    writeFileSync(join(dir, 'a.txt'), 'a');
    expect(sortedRelativeFiles(dir)).toEqual(['a.txt', 'z/b.txt']);
    expect(archiveEntries(dir)).toEqual([`${basename(dir)}/a.txt`, `${basename(dir)}/z/b.txt`]);
    expect(archiveEntries(dir, { includeRoot: false })).toEqual(['a.txt', 'z/b.txt']);
    expect(sortedFiles(join(dir, 'missing'))).toEqual([]);
  });

  it('normalizes file and directory timestamps to the ZIP epoch', () => {
    const dir = root();
    mkdirSync(join(dir, 'nested'), { recursive: true });
    const file = join(dir, 'nested', 'file.txt');
    writeFileSync(file, 'content');
    const different = new Date('2026-01-01T00:00:00Z');
    utimesSync(file, different, different);
    normalizeTimestamps(dir);
    expect(readFileSync(file, 'utf8')).toBe('content');
    expect(Math.floor(statSync(file).mtimeMs)).toBe(Math.floor(ARCHIVE_EPOCH.getTime()));
    expect(Math.floor(statSync(join(dir, 'nested')).mtimeMs)).toBe(Math.floor(ARCHIVE_EPOCH.getTime()));
  });
});
