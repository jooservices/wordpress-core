// Content audit of a WordPress site export (artifacts/live/<site>.json): duplicates, SEO and images.
// Read-only. Usage: node tools/content-audit.mjs [--source artifacts/live/jooservices.com.json] [--check-urls]
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { fromRoot } from './lib/paths.mjs';
import { decode, plain } from './live-import.mjs';

const words = (text) => (text.match(/[\p{L}\p{N}]+/gu) ?? []).length;
const shingles = (text, n = 5) => {
  const w = text.toLowerCase().match(/[\p{L}\p{N}]+/gu) ?? [];
  const set = new Set();
  for (let i = 0; i + n <= w.length; i += 1) set.add(w.slice(i, i + n).join(' '));
  return set;
};
export const jaccard = (a, b) => {
  if (!a.size || !b.size) return 0;
  let inter = 0;
  for (const x of a) if (b.has(x)) inter += 1;
  return inter / (a.size + b.size - inter);
};
const groupBy = (list, key) => {
  const map = new Map();
  for (const item of list) {
    const k = key(item);
    if (k) map.set(k, [...(map.get(k) ?? []), item]);
  }
  return [...map.values()].filter((g) => g.length > 1);
};
const norm = (s) =>
  decode(s)
    .toLowerCase()
    .replace(/[^\p{L}\p{N}]+/gu, ' ')
    .trim();

