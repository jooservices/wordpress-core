// One-time mechanical rename of the private company plugin to its product name.
// Run from the repository root: node tools/rename-resume-plugin.mjs
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, renameSync, writeFileSync } from 'node:fs';
import { fromRoot } from './lib/paths.mjs';

const previousSlug = ['jooservices', 'content'].join('-');
const nextSlug = 'jooservices-resume';
const previousNamespace = ['JOOservices', 'Content'].join('\\');
const nextNamespace = ['JOOservices', 'Resume'].join('\\');
const previousNamespaceInString = ['JOOservices', 'Content'].join('\\\\');
const nextNamespaceInString = ['JOOservices', 'Resume'].join('\\\\');
const replacements = [
  [previousSlug, nextSlug],
  [previousNamespace, nextNamespace],
  [previousNamespaceInString, nextNamespaceInString],
  ['JOO_' + 'CONTENT', 'JOO_' + 'RESUME'],
  ['joo_' + 'content', 'joo_' + 'resume'],
  ['joo-' + 'content', 'joo-' + 'resume'],
  ['JOOservices ' + 'Content', 'JOOservices ' + 'Resume'],
];

const files = execFileSync('git', ['ls-files', '-z'], { encoding: 'utf8' }).split('\0').filter(Boolean);
let changed = 0;

for (const file of files) {
  const movedRelative = file.replaceAll(previousSlug, nextSlug);
  const originalPath = fromRoot(file);
  const absolute = existsSync(originalPath) ? originalPath : fromRoot(movedRelative);
  let source;
  try {
    source = readFileSync(absolute);
  } catch {
    continue;
  }
  if (source.includes(0)) continue;
  let text = source.toString('utf8');
  const original = text;
  for (const [before, after] of replacements) text = text.replaceAll(before, after);
  if (text !== original) {
    writeFileSync(absolute, text);
    changed += 1;
  }
}

const oldDirectory = fromRoot('plugins', previousSlug);
const newDirectory = fromRoot('plugins', nextSlug);
if (existsSync(oldDirectory)) renameSync(oldDirectory, newDirectory);

for (const file of files) {
  const previousDirectory = `plugins/${previousSlug}/`;
  if (!file.startsWith(previousDirectory)) continue;
  const currentPath = fromRoot(file.replace(previousDirectory, `plugins/${nextSlug}/`));
  const nextPath = fromRoot(file.replaceAll(previousSlug, nextSlug));
  if (currentPath !== nextPath && existsSync(currentPath)) renameSync(currentPath, nextPath);
}

console.log(`Renamed ${previousSlug} to ${nextSlug}; updated ${changed} tracked text files.`);
