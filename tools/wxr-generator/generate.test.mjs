// @vitest-environment node
import { XMLParser } from 'fast-xml-parser';
import { describe, expect, it } from 'vitest';
import { build } from './generate.mjs';
import { mulberry32 } from './lib/prng.mjs';

describe('prng', () => {
  it('is deterministic per seed', () => {
    const a = mulberry32(7);
    const b = mulberry32(7);
    expect([a(), a(), a()]).toEqual([b(), b(), b()]);
    expect(mulberry32(8)()).not.toBe(mulberry32(7)());
  });
});

describe('wxr generator', () => {
  const one = build(42);
  const two = build(42);

  it('is byte-identical across runs', () => {
    expect(one.xml).toBe(two.xml);
    expect(one.media.map((m) => m.buffer.toString('base64'))).toEqual(
      two.media.map((m) => m.buffer.toString('base64'))
    );
  });

  it('differs with another seed', () => {
    expect(build(43).xml).not.toBe(one.xml);
  });

  it('produces valid WXR matching the manifest', () => {
    const doc = new XMLParser({ ignoreAttributes: false, parseTagValue: false }).parse(one.xml);
    const items = [].concat(doc.rss.channel.item);
    expect(doc.rss.channel['wp:wxr_version']).toBe('1.2');
    expect(items).toHaveLength(one.manifest.expected.items);
    const posts = items.filter((i) => i['wp:post_type'] === 'post');
    const c = one.manifest.counts;
    expect(posts).toHaveLength(c.posts.publish + c.posts.draft + c.posts.private + c.posts.protected);
    expect(items.filter((i) => i['wp:post_type'] === 'page')).toHaveLength(3);
    expect(items.filter((i) => i['wp:post_type'] === 'joo_company')).toHaveLength(6);
    expect(new Set(items.map((i) => i['wp:post_id'])).size).toBe(items.length);
  });

  it('covers the planned scenarios', () => {
    const c = one.manifest.counts;
    expect(c.featured).toBe(8);
    expect(c.vietnamese).toBe(4);
    expect(c.comments).toBeGreaterThanOrEqual(60);
    expect(one.xml).toContain('<wp:post_password><![CDATA[demo]]>');
    expect((one.xml.match(/<h2 class="wp-block-heading">/g) ?? []).length).toBeGreaterThanOrEqual(20);
  });

  it('emits valid PNGs', () => {
    for (const m of one.media) expect(m.buffer.subarray(0, 8).toString('hex')).toBe('89504e470d0a1a0a');
  });
});
