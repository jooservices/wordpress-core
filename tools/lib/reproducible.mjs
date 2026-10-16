import { createHash } from 'node:crypto';
import { existsSync, readdirSync, utimesSync } from 'node:fs';
import { basename, join, relative, sep } from 'node:path';

export const ARCHIVE_EPOCH = new Date('1980-01-01T00:00:00.000Z');

/** Returns every file under a directory in stable lexical order. */
export function sortedFiles(root) {
  if (!existsSync(root)) return [];
  const files = [];
  const walk = (dir) => {
    for (const entry of readdirSync(dir, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name))) {
      const path = join(dir, entry.name);
      if (entry.isDirectory()) walk(path);
      else if (entry.isFile()) files.push(path);
    }
  };
  walk(root);
  return files.sort((a, b) => relative(root, a).localeCompare(relative(root, b)));
}

/** Sets file mtimes to the ZIP epoch so identical inputs create identical archives. */
export function normalizeTimestamps(root) {
  const directories = [];
  const walk = (dir) => {
    if (!existsSync(dir)) return;
    directories.push(dir);
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
      const path = join(dir, entry.name);
      if (entry.isDirectory()) walk(path);
      else if (entry.isFile()) utimesSync(path, ARCHIVE_EPOCH, ARCHIVE_EPOCH);
    }
  };
  walk(root);
  for (const dir of directories.reverse()) utimesSync(dir, ARCHIVE_EPOCH, ARCHIVE_EPOCH);
}

export function sha256(buffer) {
  return createHash('sha256').update(buffer).digest('hex');
}

/** Compares named byte artifacts and returns per-file evidence. */
export function compareArtifacts(first, second) {
  const names = [...new Set([...Object.keys(first), ...Object.keys(second)])].sort();
  return names.map((name) => {
    const firstBytes = first[name];
    const secondBytes = second[name];
    const firstHash = firstBytes ? sha256(firstBytes) : '';
    const secondHash = secondBytes ? sha256(secondBytes) : '';
    return {
      name: name.split(sep).join('/'),
      firstHash,
      secondHash,
      identical: Boolean(firstBytes && secondBytes && firstHash === secondHash),
    };
  });
}

/** Reads a stable, sorted list of archive input paths relative to root. */
export function sortedRelativeFiles(root) {
  return sortedFiles(root).map((path) => relative(root, path).split(sep).join('/'));
}

/** Creates stable ZIP entry names with or without a top-level directory. */
export function archiveEntries(root, { includeRoot = true } = {}) {
  const files = sortedRelativeFiles(root);
  return includeRoot ? files.map((file) => `${basename(root)}/${file}`) : files;
}
