// @vitest-environment node
import { mkdtempSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';
import {
  checkGeneratedFiles,
  hookRegistryDrift,
  hooksByModule,
  hooksFromPhp,
  hooksPage,
  settingsPage,
  surfaceRegistryDrift,
} from './docs-reference.mjs';

let root;

afterEach(() => {
  if (root) rmSync(root, { recursive: true, force: true });
  root = undefined;
});

describe('docs-reference', () => {
  it('groups hooks by module', () => {
    expect(hooksByModule(['joo/seo/title', 'joo/seo/yield', 'joo/lazy/should_lazy'])).toEqual({
      seo: ['joo/seo/title', 'joo/seo/yield'],
      lazy: ['joo/lazy/should_lazy'],
    });
  });

  it('extracts hook kinds and callback arguments from PHP calls', () => {
    const result = hooksFromPhp(
      `<?php
// Hooks::filter( 'joo/example/comment', $ignored );
$title = Hooks::filter( 'joo/seo/title', $title, $this->context() );
\\JOOservices\\ThemeCore\\Support\\Hooks::action( sprintf( 'joo/blocks/test/%s', $slug ), $registry );
`,
      'Fixture.php',
      ['joo/blocks/test/{slug}']
    );

    expect(result.errors).toEqual([]);
    expect(
      result.hooks.map(({ name, kind, arguments: hookArguments }) => ({ name, kind, arguments: hookArguments }))
    ).toEqual([
      { name: 'joo/seo/title', kind: 'filter', arguments: ['value', 'context'] },
      { name: 'joo/blocks/test/{slug}', kind: 'action', arguments: ['registry'] },
    ]);
  });

  it('renders hooks with their type and callback arguments', () => {
    const page = hooksPage([
      { name: 'joo/seo/title', kind: 'filter', arguments: ['value', 'context'] },
      { name: 'joo/core/ready', kind: 'action', arguments: ['container'] },
    ]);
    expect(page).toContain('- `joo/seo/title` — Filter; arguments: `$value`, `$context`.');
    expect(page).toContain('- `joo/core/ready` — Action; arguments: `$container`.');

    const settings = settingsPage({
      settings: { 'a.b': { type: 'boolean', default: true, group: 'general', title: 'T', description: 'D | x' } },
    });
    expect(settings).toContain('| `a.b` | boolean | `true` | general | D / x |');
  });

  it('fails when a hook from source is missing from the docs', () => {
    root = mkdtempSync(join(tmpdir(), 'jooservices-reference-'));
    mkdirSync(join(root, 'docs', 'reference'), { recursive: true });
    const hooks = [{ name: 'joo/seo/title', kind: 'filter', arguments: ['value', 'context'] }];
    const generated = hooksPage(hooks);
    const docsWithoutHook = generated.replace('- `joo/seo/title` — Filter; arguments: `$value`, `$context`.\n', '');
    writeFileSync(join(root, 'docs', 'reference', 'hooks.md'), docsWithoutHook);

    expect(checkGeneratedFiles({ 'docs/reference/hooks.md': generated }, root)).toEqual(['docs/reference/hooks.md']);
  });

  it('detects hook and surface naming-registry drift', () => {
    expect(hookRegistryDrift([{ name: 'joo/seo/title' }], ['joo/seo/title', 'joo/seo/robots'])).toEqual({
      missingFromRegistry: [],
      missingFromSource: ['joo/seo/robots'],
    });
    expect(surfaceRegistryDrift({ blocks: ['joo/current', 'joo/removed'] }, '"joo/current"')).toEqual([
      'blocks: joo/removed',
    ]);
  });
});
