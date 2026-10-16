// @vitest-environment node
import { describe, expect, it } from 'vitest';
import stylelint from 'stylelint';
import { readFileSync } from 'node:fs';

const config = JSON.parse(readFileSync('.stylelintrc.json', 'utf8'));
const lint = async (code) => {
  const { results } = await stylelint.lint({ code, config, codeFilename: 'virtual.css' });
  return results[0].warnings.map((w) => w.rule);
};

describe('stylelint RTL guard', () => {
  it.each([
    '.joo-a { margin-left: 4px; }',
    '.joo-a { padding-right: 4px; }',
    '.joo-a { border-left: 1px solid var(--joo-line); }',
    '.joo-a { border-top-left-radius: 4px; }',
    '.joo-a { left: 0; }',
    '.joo-a { text-align: right; }',
    '.joo-a { float: left; }',
  ])('rejects physical direction: %s', async (css) => {
    expect((await lint(css)).length).toBeGreaterThan(0);
  });

  it.each([
    '.joo-a { margin-inline-start: 4px; }',
    '.joo-a { padding-inline-end: 4px; }',
    '.joo-a { border-inline-start: 1px solid var(--joo-line); }',
    '.joo-a { border-start-start-radius: 4px; }',
    '.joo-a { inset-inline-end: 0; }',
    '.joo-a { text-align: start; }',
    '.joo-a { float: none; }',
  ])('accepts logical direction: %s', async (css) => {
    expect(await lint(css)).toEqual([]);
  });
});
