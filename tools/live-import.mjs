// Builds a demo set (WXR, media, menus, settings) from the PUBLIC REST data of a live WordPress site, so the local
// demo can show real content. Read-only: it only sends GET requests and writes below artifacts/.
// Usage: node tools/live-import.mjs --source artifacts/live/jooservices.com.json --out artifacts/demo/jooservices-live
//        node tools/live-import.mjs --fetch https://jooservices.com   (downloads the JSON first)
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { basename, join } from 'node:path';
import { fromRoot } from './lib/paths.mjs';
import { build } from './wxr-generator/generate.mjs';

const MEDIA = '__MEDIA_BASE__';
const ENTITIES = {
  amp: '&',
  lt: '<',
  gt: '>',
  quot: '"',
  apos: "'",
  nbsp: ' ',
  hellip: '…',
  ndash: '–',
  mdash: '—',
  rsquo: '’',
  lsquo: '‘',
  ldquo: '“',
  rdquo: '”',
};

/** Decodes HTML entities of a title or name. */
export function decode(text) {
  return String(text ?? '')
    .replace(/&#(\d+);/g, (_, n) => String.fromCodePoint(Number(n)))
    .replace(/&#x([0-9a-f]+);/gi, (_, n) => String.fromCodePoint(parseInt(n, 16)))
    .replace(/&([a-z]+);/gi, (m, name) => ENTITIES[name.toLowerCase()] ?? m);
}

/** Plain text of an HTML fragment. */
export const plain = (html) =>
  decode(String(html ?? '').replace(/<[^>]*>/g, ' '))
    .replace(/\s+/g, ' ')
    .trim();

/** Vietnamese diacritics that never occur in English text. */
export const isVietnamese = (text) => /[ăâđêôơưĂÂĐÊÔƠƯạảãấầẩẫậắằẳẵặẹẻẽếềểễệịỉĩọỏốồổỗộớờởỡợụủứừửữựỳỵỷỹ]/i.test(text);

/** Local file name of a remote upload URL (stable, flat, safe). */
export function localName(url) {
  let path = new URL(url).pathname.replace(/^\/wp-content\/uploads\//, '');
  try {
    path = decodeURIComponent(path);
  } catch {
    /* keep the encoded form */
  }
  return path.replace(/[^A-Za-z0-9._-]+/g, '-').replace(/-+/g, '-');
}

const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const cdata = (s) => `<![CDATA[${String(s).replace(/]]>/g, ']]]]><![CDATA[>')}]]>`;
const stamp = (iso) => iso.replace('T', ' ').slice(0, 19);
const rfc = (iso) => new Date(`${iso}Z`).toUTCString();
const meta = (k, v) =>
  `<wp:postmeta><wp:meta_key>${esc(k)}</wp:meta_key><wp:meta_value>${cdata(v)}</wp:meta_value></wp:postmeta>`;
const termMeta = (m) =>
  Object.entries(m)
    .map(
      ([k, v]) =>
        `<wp:termmeta><wp:meta_key>${cdata(k)}</wp:meta_key><wp:meta_value>${cdata(v)}</wp:meta_value></wp:termmeta>`
    )
    .join('');

function item(o) {
  return `<item>
<title>${esc(o.title)}</title>
<link>http://example.test/?p=${o.id}</link>
<pubDate>${rfc(o.date)}</pubDate>
<dc:creator>${cdata('viet')}</dc:creator>
<guid isPermaLink="false">live-${o.id}</guid>
<description></description>
<content:encoded>${cdata(o.content ?? '')}</content:encoded>
<excerpt:encoded>${cdata(o.excerpt ?? '')}</excerpt:encoded>
<wp:post_id>${o.id}</wp:post_id>
<wp:post_date>${cdata(stamp(o.date))}</wp:post_date>
<wp:post_date_gmt>${cdata(stamp(o.date))}</wp:post_date_gmt>
<wp:post_modified>${cdata(stamp(o.date))}</wp:post_modified>
<wp:post_modified_gmt>${cdata(stamp(o.date))}</wp:post_modified_gmt>
<wp:comment_status>${cdata(o.commentStatus ?? 'open')}</wp:comment_status>
<wp:ping_status>${cdata('closed')}</wp:ping_status>
<wp:post_name>${cdata(o.slug)}</wp:post_name>
<wp:status>${cdata(o.status ?? 'publish')}</wp:status>
<wp:post_parent>0</wp:post_parent>
<wp:menu_order>0</wp:menu_order>
<wp:post_type>${cdata(o.type ?? 'post')}</wp:post_type>
<wp:post_password>${cdata('')}</wp:post_password>
<wp:is_sticky>0</wp:is_sticky>
${o.attachmentUrl ? `<wp:attachment_url>${cdata(o.attachmentUrl)}</wp:attachment_url>\n` : ''}${(o.cats ?? []).map((c) => `<category domain="category" nicename="${c.slug}">${cdata(c.name)}</category>`).join('\n')}
${(o.tags ?? []).map((t) => `<category domain="post_tag" nicename="${t.slug}">${cdata(t.name)}</category>`).join('\n')}
${(o.meta ?? []).map(([k, v]) => meta(k, v)).join('\n')}
</item>`;
}

/**
 * Pure transformation of the fetched data. `download(url)` is called for every image that is used and must return
 * true when the file is available locally under localName(url).
 */
export function transform(data, { companies, settings, profile }) {
  const catById = new Map(data.categories.map((c) => [c.id, c]));
  const tagById = new Map(data.tags.map((t) => [t.id, t]));
  const mediaById = new Map(data.media.map((m) => [m.id, m]));
  const images = new Map(); // url -> local name
  const useImage = (url) => {
    if (!/\.(jpe?g|png|gif|webp|avif|svg)$/i.test(url.split('?')[0])) return null;
    images.set(url, localName(url));
    return localName(url);
  };
  const rewrite = (html) =>
    String(html)
      .replace(/\s(srcset|sizes)="[^"]*"/g, '')
      .replace(/https?:\/\/[^\s"'<>)]*\/wp-content\/uploads\/[^\s"'<>)]+/g, (url) => {
        const name = useImage(url);
        return name ? `${MEDIA}/${name}` : url;
      });

  let id = 1000;
  const usedTags = new Set(data.posts.flatMap((p) => p.tags));
  const tags = data.tags.filter((t) => usedTags.has(t.id) && /[A-Za-z0-9]/.test(t.name));
  const keepTag = new Set(tags.map((t) => t.id));

  const featuredCat = data.categories.find((c) => c.slug === 'featured');
  const posts = [...data.posts].sort((a, b) => b.date.localeCompare(a.date));
  const featuredIds = [];
  for (const p of posts)
    if (featuredCat && p.categories.includes(featuredCat.id) && p.featured_media) featuredIds.push(p.id);
  for (const p of posts)
    if (featuredIds.length < 5 && p.featured_media && !featuredIds.includes(p.id)) featuredIds.push(p.id);

  const attachmentId = new Map(); // media id -> wxr id
  const postItems = posts.map((p) => {
    const cats = p.categories.map((c) => catById.get(c)).filter((c) => c && c.slug !== 'featured');
    const thumb = p.featured_media ? mediaById.get(p.featured_media) : null;
    const thumbName = thumb ? useImage(thumb.source_url) : null;
    const text = plain(p.title.rendered) + ' ' + plain(p.excerpt.rendered);
    const m = [
      ['_joo_demo', '1'],
      ['_joo_lang', isVietnamese(text) ? 'vi' : 'en'],
      ['_joo_live_source', p.link],
    ];
    const order = featuredIds.indexOf(p.id);
    if (order >= 0) m.push(['_joo_featured', '1'], ['_joo_featured_order', String(order + 1)]);
    const entry = {
      id: (id += 1),
      title: decode(p.title.rendered),
      slug: p.slug,
      date: p.date,
      content: rewrite(p.content.rendered),
      excerpt: plain(p.excerpt.rendered),
      cats,
      tags: p.tags.filter((t) => keepTag.has(t)).map((t) => tagById.get(t)),
      meta: m,
      thumbName,
      thumbMedia: thumb?.id,
      commentStatus: 'open',
    };
    return entry;
  });

  const media = [];
  for (const [url, name] of images) {
    const mid = (id += 1);
    media.push({ id: mid, url, name });
  }
  const mediaByName = new Map(media.map((m) => [m.name, m]));
  for (const p of postItems) {
    if (p.thumbName && mediaByName.has(p.thumbName))
      p.meta.push(['_thumbnail_id', String(mediaByName.get(p.thumbName).id)]);
    if (p.cats[0]) p.meta.push(['_joo_primary_category_slug', p.cats[0].slug]);
  }
  void attachmentId;

  const pages = data.pages.map((p) => ({
    id: (id += 1),
    title: decode(p.title.rendered),
    slug: p.slug,
    date: p.date,
    content: rewrite(p.content.rendered),
    excerpt: '',
    meta: [['_joo_demo', '1']],
    type: 'page',
  }));
  if (!pages.some((p) => p.slug === 'about')) {
    pages.push({
      id: (id += 1),
      title: 'About',
      slug: 'about',
      date: posts.at(-1)?.date ?? '2024-01-01T00:00:00',
      type: 'page',
      excerpt: '',
      meta: [
        ['_joo_demo', '1'],
        ['_wp_page_template', 'page-full'],
      ],
      content:
        '<!-- wp:joo/profile-card {"titleLevel":3} /-->\n\n<!-- wp:heading -->\n<h2 class="wp-block-heading">Where I have worked</h2>\n<!-- /wp:heading -->\n\n<!-- wp:joo/companies {"perPage":6} /-->',
    });
  }
  const companyItems = companies.map((c, i) => ({
    id: (id += 1),
    type: 'company',
    title: c.name,
    slug: c.name.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
    date: '2024-01-0' + ((i % 8) + 1) + 'T09:00:00',
    excerpt: c.description,
    commentStatus: 'closed',
    meta: [
      ['_joo_demo', '1'],
      ['_joo_company_role', c.role],
      ['_joo_company_from', c.from],
      ['_joo_company_to', c.to],
      ['_joo_company_pinned', c.pinned ? '1' : ''],
      ['_joo_company_url', c.url],
    ],
  }));
  for (const c of companyItems) c.type = 'joo_company';

  const cats = data.categories.filter((c) => c.slug !== 'featured');
  const ordered = [...cats.filter((c) => c.parent === 0), ...cats.filter((c) => c.parent !== 0)];
  const xml = `<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
<title>JOOservices live content</title>
<link>http://example.test</link>
<description>Public content of a live site (read-only fetch)</description>
<language>en-US</language>
<wp:wxr_version>1.2</wp:wxr_version>
<wp:base_site_url>http://example.test</wp:base_site_url>
<wp:base_blog_url>http://example.test</wp:base_blog_url>
<wp:author><wp:author_id>2</wp:author_id><wp:author_login>${cdata('viet')}</wp:author_login><wp:author_email>${cdata('viet@example.test')}</wp:author_email><wp:author_display_name>${cdata('Viet VU')}</wp:author_display_name><wp:author_first_name>${cdata('Viet')}</wp:author_first_name><wp:author_last_name>${cdata('VU')}</wp:author_last_name></wp:author>
${ordered.map((c) => `<wp:category><wp:term_id>${c.id}</wp:term_id><wp:category_nicename>${cdata(c.slug)}</wp:category_nicename><wp:category_parent>${cdata(catById.get(c.parent)?.slug ?? '')}</wp:category_parent><wp:cat_name>${cdata(decode(c.name))}</wp:cat_name><wp:category_description>${cdata(plain(c.description))}</wp:category_description>${termMeta({ _joo_demo: '1', joo_short_description: plain(c.description).slice(0, 160) })}</wp:category>`).join('\n')}
${tags.map((t) => `<wp:tag><wp:term_id>${t.id}</wp:term_id><wp:tag_slug>${cdata(t.slug)}</wp:tag_slug><wp:tag_name>${cdata(decode(t.name))}</wp:tag_name><wp:tag_description>${cdata(plain(t.description))}</wp:tag_description>${termMeta({ _joo_demo: '1' })}</wp:tag>`).join('\n')}
${media.map((m) => item({ id: m.id, title: m.name, slug: m.name.replace(/\.[a-z0-9]+$/i, ''), date: '2024-01-01T00:00:00', type: 'attachment', status: 'inherit', attachmentUrl: `${MEDIA}/${m.name}`, meta: [['_joo_demo', '1']] })).join('\n')}
${[...postItems, ...pages, ...companyItems].map((o) => item(o)).join('\n')}
</channel>
</rss>
`;
  const topLevel = ordered
    .filter((c) => c.parent === 0 && c.count > 0 && !['uncategorized', 'xcrawler'].includes(c.slug))
    .slice(0, 4);
  const menus = {
    primary: [
      ...topLevel.map((c) => ({
        title: decode(c.name).replace(/Developmment/, 'Development'),
        type: 'category',
        slug: c.slug,
      })),
      { title: 'About', type: 'page', slug: 'about' },
    ],
    footer: pages.filter((p) => p.slug !== 'about').map((p) => ({ title: p.title, type: 'page', slug: p.slug })),
  };
  const manifest = {
    source: 'live',
    counts: {
      posts: postItems.length,
      pages: pages.length,
      categories: ordered.length,
      tags: tags.length,
      images: media.length,
      featured: featuredIds.length,
      vietnamese: postItems.filter((p) => p.meta.some(([k, v]) => k === '_joo_lang' && v === 'vi')).length,
    },
  };
  return { xml, media, menus, settings, manifest, profile };
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = Object.fromEntries(
    process.argv
      .slice(2)
      .map((a, i, all) => (a.startsWith('--') ? [a.slice(2), all[i + 1]] : null))
      .filter(Boolean)
  );
  const source = fromRoot(args.source ?? 'artifacts/live/jooservices.com.json');
  const out = fromRoot(args.out ?? 'artifacts/demo/jooservices-live');
  const cache = fromRoot('artifacts/live/media');
  if (!existsSync(source)) {
    console.error(`missing ${source}: fetch the public REST data first (see docs/guides/live-content.md)`);
    process.exit(1);
  }
  const data = JSON.parse(readFileSync(source, 'utf8'));
  const base = build(42);
  const topics = JSON.parse(readFileSync(fromRoot('tools/wxr-generator/data/topics.json'), 'utf8'));
  const result = transform(data, {
    companies: topics.companies,
    settings: base.settings,
    profile: base.settings.profile,
  });
  mkdirSync(join(out, 'media'), { recursive: true });
  mkdirSync(cache, { recursive: true });
  const origin = new URL(data.posts[0]?.link ?? 'https://example.test').origin;
  let fetched = 0;
  let missing = 0;
  const queue = [...result.media];
  const worker = async () => {
    for (let m = queue.shift(); m; m = queue.shift()) {
      const file = join(cache, basename(m.name));
      if (!existsSync(file)) {
        try {
          const res = await fetch(m.url.startsWith('http') ? m.url : origin + m.url);
          if (!res.ok) throw new Error(String(res.status));
          writeFileSync(file, Buffer.from(await res.arrayBuffer()));
          fetched += 1;
        } catch {
          missing += 1;
          continue;
        }
      }
      writeFileSync(join(out, 'media', m.name), readFileSync(file));
    }
  };
  await Promise.all(Array.from({ length: 6 }, worker));
  // Attachments whose file could not be downloaded are skipped by the seeder (their placeholder stays as a remote URL).
  const settings = {
    ...result.settings,
    profile: {
      ...result.settings.profile,
      avatar: result.media.find((m) => existsSync(join(out, 'media', m.name)))?.name ?? '',
    },
  };
  writeFileSync(join(out, 'content.xml'), result.xml);
  writeFileSync(join(out, 'menus.json'), JSON.stringify(result.menus, null, 2) + '\n');
  writeFileSync(join(out, 'settings.json'), JSON.stringify(settings, null, 2) + '\n');
  writeFileSync(
    join(out, 'manifest.json'),
    JSON.stringify({ ...result.manifest, downloaded: fetched, missing }, null, 2) + '\n'
  );
  console.log(
    `wrote ${out}: ${JSON.stringify(result.manifest.counts)}, ${fetched} images downloaded, ${missing} missing`
  );
}