/** Findings: [{ id, severity, scope, message, items }] */
export function audit(data) {
  const out = [];
  const add = (id, severity, scope, message, items) =>
    items.length && out.push({ id, severity, scope, message, count: items.length, items: items.slice(0, 15) });
  const published = data.posts.filter((p) => p.status === 'publish');
  const mediaById = new Map(data.media.map((m) => [m.id, m]));
  const label = (p) => `${p.slug} (#${p.id})`;

  // --- duplicates -------------------------------------------------------------------------------------------
  add(
    'dup-title',
    'high',
    'posts',
    'Posts share the same title',
    groupBy(published, (p) => norm(p.title.rendered)).map((g) => g.map(label).join(' = '))
  );
  add(
    'dup-slug-base',
    'medium',
    'posts',
    'Slugs that differ only by a numeric suffix (probable duplicate)',
    groupBy(published, (p) => p.slug.replace(/-\d+$/, '')).map((g) => g.map(label).join(' = '))
  );
  add(
    'dup-metadesc',
    'medium',
    'posts',
    'Posts share the same meta description',
    groupBy(published, (p) => norm(p.yoast_head_json?.description ?? '')).map((g) => g.map(label).join(' = '))
  );
  add(
    'dup-excerpt',
    'low',
    'posts',
    'Posts share the same excerpt',
    groupBy(published, (p) => norm(plain(p.excerpt.rendered)).slice(0, 120)).map((g) => g.map(label).join(' = '))
  );
  const sh = published.map((p) => [p, shingles(plain(p.content.rendered))]);
  const near = [];
  for (let i = 0; i < sh.length; i += 1)
    for (let j = i + 1; j < sh.length; j += 1) {
      const score = jaccard(sh[i][1], sh[j][1]);
      if (score >= 0.5) near.push(`${label(sh[i][0])} ~ ${label(sh[j][0])} (${Math.round(score * 100)}% same text)`);
    }
  add('dup-content', 'high', 'posts', 'Posts whose text is largely the same (duplicate content)', near);
  add(
    'dup-featured-image',
    'low',
    'posts',
    'The same featured image is used by several posts',
    groupBy(
      published.filter((p) => p.featured_media),
      (p) => String(p.featured_media)
    ).map(
      (g) => `${mediaById.get(g[0].featured_media)?.slug ?? g[0].featured_media}: ${g.map((p) => p.slug).join(', ')}`
    )
  );
  add(
    'dup-tag',
    'medium',
    'tags',
    'Tags that are the same word (case, spacing or accent)',
    groupBy(data.tags, (t) => norm(t.name).normalize('NFD').replace(/[̀-ͯ]/g, '')).map((g) =>
      g.map((t) => `${t.name} [${t.slug}] (${t.count})`).join(' = ')
    )
  );
  add(
    'dup-media',
    'low',
    'media',
    'Media files with the same title (probable duplicate upload)',
    groupBy(data.media, (m) => norm(m.title.rendered)).map((g) =>
      g.map((m) => m.source_url.split('/').slice(-2).join('/')).join(' = ')
    )
  );
  add(
    'junk-tag',
    'medium',
    'tags',
    'Tags that are numeric, a symbol or used by one post only and add no value',
    data.tags
      .filter((t) => !/[\p{L}]/u.test(t.name) || /^\d+$/.test(t.slug))
      .map((t) => `${t.name} [${t.slug}] (${t.count})`)
  );
  const single = data.tags.filter((t) => t.count === 1).length;
  if (single > 20)
    out.push({
      id: 'singleton-tags',
      severity: 'medium',
      scope: 'tags',
      message: `${single} of ${data.tags.length} tags are used by a single post (thin tag archives are indexable by default)`,
      count: single,
      items: [],
    });

  // --- SEO -------------------------------------------------------------------------------------------------
  const titleOf = (p) => decode(p.yoast_head_json?.title ?? p.title.rendered);
  add(
    'title-long',
    'medium',
    'posts',
    'SEO title over 60 characters (cut off in results)',
    published.filter((p) => titleOf(p).length > 60).map((p) => `${label(p)}: ${titleOf(p).length}`)
  );
  add(
    'title-short',
    'low',
    'posts',
    'SEO title under 25 characters',
    published.filter((p) => titleOf(p).length < 25).map((p) => `${label(p)}: ${titleOf(p)}`)
  );
  add(
    'desc-missing',
    'high',
    'posts',
    'No meta description',
    published.filter((p) => !(p.yoast_head_json?.description ?? '').trim()).map(label)
  );
  add(
    'desc-length',
    'medium',
    'posts',
    'Meta description outside 70 to 160 characters',
    published
      .filter((p) => {
        const l = (p.yoast_head_json?.description ?? '').length;
        return l > 0 && (l < 70 || l > 160);
      })
      .map((p) => `${label(p)}: ${p.yoast_head_json.description.length}`)
  );
  add(
    'thin',
    'high',
    'posts',
    'Thin content (under 300 words)',
    published
      .filter((p) => words(plain(p.content.rendered)) < 300)
      .map((p) => `${label(p)}: ${words(plain(p.content.rendered))} words`)
  );
  add(
    'noindex',
    'info',
    'posts',
    'Published posts marked noindex',
    published.filter((p) => p.yoast_head_json?.robots?.index === 'noindex').map(label)
  );
  add(
    'slug-nonascii',
    'low',
    'posts',
    'Slug with non-ASCII characters or over 75 characters',
    published
      .filter((p) => /[^ -~]/.test(decodeURIComponent(p.slug)) || p.slug.length > 75)
      .map((p) => `${p.slug.slice(0, 80)} (${p.slug.length})`)
  );
  add(
    'uncategorized',
    'medium',
    'posts',
    'Posts only in Uncategorized or Featured (no topical category)',
    published.filter((p) => p.categories.every((c) => [1, 295].includes(c))).map(label)
  );
  add(
    'too-many-tags',
    'low',
    'posts',
    'More than 8 tags (tag stuffing)',
    published.filter((p) => p.tags.length > 8).map((p) => `${label(p)}: ${p.tags.length}`)
  );
  add('no-tags', 'low', 'posts', 'No tags', published.filter((p) => p.tags.length === 0).map(label));
  add(
    'junk-text',
    'high',
    'posts',
    'Leftover AI citation markers such as [oai_citation:...] in the text',
    published
      .filter((p) => /\[oai_citation/.test(p.content.raw ?? p.content.rendered))
      .map((p) => `${label(p)}: ${(p.content.raw.match(/\[oai_citation/g) ?? []).length}`)
  );
  add(
    'h1-in-content',
    'medium',
    'posts',
    'An h1 inside the content (the title is already the h1)',
    published.filter((p) => /<h1[\s>]/i.test(p.content.rendered)).map(label)
  );
  add(
    'heading-skip',
    'low',
    'posts',
    'Heading levels that skip (h2 to h4)',
    published
      .filter((p) => {
        const l = [...p.content.rendered.matchAll(/<h([1-6])[\s>]/gi)].map((m) => +m[1]);
        return l.some((v, i) => i && v - l[i - 1] > 1);
      })
      .map(label)
  );
  add(
    'no-headings',
    'medium',
    'posts',
    'Long post (over 600 words) without any heading',
    published
      .filter((p) => words(plain(p.content.rendered)) > 600 && !/<h[2-4][\s>]/i.test(p.content.rendered))
      .map(label)
  );
  add(
    'no-excerpt',
    'low',
    'posts',
    'No manual excerpt (the card shows an automatic one)',
    published.filter((p) => !plain(p.excerpt.raw ?? '').length).map(label)
  );
  const cats = data.categories;
  add(
    'cat-no-description',
    'medium',
    'categories',
    'Category without a description (thin archive page)',
    cats.filter((c) => !plain(c.description).length).map((c) => c.slug)
  );
  add(
    'cat-empty',
    'medium',
    'categories',
    'Empty category',
    cats.filter((c) => c.count === 0).map((c) => c.slug)
  );
  add(
    'cat-typo',
    'high',
    'categories',
    'Category name or slug that looks misspelled',
    cats.filter((c) => /developmment|developmment/i.test(c.slug + c.name)).map((c) => `${c.name} [${c.slug}]`)
  );
  add(
    'page-draft',
    'info',
    'pages',
    'Pages still in draft',
    data.pages.filter((p) => p.status !== 'publish').map((p) => `${p.slug} (${p.status})`)
  );

  // --- images ----------------------------------------------------------------------------------------------
  add(
    'no-featured',
    'high',
    'images',
    'Published posts without a featured image (no card image, weak social preview)',
    published.filter((p) => !p.featured_media).map(label)
  );
  add(
    'featured-missing-file',
    'high',
    'images',
    'Featured image id that no longer exists in the media library',
    published.filter((p) => p.featured_media && !mediaById.has(p.featured_media)).map(label)
  );
  add(
    'featured-no-alt',
    'medium',
    'images',
    'Featured image without alt text',
    published
      .filter(
        (p) =>
          p.featured_media &&
          mediaById.has(p.featured_media) &&
          !(mediaById.get(p.featured_media).alt_text ?? '').trim()
      )
      .map((p) => `${label(p)} -> ${mediaById.get(p.featured_media).slug}`)
  );
  add(
    'featured-small',
    'medium',
    'images',
    'Featured image narrower than 1200 px (weak Open Graph preview)',
    published
      .filter((p) => p.featured_media && (mediaById.get(p.featured_media)?.media_details?.width ?? 9999) < 1200)
      .map((p) => `${label(p)}: ${mediaById.get(p.featured_media).media_details.width}px`)
  );
  add(
    'featured-heavy',
    'low',
    'images',
    'Featured image over 1.5 MB',
    published
      .filter((p) => (mediaById.get(p.featured_media)?.media_details?.filesize ?? 0) > 1.5 * 1024 * 1024)
      .map((p) => `${label(p)}: ${Math.round(mediaById.get(p.featured_media).media_details.filesize / 1024)} KB`)
  );
  add(
    'og-image-missing',
    'medium',
    'images',
    'No og:image in the SEO head',
    published.filter((p) => !(p.yoast_head_json?.og_image ?? []).length).map(label)
  );
  const noAlt = [];
  const imgTotal = { all: 0, noAlt: 0 };
  for (const p of published) {
    for (const m of p.content.rendered.matchAll(/<img\b[^>]*>/gi)) {
      imgTotal.all += 1;
      if (!/\balt="[^"]+"/i.test(m[0])) {
        imgTotal.noAlt += 1;
        noAlt.push(p.slug);
      }
    }
  }
  const byPost = new Map();
  for (const slug of noAlt) byPost.set(slug, (byPost.get(slug) ?? 0) + 1);
  add(
    'content-img-no-alt',
    'medium',
    'images',
    `Images inside posts without alt text (${imgTotal.noAlt} of ${imgTotal.all})`,
    [...byPost].map(([slug, n]) => `${slug}: ${n}`)
  );
  add(
    'media-no-alt',
    'low',
    'images',
    'Media library images without alt text',
    data.media
      .filter((m) => m.mime_type.startsWith('image/') && !(m.alt_text ?? '').trim())
      .map((m) => m.source_url.split('/').slice(-2).join('/'))
  );
  const used = new Set(
    published.flatMap((p) => [
      p.featured_media,
      ...[...p.content.rendered.matchAll(/wp-image-(\d+)/g)].map((m) => +m[1]),
    ])
  );
  add(
    'media-unused',
    'low',
    'images',
    'Images in the library used by no published post',
    data.media
      .filter((m) => m.mime_type.startsWith('image/') && !used.has(m.id))
      .map((m) => m.source_url.split('/').slice(-2).join('/'))
  );
  return out;
}

