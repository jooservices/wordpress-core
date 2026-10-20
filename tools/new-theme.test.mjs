// @vitest-environment node
import { cpSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { plan, registerTooling, rewrite, studly } from './new-theme.mjs';
import { ROOT } from './lib/paths.mjs';

const ctx = { from: 'starter', to: 'acme-labs', title: 'Acme Labs' };

describe('new-theme', () => {
  it('converts slugs', () => {
    expect(studly('acme-labs')).toBe('AcmeLabs');
  });

  it('rewrites slug, title, namespace and text domain', () => {
    const out = rewrite(
      "namespace JOOservices\\Starter\\Tests; __( 'Hi', 'starter' ); Theme Name: Starter; class StarterTest",
      ctx
    );
    expect(out).toBe(
      "namespace JOOservices\\AcmeLabs\\Tests; __( 'Hi', 'acme-labs' ); Theme Name: Acme Labs; class AcmeLabsTest"
    );
    expect(out.toLowerCase()).not.toContain('starter');
  });

  it('plans files with renamed paths and skips core and build output', () => {
    const root = mkdtempSync(join(tmpdir(), 'joo-theme-'));
    const base = join(root, 'themes/starter');
    mkdirSync(join(base, 'languages'), { recursive: true });
    mkdirSync(join(base, 'core'), { recursive: true });
    writeFileSync(join(base, 'style.css'), '/*\nTheme Name: Starter\nText Domain: starter\n*/');
    writeFileSync(join(base, 'languages/starter.pot'), 'x');
    writeFileSync(join(base, 'core/skip.php'), 'x');
    const files = plan({ root, name: 'acme-labs' });
    expect(files.map((f) => f.to.replace(root, ''))).toEqual([
      '/themes/acme-labs/languages/acme-labs.pot',
      '/themes/acme-labs/style.css',
    ]);
    expect(files[1].text).toContain('Theme Name: Acme Labs');
  });

  it('rejects bad slugs and existing targets', () => {
    expect(() => plan({ name: 'Bad Name' })).toThrow(/invalid slug/);
    expect(() => plan({ name: 'starter' })).toThrow(/already exists/);
  });

  it('registers the text domain and inc path once', () => {
    const files = {
      phpcs: '\t\t\t\t<element value="starter"/>\n\t\t\t\t<element value="jooservices-resume"/>\n',
      deptrac: '  paths:\n    - ./themes/starter/inc\n  analyser:\n',
    };
    const once = registerTooling('acme', files);
    expect(once.phpcs).toContain('<element value="acme"/>');
    expect(once.deptrac).toContain('./themes/acme/inc');
    expect(registerTooling('acme', once)).toEqual(once);
  });

  describe('the steps the guide relies on, planned from the real starter', () => {
    const root = mkdtempSync(join(tmpdir(), 'joo-theme-plan-'));
    cpSync(join(ROOT, 'themes/starter'), join(root, 'themes/starter'), { recursive: true });
    const files = plan({ root, name: 'acme', title: 'Acme Co' });
    const byPath = (suffix) => files.find((f) => f.to.endsWith(suffix));

    it('leaves no starter name in any file name or text', () => {
      for (const f of files) {
        expect(f.to.toLowerCase()).not.toContain('starter');
        if (f.text !== null) expect(f.text.toLowerCase(), f.to).not.toContain('starter');
      }
    });

    it('names the integration test file after its class so PSR-4 and PHPCS pass', () => {
      const test = byPath('/tests/Integration/AcmeTest.php');
      expect(test?.text).toContain('final class AcmeTest');
    });

    it('declares the workspace package that the lockfile step needs', () => {
      expect(JSON.parse(byPath('/package.json').text).name).toBe('@jooservices/theme-acme');
    });

    it('keeps the theme bootstrap within the 50-line budget the guide states', () => {
      expect(byPath('/functions.php').text.trimEnd().split('\n').length).toBeLessThanOrEqual(50);
    });

    it('registers tooling in the real phpcs and deptrac files without breaking them', () => {
      const phpcs = readFileSync(join(ROOT, 'phpcs.xml.dist'), 'utf8');
      const deptrac = readFileSync(join(ROOT, 'deptrac.yaml'), 'utf8');
      const next = registerTooling('acme', { phpcs, deptrac });
      expect(next.phpcs).toContain('<element value="acme"/>');
      expect(next.deptrac).toContain('./themes/acme/inc');
    });
  });
});
