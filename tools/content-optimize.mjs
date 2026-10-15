// Fixes the content problems tools/content-audit.mjs finds, on the fetched data of a site, before it is imported
// into the local demo (the live site itself is never changed). Pure function: returns a new data set plus a log.
import { decode, plain } from './live-import.mjs';

const clip = (text, max) => {
  if (text.length <= max) return text;
  const cut = text.slice(0, max - 1);
  return `${cut.slice(0, cut.lastIndexOf(' ') > 60 ? cut.lastIndexOf(' ') : cut.length).replace(/[,;:.\s]+$/, '')}…`;
};

/** A description of 70-160 characters from an excerpt or the first paragraph. */
export function describe(post) {
  const source = plain(post.excerpt.rendered) || plain(post.content.rendered);
  return clip(source.replace(/\[oai_citation[^\]]*\]\([^)]*\)/g, '').trim(), 155);
}

export function optimize(
  data,
  { cardUrl = (slug) => `https://jooservices.com/wp-content/uploads/generated/card-${slug}.png` } = {}
) {
  const log = [];
  const note = (key, n = 1) => {
    log.find((l) => l.key === key) ? (log.find((l) => l.key === key).count += n) : log.push({ key, count: n });
  };
  const copy = structuredClone(data);
  const mediaById = new Map(copy.media.map((m) => [m.id, m]));
  const catById = new Map(copy.categories.map((c) => [c.id, c]));
  const published = copy.posts.filter((p) => p.status === 'publish');
  const tagCount = new Map();
  for (const p of published) for (const t of p.tags) tagCount.set(t, (tagCount.get(t) ?? 0) + 1);
  const junkTag = new Set(copy.tags.filter((t) => !/\p{L}/u.test(t.name) || /^\d+$/.test(t.slug)).map((t) => t.id));
  const cards = [];
  const featuredSeen = new Set();
  let nextMediaId = 9_000_000;

  for (const p of copy.posts) {
    const title = decode(p.title.rendered);
    for (const field of ['raw', 'rendered']) {
      let html = p.content[field] ?? '';
      if (!html) continue;
      const before = html;
      html = html.replace(/\s*\[oai_citation:[^\]]*\]\([^)]*\)/g, '');
      if (html !== before) note('AI citation markers removed');
      const h1 = html;
      html = html.replace(/<h1(\s|>)/gi, '<h2$1').replace(/<\/h1>/gi, '</h2>');
      if (html !== h1 && field === 'rendered') note('h1 in content turned into h2');
      const withoutAlt = (html.match(/<img\b(?![^>]*\balt="[^"]+")[^>]*>/gi) ?? []).length;
      html = html
        .replace(/<img\b([^>]*?)\s+alt=""([^>]*)>/gi, `<img$1 alt="${title.replace(/"/g, '&quot;')}"$2>`)
        .replace(/<img\b(?![^>]*\balt=)([^>]*)>/gi, `<img alt="${title.replace(/"/g, '&quot;')}" $1>`);
      if (withoutAlt && field === 'rendered') note('content images given alt text', withoutAlt);
      p.content[field] = html;
    }
    if (p.status !== 'publish') continue;

    // Featured image: keep a working, unique one; else the first image of the text; else a generated card.
    let media = p.featured_media ? mediaById.get(p.featured_media) : null;
    if (media && featuredSeen.has(media.id)) {
      media = null;
      note('shared featured image replaced');
    }
    if (!media) {
      const firstId = Number((p.content.rendered.match(/wp-image-(\d+)/) ?? [])[1] ?? 0);
      const candidate = mediaById.get(firstId);
      if (
        candidate &&
        !featuredSeen.has(candidate.id) &&
        (candidate.media_details?.width ?? 0) >= 800 &&
        candidate.mime_type.startsWith('image/')
      ) {
        media = candidate;
        note('featured image taken from the text');
      }
    }
    if (!media) {
      const category = catById.get(p.categories.find((c) => ![1, 295].includes(c)) ?? p.categories[0]);
      const file = `generated-card-${p.slug}.png`;
      const url = cardUrl(p.slug);
      media = {
        id: (nextMediaId += 1),
        source_url: url,
        mime_type: 'image/png',
        alt_text: title,
        title: { rendered: `Card: ${title}` },
        slug: `card-${p.slug}`,
        media_details: { width: 1200, height: 630 },
        generated: true,
      };
      copy.media.push(media);
      mediaById.set(media.id, media);
      cards.push({ title, category: decode(category?.name ?? 'Blog'), slug: p.slug, file });
      note('social card generated');
    }
    p.featured_media = media.id;
    featuredSeen.add(media.id);
    if (!(media.alt_text ?? '').trim()) {
      media.alt_text = title;
      note('featured images given alt text');
    }
    const og = p.yoast_head_json ?? (p.yoast_head_json = {});
    if (!(og.og_image ?? []).length) {
      og.og_image = [
        {
          url: media.source_url,
          width: media.media_details?.width ?? 1200,
          height: media.media_details?.height ?? 630,
        },
      ];
      note('og:image added');
    }
    const desc = (og.description ?? '').trim();
    if (desc.length < 70 || desc.length > 160) {
      og.description = describe(p);
      note('meta descriptions written');
    }
    // Tags: no junk, at most 8, the most used first.
    const kept = p.tags
      .filter((t) => !junkTag.has(t))
      .sort((a, b) => (tagCount.get(b) ?? 0) - (tagCount.get(a) ?? 0))
      .slice(0, 8);
    if (kept.length !== p.tags.length) note('tags pruned (junk or more than 8)', p.tags.length - kept.length);
    p.tags = kept;
  }
  copy.tags = copy.tags.filter((t) => !junkTag.has(t.id));
  for (const c of copy.categories) {
    if (/Developmment/.test(c.name)) {
      c.name = c.name.replace(/Developmment/g, 'Development');
      note('category name spelling fixed (slug kept so URLs stay the same)');
    }
    if (!plain(c.description).length) {
      c.description = `Articles filed under ${decode(c.name)}.`;
      note('category descriptions written');
    }
  }
  return { data: copy, cards, log };
}
