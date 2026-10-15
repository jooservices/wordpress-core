// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { hooksByModule, hooksPage, settingsPage } from './docs-reference.mjs';

describe('docs-reference', () => {
  it('groups hooks by module', () => {
    expect(hooksByModule(['joo/seo/title', 'joo/seo/yield', 'joo/lazy/policy'])).toEqual({
      seo: ['joo/seo/title', 'joo/seo/yield'],
      lazy: ['joo/lazy/policy'],
    });
  });
  it('renders hooks and settings', () => {
    expect(hooksPage({ hooks: ['joo/seo/title'] })).toContain('`joo/seo/title`');
    const page = settingsPage({
      settings: { 'a.b': { type: 'boolean', default: true, group: 'general', title: 'T', description: 'D | x' } },
    });
    expect(page).toContain('| `a.b` | boolean | `true` | general | D / x |');
  });
});
