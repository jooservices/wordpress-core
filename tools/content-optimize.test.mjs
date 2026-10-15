// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { describe as describePost, optimize } from './content-optimize.mjs';
import { audit } from './content-audit.mjs';
import { cardHtml, titleSize } from './social-cards.mjs';

const base = () => ({
  categories: [
    { id: 1, slug: 'uncategorized', name: 'Uncategorized', count: 1, description: '' },
    { id: 2, slug: 'rd', name: 'Research and Developmment', count: 2, description: '' },
  ],
  tags: [
    { id: 10, slug: 'php', name: 'php', count: 2, description: '' },
    { id: 11, slug: '2019', name: '2019', count: 1, description: '' },
  ],
  pages: [],
  media: [
    {
      id: 5,
      slug: 'a',
      mime_type: 'image/jpeg',
      source_url: 'https://x.test/wp-content/uploads/a.jpg',
      alt_text: '',
      title: { rendered: 'a' },
      media_details: { width: 1600 },
    },
  ],
  posts: [
    {
      id: 1,
      slug: 'one',
      status: 'publish',
      title: { rendered: 'One' },
      excerpt: { rendered: '' },
      categories: [2],
      tags: [10, 11],
      featured_media: 0,
      content: {
        raw: '<h1>Hi</h1><p>Text [oai_citation:0‡x](https://y.test)</p>',
        rendered:
          '<h1>Hi</h1><p>Text [oai_citation:0‡x](https://y.test)</p><img class="wp-image-5" src="a.jpg" alt="">',
      },
      yoast_head_json: {},
    },
    {
      id: 2,
      slug: 'two',
      status: 'publish',
      title: { rendered: 'Two' },
      excerpt: { rendered: '<p>Short.</p>' },
      categories: [2],
      tags: [],
      featured_media: 5,
      content: { raw: '<p>Body</p>', rendered: '<p>Body</p>' },
      yoast_head_json: { description: 'Short.' },
    },
  ],
});

describe('content optimize', () => {
  it('fixes images, descriptions, tags, markers and headings', () => {
    const { data, cards, log } = optimize(base());
    const [one, two] = data.posts;
    expect(one.featured_media).toBe(5);
    expect(two.featured_media).not.toBe(5); // the shared image is replaced by a card
    expect(cards).toHaveLength(1);
    expect(cards[0].slug).toBe('two');
    expect(one.content.rendered).not.toContain('oai_citation');
    expect(one.content.rendered).toContain('<h2>Hi</h2>');
    expect(one.content.rendered).toContain('alt="One"');
    expect(one.tags).toEqual([10]);
    expect(data.categories[1].name).toBe('Research and Development');
    expect(two.yoast_head_json.description.length).toBeGreaterThan(0);
    expect(log.map((l) => l.key)).toContain('social card generated');
  });

  it('leaves the audit with no missing featured image, alt text or description', () => {
    const { data } = optimize(base());
    const ids = audit(data).map((f) => f.id);
    for (const gone of ['no-featured', 'featured-no-alt', 'desc-missing', 'junk-text', 'h1-in-content', 'junk-tag'])
      expect(ids).not.toContain(gone);
  });

  it('describes in at most 155 characters on a word boundary', () => {
    const text = 'word '.repeat(100);
    const out = describePost({ excerpt: { rendered: '' }, content: { rendered: `<p>${text}</p>` } });
    expect(out.length).toBeLessThanOrEqual(156);
    expect(out.endsWith('…')).toBe(true);
  });
});

describe('social cards', () => {
  it('shrinks long titles and escapes markup', () => {
    expect(titleSize('x'.repeat(120))).toBeLessThan(titleSize('short'));
    expect(cardHtml({ title: '<b>&</b>', category: 'AI', slug: 'a' })).toContain('&lt;b&gt;&amp;&lt;/b&gt;');
  });
});