/** HEAD-check the image URLs and the internal links used in the published posts. */
export async function checkUrls(data) {
  const urls = new Map();
  for (const p of data.posts.filter((x) => x.status === 'publish')) {
    for (const m of p.content.rendered.matchAll(/<img[^>]+src="([^"]+)"/gi))
      urls.set(m[1], [...(urls.get(m[1]) ?? []), p.slug]);
    for (const m of p.content.rendered.matchAll(/<a[^>]+href="(https?:\/\/jooservices\.com[^"#]*)"/gi))
      urls.set(m[1], [...(urls.get(m[1]) ?? []), p.slug]);
  }
  const bad = [];
  const queue = [...urls];
  const worker = async () => {
    for (let item = queue.shift(); item; item = queue.shift()) {
      const [url, slugs] = item;
      try {
        const res = await fetch(url, { method: 'HEAD', redirect: 'manual' });
        if (res.status >= 400) bad.push(`${res.status} ${url} (in ${[...new Set(slugs)].slice(0, 2).join(', ')})`);
      } catch (error) {
        bad.push(`ERR ${url} (${error.message})`);
      }
    }
  };
  await Promise.all(Array.from({ length: 8 }, worker));
  return { checked: urls.size, bad };
}

export function render(site, data, findings, urls) {
  const order = { high: 0, medium: 1, low: 2, info: 3 };
  const sorted = [...findings].sort((a, b) => order[a.severity] - order[b.severity] || a.scope.localeCompare(b.scope));
  const pub = data.posts.filter((p) => p.status === 'publish').length;
  const table = sorted.map((f) => `| ${f.severity} | ${f.scope} | ${f.message} | ${f.count} |`).join('\n');
  const detail = sorted
    .map(
      (f) =>
        `### ${f.message}\n\n${f.severity} · ${f.scope} · ${f.count} found${f.items.length < f.count ? ` (first ${f.items.length})` : ''}\n\n${f.items.map((i) => `- ${i}`).join('\n') || '- (summary only)'}\n`
    )
    .join('\n');
  const urlPart = urls
    ? `\n## Broken images and internal links\n\nChecked ${urls.checked} URLs, ${urls.bad.length} answered with an error.\n\n${
        urls.bad
          .slice(0, 40)
          .map((b) => `- ${b}`)
          .join('\n') || '- none'
      }\n`
    : '';
  return `# Content audit: ${site}\n\n${pub} published posts, ${data.posts.length - pub} drafts, ${data.pages.length} pages, ${data.categories.length} categories, ${data.tags.length} tags, ${data.media.length} media files.\n\n## Summary\n\n| Severity | Area | Finding | Count |\n| --- | --- | --- | --- |\n${table}\n${urlPart}\n## Details\n\n${detail}`;
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = process.argv.slice(2);
  const source = fromRoot(
    args.includes('--source') ? args[args.indexOf('--source') + 1] : 'artifacts/live/jooservices.com.json'
  );
  const data = JSON.parse(readFileSync(source, 'utf8'));
  const findings = audit(data);
  const urls = args.includes('--check-urls') ? await checkUrls(data) : null;
  mkdirSync(fromRoot('artifacts/reports/content'), { recursive: true });
  const file = fromRoot('artifacts/reports/content/audit.md');
  writeFileSync(file, render('jooservices.com', data, findings, urls));
  writeFileSync(fromRoot('artifacts/reports/content/audit.json'), JSON.stringify({ findings, urls }, null, 2));
  for (const f of findings.sort((a, b) => a.severity.localeCompare(b.severity)))
    console.log(`${f.severity.padEnd(6)} ${f.id.padEnd(22)} ${String(f.count).padStart(4)}  ${f.message}`);
  if (urls) console.log(`urls checked ${urls.checked}, broken ${urls.bad.length}`);
  console.log(`report: ${file}`);
}
