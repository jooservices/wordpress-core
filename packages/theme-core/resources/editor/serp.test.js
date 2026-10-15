import { describe, expect, it } from 'vitest';
import api from './serp.js';

describe('serp preview', () => {
  it('counts surrogate pairs once', () => {
    expect(api.count('a🚀b')).toBe(3);
    expect(api.count('')).toBe(0);
    expect(api.count(undefined)).toBe(0);
  });

  it('keeps short text and clamps long text on a word boundary', () => {
    expect(api.clamp('short text', 70)).toBe('short text');
    const out = api.clamp('The quick brown fox jumps over the lazy dog again and again', 30);
    expect(out).toBe('The quick brown fox jumps…');
    expect(Array.from(out).length).toBeLessThanOrEqual(30);
  });

  it('never splits an emoji or a Vietnamese letter', () => {
    const out = api.clamp('🚀'.repeat(100), 20);
    expect(Array.from(out).length).toBe(20);
    expect(out.endsWith('…')).toBe(true);
    expect(api.clamp('Tối ưu hiệu năng '.repeat(10), 25)).toMatch(/…$/);
  });

  it('reports title and description states', () => {
    expect(api.serp({}).titleState).toBe('empty');
    expect(api.serp({ title: 'x'.repeat(40) }).titleState).toBe('ok');
    expect(api.serp({ title: 'x'.repeat(65) }).titleState).toBe('long');
    expect(api.serp({ title: 'x'.repeat(80) }).titleState).toBe('over');
    expect(api.serp({ description: 'x'.repeat(50) }).descState).toBe('short');
    expect(api.serp({ description: 'x'.repeat(140) }).descState).toBe('ok');
    expect(api.serp({ description: 'x'.repeat(200) }).descState).toBe('over');
  });

  it('formats the URL like a search result breadcrumb', () => {
    expect(api.serp({ url: 'https://jooservices.com/blog/post/' }).url).toBe('jooservices.com › blog › post');
  });

  it('clamps the displayed title and description', () => {
    const r = api.serp({ title: 'word '.repeat(30), description: 'sentence '.repeat(40) });
    expect(Array.from(r.title).length).toBeLessThanOrEqual(70);
    expect(Array.from(r.description).length).toBeLessThanOrEqual(160);
  });
});
