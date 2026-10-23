// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { readFileSync, readdirSync } from 'node:fs';

// Static guards for the cross-engine rules of JOOWP-204; the runtime proof is tests/e2e/cross-engine.spec.ts.
const root = 'themes/jooservices/';
const sources = ['assets/src/css/components', 'assets/critical'].flatMap((dir) =>
  readdirSync(root + dir)
    .filter((name) => name.endsWith('.css'))
    .map((name) => [`${dir}/${name}`, readFileSync(`${root}${dir}/${name}`, 'utf8')])
);
sources.push(['assets/editor.css', readFileSync(`${root}assets/editor.css`, 'utf8')]);

/** Removes every `@supports selector(:has(*)) { ... }` block so only unguarded :has() remains. */
const withoutGuarded = (css) => {
  const marker = '@supports selector(:has(*))';
  let out = css.replace(/\/\*[\s\S]*?\*\//g, '');
  for (let at = out.indexOf(marker); at !== -1; at = out.indexOf(marker)) {
    let depth = 0;
    let end = out.indexOf('{', at);
    for (; end < out.length; end++) {
      if (out[end] === '{') depth++;
      if (out[end] === '}' && --depth === 0) break;
    }
    out = out.slice(0, at) + out.slice(end + 1);
  }
  return out;
};

describe('jooservices cross-engine rules', () => {
  it('guards every :has() with @supports selector(:has(*))', () => {
    for (const [file, css] of sources) {
      expect(withoutGuarded(css), file).not.toContain(':has(');
    }
  });

  it('does not use the popover API', () => {
    const files = ['parts', 'patterns', 'templates'].flatMap((dir) =>
      readdirSync(root + dir).map((name) => [`${dir}/${name}`, readFileSync(`${root}${dir}/${name}`, 'utf8')])
    );
    for (const [file, text] of [...files, ...sources]) {
      expect(text, file).not.toMatch(/\bpopover(target)?\b|:popover-open/);
    }
  });

  it('keeps the sticky dock aligned to the flex start', () => {
    const css = readFileSync(`${root}assets/src/css/components/terminal.css`, 'utf8');
    expect(css).toMatch(/position:\s*sticky;[^}]*align-self:\s*flex-start;/s);
  });
});
