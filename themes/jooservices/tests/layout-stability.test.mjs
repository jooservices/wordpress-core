// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';

// Static guard for the layout-shift sources behind JOOWP-301. The runtime proof is the CLS budget in
// tests/e2e/specs/matrix.spec.ts (make test-matrix THEME=jooservices); these checks fail fast without a browser.
const css = readFileSync('themes/jooservices/assets/src/css/components/terminal.css', 'utf8');
const lazy = readFileSync('packages/theme-core/src/Lazy/FragmentLazy.php', 'utf8');

describe('jooservices layout stability', () => {
  it('reserves the terminal dock column on desktop so it cannot push the content when it upgrades', () => {
    const desktop = css.slice(css.indexOf('@media (min-width: 901px)'), css.indexOf('@media (max-width: 900px)'));
    expect(desktop).toMatch(/\.joo-term\s*{[^}]*\bwidth:\s*44px;[^}]*\bmin-width:\s*44px;/s);
    expect(desktop).toMatch(/\.joo-term\s*{[^}]*\bheight:\s*min\(100vh,\s*780px\);/s);
  });

  it('reserves the height of lazy fragments before they load', () => {
    expect(lazy).toContain('display:block;min-block-size:');
  });
});
