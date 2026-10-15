// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { decode, isVietnamese, localName, plain, transform } from './live-import.mjs';

describe('live-import', () => {
  it('decodes entities and strips tags', () => {
    expect(decode('Tips &amp; Tricks &#8217;s')).toBe('Tips & Tricks ’s');
    expect(plain('<p>Hello <b>you</b>&hellip;</p>')).toBe('Hello you …');
  });
  it('detects Vietnamese and builds flat local names', () => {
    expect(isVietnamese('Hướng dẫn chọn giấy phép')).toBe(true);
    expect(isVietnamese('Plain English text')).toBe(false);
    expect(localName('https://x.test/wp-content/uploads/2026/09/a b.jpeg')).toBe('2026-09-a-b.jpeg');
  });
  it('turns fetched data into a WXR with local image placeholders', () => {
    const data = {
      categories: [
        { id: 1, slug: 'blog', parent: 0, count: 1, name: 'Blog', description: '' },
        { id: 2, slug: 'news', parent: 1, count: 1, name: 'News', description: '' },
      ],
      tags: [{ id: 5, slug: 'php', name: 'php', description: '' }],
      pages: [],
      media: [{ id: 9, source_url: 'https://x.test/wp-content/uploads/2026/09/cover.jpg' }],
      posts: [
        {
          id: 7,
          slug: 'hello',
          link: 'https://x.test/hello/',
          date: '2026-09-05T08:00:00',
          status: 'publish',
          title: { rendered: 'Xin chào' },
          excerpt: { rendered: '<p>Tóm tắt</p>' },
          content: {
            rendered: '<p><img src="https://x.test/wp-content/uploads/2026/09/cover-300x200.jpg" srcset="a 1x"></p>',
          },
          categories: [2],
          tags: [5],
          featured_media: 9,
        },
      ],
    };
    const out = transform(data, { companies: [], settings: { profile: {} }, profile: {} });
    expect(out.xml).toContain('__MEDIA_BASE__/2026-09-cover-300x200.jpg');
    expect(out.xml).not.toContain('srcset');
    expect(out.xml).toContain('<wp:category_parent><![CDATA[blog]]>');
    expect(out.xml).toContain('_joo_lang');
    expect(out.xml).toContain('<wp:status><![CDATA[publish]]></wp:status>');
    const draft = transform(
      { ...data, posts: [{ ...data.posts[0], status: 'draft' }] },
      { companies: [], settings: { profile: {} }, profile: {} }
    );
    expect(draft.xml).toContain('<wp:status><![CDATA[draft]]></wp:status>');
    expect(draft.manifest.counts.drafts).toBe(1);
    expect(draft.manifest.counts.featured).toBe(0);
    expect(out.manifest.counts).toMatchObject({ posts: 1, vietnamese: 1, featured: 1 });
    expect(out.media.map((m) => m.name)).toContain('2026-09-cover.jpg');
  });
});
