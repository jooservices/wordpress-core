// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { phpProblems, renderMarkdown, skipReason } from './matrix.mjs';

describe('matrix helpers', () => {
  it('skips licensed plugins without a zip and records why', () => {
    expect(skipReason({ source: 'manual', slug: '' }, { hasZip: false })).toMatch(/not tested/);
    expect(skipReason({ source: 'manual', slug: '' }, { hasZip: true })).toBeNull();
    expect(skipReason({ source: 'wporg', slug: 'x' }, { hasZip: false })).toBeNull();
  });
  it('finds PHP problems in a log excerpt', () => {
    const log = '[a] PHP Notice:  x\n[b] harmless\n[c] PHP Fatal error:  y';
    expect(phpProblems(log)).toHaveLength(2);
  });
  it('renders a table with counts', () => {
    const md = renderMarkdown({
      theme: 't',
      date: 'd',
      counts: { pass: 1, fail: 0, skipped: 1 },
      results: [
        { key: 'a', name: 'A', result: 'PASS', notes: [] },
        { key: 'b', name: 'B', result: 'SKIPPED', notes: ['why | x'] },
      ],
    });
    expect(md).toContain('1 pass, 0 fail, 1 skipped');
    expect(md).toContain('why / x');
  });
});
