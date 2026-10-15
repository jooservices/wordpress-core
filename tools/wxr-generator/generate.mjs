// Deterministic demo content: WXR 1.2 (content.xml), generated media, menus, settings and a manifest with the expected counts.
// Usage: node tools/wxr-generator/generate.mjs [--seed 42] [--out themes/<slug>/demo]
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gradientPng } from './lib/png.mjs';
import { mulberry32, toolbox } from './lib/prng.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const data = JSON.parse(readFileSync(join(here, 'data/topics.json'), 'utf8'));

const START = Date.UTC(2023, 0, 5, 9, 0, 0);
const END = Date.UTC(2026, 9, 1, 9, 0, 0);
const MEDIA = '__MEDIA_BASE__';

const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const cdata = (s) => `<![CDATA[${String(s).replace(/]]>/g, ']]]]><![CDATA[>')}]]>`;
const stamp = (ms) => new Date(ms).toISOString().replace('T', ' ').slice(0, 19);
const rfc = (ms) => new Date(ms).toUTCString();

/** Builds the whole dataset for a seed. */
export function build(seed = 42) {
  const { int, pick, rand } = toolbox(mulberry32(seed));
  let nextId = 100;
  const id = () => (nextId += 1);

  // --- media -----------------------------------------------------------------------------------------------
  const palettes = [
    [
      [14, 30, 60],
      [30, 120, 190],
    ],
    [
      [40, 14, 60],
      [160, 60, 150],
    ],
    [
      [10, 50, 40],
      [40, 160, 110],
    ],
    [
      [60, 30, 10],
      [200, 120, 40],
    ],
    [
      [20, 20, 40],
      [90, 90, 180],
    ],
    [
      [10, 40, 60],
      [20, 170, 190],
    ],
    [
      [50, 10, 30],
      [190, 50, 90],
    ],
    [
      [25, 45, 15],
      [120, 170, 50],
    ],
    [
      [30, 30, 30],
      [140, 150, 160],
    ],
    [
      [15, 25, 55],
      [60, 90, 200],
    ],
    [
      [45, 25, 55],
      [170, 110, 190],
    ],
    [
      [10, 55, 55],
      [60, 200, 160],
    ],
  ];
  const media = [];
  const attachments = [];
  palettes.forEach(([from, to], index) => {
    const wide = index % 3 !== 2;
    const [w, h] = wide ? [1600, 900] : [800, 600];
    const name = `demo-${String(index + 1).padStart(2, '0')}-${w}x${h}.png`;
    const jitter = int(0, 12);
    media.push({
      name,
      buffer: gradientPng(
        w,
        h,
        from.map((v) => v + jitter),
        to
      ),
    });
    attachments.push({
      id: id(),
      name,
      alt: `Generated demo image ${index + 1}`,
      title: `Demo image ${index + 1}`,
      w,
      h,
    });
  });

  // --- terms -----------------------------------------------------------------------------------------------
  const categories = data.categories;
  const tags = data.tags.map((slug) => ({ slug, name: slug, intro: `Posts about ${slug}.` }));

  // --- posts -----------------------------------------------------------------------------------------------
  const catOf = (slug) => data.categories.find((c) => c.slug === slug);
  const total = 40;
  const step = Math.floor((END - START) / total);
  const para = (n) =>
    Array.from(
      { length: n },
      () =>
        `<!-- wp:paragraph -->\n<p>${esc(Array.from({ length: int(2, 3) }, () => pick(data.sentences)).join(' '))}</p>\n<!-- /wp:paragraph -->`
    ).join('\n\n');
  const heading = (text, level = 2) =>
    `<!-- wp:heading${level === 2 ? '' : ` {"level":${level}}`} -->\n<h${level} class="wp-block-heading">${esc(text)}</h${level}>\n<!-- /wp:heading -->`;
  const list = () =>
    `<!-- wp:list -->\n<ul class="wp-block-list">${Array.from({ length: 4 }, () => `<li>${esc(pick(data.sentences).split(',')[0])}</li>`).join('')}</ul>\n<!-- /wp:list -->`;
  const quote = () =>
    `<!-- wp:quote -->\n<blockquote class="wp-block-quote"><p>${esc(pick(data.sentences))}</p></blockquote>\n<!-- /wp:quote -->`;
  const code = (lang, lines) =>
    `<!-- wp:code -->\n<pre class="wp-block-code"><code class="language-${lang}">${esc(Array.from({ length: lines }, (_, i) => `// step ${i + 1}: ${pick(data.verbs).toLowerCase()} ${pick(data.nouns)}\n$value = compute( ${i + 1} );`).join('\n'))}</code></pre>\n<!-- /wp:code -->`;
  const image = (a) =>
    `<!-- wp:image {"sizeSlug":"large"} -->\n<figure class="wp-block-image size-large"><img src="${MEDIA}/${a.name}" alt="${esc(a.alt)}" width="${a.w}" height="${a.h}"/></figure>\n<!-- /wp:image -->`;
  const gallery = (list2) =>
    `<!-- wp:gallery {"linkTo":"none"} -->\n<figure class="wp-block-gallery has-nested-images columns-3 is-cropped">${list2.map((a) => `<!-- wp:image -->\n<figure class="wp-block-image"><img src="${MEDIA}/${a.name}" alt="${esc(a.alt)}"/></figure>\n<!-- /wp:image -->`).join('')}</figure>\n<!-- /wp:gallery -->`;
  const table = () =>
    `<!-- wp:table -->\n<figure class="wp-block-table"><table><thead><tr><th>Metric</th><th>Before</th><th>After</th></tr></thead><tbody><tr><td>p95</td><td>${int(200, 900)} ms</td><td>${int(40, 190)} ms</td></tr></tbody></table></figure>\n<!-- /wp:table -->`;

  const specs = [];
  data.core.forEach((c, i) =>
    specs.push({ ...c, kind: 'core', featured: i + 1, image: attachments[i % attachments.length] })
  );
  const rest = [];
  for (let i = specs.length; i < total; i += 1) rest.push(i);
  const generated = (i) => {
    const verb = pick(data.verbs);
    const noun = pick(data.nouns);
    const tool = pick(data.tools);
    const cat = pick(['ai', 'homelab', 'study-cases', 'tips-and-tricks', 'news', 'companies']);
    return {
      slug: `${verb}-${noun}-with-${tool}`.toLowerCase().replace(/[^a-z0-9]+/g, '-') + `-${i}`,
      cat,
      title: `${verb} ${noun} with ${tool}`,
      excerpt: pick(data.sentences),
      tags: [pick(data.tags), pick(data.tags)].filter((t, k, a) => a.indexOf(t) === k),
    };
  };
  for (const i of rest) specs.push({ ...generated(i), kind: 'normal' });
  const set = (i, patch) => Object.assign(specs[i], patch);
  set(8, {
    kind: 'toc',
    title: 'The complete release checklist, in twenty steps',
    slug: 'complete-release-checklist',
    cat: 'study-cases',
    tags: ['testing', 'php'],
  });
  [9, 10, 11].forEach((i, k) =>
    set(i, {
      kind: 'code',
      title: `Long listing: ${['a queue worker', 'a webhook handler', 'a cache warmer'][k]}`,
      slug: ['queue-worker-listing', 'webhook-handler-listing', 'cache-warmer-listing'][k],
      cat: 'tips-and-tricks',
      tags: ['php', 'laravel'],
    })
  );
  [12, 13, 14, 15, 16].forEach((i, k) =>
    set(i, {
      kind: 'images',
      title: `Photo notes ${k + 1}: the rack, the cables, the labels`,
      slug: `photo-notes-${k + 1}`,
      cat: 'homelab',
      tags: ['homelab'],
      image: attachments[(k + 2) % attachments.length],
    })
  );
  data.vi.forEach((v, k) => set(17 + k, { kind: 'vi', ...v }));
  [21, 22].forEach((i, k) =>
    set(i, {
      kind: 'comments',
      title: `A discussion post ${k + 1}: what would you change?`,
      slug: `discussion-post-${k + 1}`,
      cat: 'blog',
      tags: ['agents'],
      comments: 34 + k * 4,
    })
  );
  set(23, {
    kind: 'two-categories',
    title: 'One post, two homes',
    slug: 'one-post-two-homes',
    cat: 'ai',
    cat2: 'news',
    tags: ['mcp'],
  });
  set(24, {
    kind: 'draft',
    status: 'draft',
    title: 'Draft: unfinished thoughts',
    slug: 'draft-unfinished-thoughts',
    cat: 'blog',
  });
  set(25, {
    kind: 'private',
    status: 'private',
    title: 'Private: only for me',
    slug: 'private-only-for-me',
    cat: 'blog',
  });
  set(26, {
    kind: 'protected',
    password: 'demo',
    title: 'Protected: ask me for the password',
    slug: 'protected-ask-me',
    cat: 'blog',
  });
  specs.forEach((p, i) => {
    p.index = i;
    p.status ??= 'publish';
    p.password ??= '';
    p.tags ??= [];
    p.date = END - i * step;
    p.id = id();
    p.image ??= i % 3 === 0 && p.kind === 'normal' ? attachments[i % attachments.length] : null;
    p.lang = p.kind === 'vi' ? 'vi' : 'en';
  });

  const bodyOf = (p) => {
    const parts = [];
    if (p.kind === 'toc') {
      parts.push(para(1));
      for (let n = 1; n <= 20; n += 1)
        parts.push(heading(`Step ${n}: ${pick(data.verbs)} ${pick(data.nouns)}`), para(1));
    } else if (p.kind === 'code') {
      parts.push(para(1), heading('The listing'), code('php', 60), para(1), code('bash', 25), quote());
    } else if (p.kind === 'images') {
      parts.push(
        para(1),
        image(p.image),
        para(1),
        gallery([attachments[1], attachments[3], attachments[5], attachments[7], attachments[9], attachments[11]]),
        para(1)
      );
    } else if (p.kind === 'vi') {
      parts.push(
        `<!-- wp:paragraph -->\n<p>${esc(p.excerpt)} Bài viết này đi qua từng bước, kèm ví dụ và những điều cần tránh.</p>\n<!-- /wp:paragraph -->`,
        heading('Bối cảnh'),
        para(1),
        heading('Cách làm'),
        list(),
        para(1)
      );
    } else {
      parts.push(
        para(2),
        heading('What I tried'),
        para(2),
        list(),
        heading('What worked'),
        para(1),
        table(),
        quote(),
        para(1)
      );
    }
    return parts.join('\n\n');
  };

  // comments ---------------------------------------------------------------------------------------------
  const commentsFor = (p) => {
    const out = [];
    let cid = 1;
    for (let n = 0; n < p.comments; n += 1) {
      const parent = n > 4 && n % 3 === 0 ? out[int(0, out.length - 1)].id : 0;
      out.push({
        id: (cid += 1),
        parent: parent > 0 ? parent : 0,
        author: `Reader ${n + 1}`,
        email: `reader${n + 1}@example.test`,
        date: p.date + (n + 1) * 3600e3,
        content: data.comments[n % data.comments.length],
      });
    }
    return out;
  };

  // --- XML -------------------------------------------------------------------------------------------------
  const meta = (k, v) =>
    `<wp:postmeta><wp:meta_key>${esc(k)}</wp:meta_key><wp:meta_value>${cdata(v)}</wp:meta_value></wp:postmeta>`;
  const item = (o) =>
    `<item>
<title>${esc(o.title)}</title>
<link>http://example.test/?p=${o.id}</link>
<pubDate>${rfc(o.date)}</pubDate>
<dc:creator>${cdata(o.author ?? 'viet')}</dc:creator>
<guid isPermaLink="false">demo-${o.id}</guid>
<description></description>
<content:encoded>${cdata(o.content ?? '')}</content:encoded>
<excerpt:encoded>${cdata(o.excerpt ?? '')}</excerpt:encoded>
<wp:post_id>${o.id}</wp:post_id>
<wp:post_date>${cdata(stamp(o.date))}</wp:post_date>
<wp:post_date_gmt>${cdata(stamp(o.date))}</wp:post_date_gmt>
<wp:post_modified>${cdata(stamp(o.date + 3600e3))}</wp:post_modified>
<wp:post_modified_gmt>${cdata(stamp(o.date + 3600e3))}</wp:post_modified_gmt>
<wp:comment_status>${cdata(o.commentStatus ?? 'open')}</wp:comment_status>
<wp:ping_status>${cdata('closed')}</wp:ping_status>
<wp:post_name>${cdata(o.slug)}</wp:post_name>
<wp:status>${cdata(o.status ?? 'publish')}</wp:status>
<wp:post_parent>${o.parent ?? 0}</wp:post_parent>
<wp:menu_order>${o.menuOrder ?? 0}</wp:menu_order>
<wp:post_type>${cdata(o.type ?? 'post')}</wp:post_type>
<wp:post_password>${cdata(o.password ?? '')}</wp:post_password>
<wp:is_sticky>0</wp:is_sticky>
${o.attachmentUrl ? `<wp:attachment_url>${cdata(o.attachmentUrl)}</wp:attachment_url>\n` : ''}${(o.cats ?? []).map((c) => `<category domain="category" nicename="${c.slug}">${cdata(c.name)}</category>`).join('\n')}
${(o.tagNames ?? []).map((t) => `<category domain="post_tag" nicename="${t}">${cdata(t)}</category>`).join('\n')}
${(o.meta ?? []).map(([k, v]) => meta(k, v)).join('\n')}
${(o.comments ?? []).map((c) => `<wp:comment><wp:comment_id>${c.id}</wp:comment_id><wp:comment_author>${cdata(c.author)}</wp:comment_author><wp:comment_author_email>${cdata(c.email)}</wp:comment_author_email><wp:comment_author_url></wp:comment_author_url><wp:comment_author_IP></wp:comment_author_IP><wp:comment_date>${cdata(stamp(c.date))}</wp:comment_date><wp:comment_date_gmt>${cdata(stamp(c.date))}</wp:comment_date_gmt><wp:comment_content>${cdata(c.content)}</wp:comment_content><wp:comment_approved>${cdata('1')}</wp:comment_approved><wp:comment_type>${cdata('comment')}</wp:comment_type><wp:comment_parent>${c.parent}</wp:comment_parent><wp:comment_user_id>0</wp:comment_user_id></wp:comment>`).join('\n')}
</item>`;

  const items = [];
  const counts = {
    posts: { publish: 0, draft: 0, private: 0, protected: 0 },
    pages: 0,
    categories: categories.length,
    tags: tags.length,
    attachments: attachments.length,
    comments: 0,
    companies: data.companies.length,
    featured: 0,
    vietnamese: 0,
    twoCategories: 0,
  };
  for (const a of attachments) {
    items.push(
      item({
        id: a.id,
        title: a.title,
        slug: a.name.replace(/\.png$/, ''),
        date: START,
        type: 'attachment',
        status: 'inherit',
        attachmentUrl: `${MEDIA}/${a.name}`,
        meta: [
          ['_wp_attachment_image_alt', a.alt],
          ['_joo_demo', '1'],
        ],
      })
    );
  }
  for (const p of specs) {
    const cats = [catOf(p.cat), ...(p.cat2 ? [catOf(p.cat2)] : [])];
    const postMeta = [
      ['_joo_demo', '1'],
      ['_joo_lang', p.lang],
    ];
    if (p.featured) {
      postMeta.push(['_joo_featured', '1'], ['_joo_featured_order', String(p.featured)]);
      counts.featured += 1;
    }
    if (p.translationOf) postMeta.push(['_joo_translation_of_slug', p.translationOf]);
    if (p.image) postMeta.push(['_thumbnail_id', String(p.image.id)]);
    if (p.cat2) postMeta.push(['_joo_primary_category_slug', p.cat]);
    const comments = p.comments ? commentsFor(p) : [];
    counts.comments += comments.length;
    items.push(
      item({
        ...p,
        content: bodyOf(p),
        cats,
        tagNames: p.tags,
        meta: postMeta,
        comments,
        author: p.kind === 'comments' || p.index % 7 === 0 ? 'editor' : 'viet',
      })
    );
    if (p.status === 'publish' && !p.password) counts.posts.publish += 1;
    else if (p.status === 'draft') counts.posts.draft += 1;
    else if (p.status === 'private') counts.posts.private += 1;
    else counts.posts.protected += 1;
    if (p.kind === 'vi') counts.vietnamese += 1;
    if (p.cat2) counts.twoCategories += 1;
  }
  const pages = [
    { slug: 'about', title: 'About', content: `${heading('About me')}\n\n${para(3)}` },
    {
      slug: 'contact',
      title: 'Contact',
      content: `${heading('Contact')}\n\n<!-- wp:paragraph -->\n<p>hello@jooservices.com</p>\n<!-- /wp:paragraph -->`,
    },
    {
      slug: 'privacy',
      title: 'Privacy',
      content: `${heading('Privacy')}\n\n<!-- wp:paragraph -->\n<p>This site sets no tracking cookies.</p>\n<!-- /wp:paragraph -->`,
    },
  ];
  pages.forEach((pg, i) => {
    items.push(
      item({
        id: id(),
        type: 'page',
        title: pg.title,
        slug: pg.slug,
        content: pg.content,
        excerpt: '',
        date: START + i * 86400e3,
        meta: [['_joo_demo', '1']],
      })
    );
    counts.pages += 1;
  });
  data.companies.forEach((c, i) => {
    items.push(
      item({
        id: id(),
        type: 'joo_company',
        title: c.name,
        slug: c.name.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
        excerpt: c.description,
        content: '',
        date: START + i * 86400e3,
        commentStatus: 'closed',
        meta: [
          ['_joo_demo', '1'],
          ['_joo_company_role', c.role],
          ['_joo_company_from', c.from],
          ['_joo_company_to', c.to],
          ['_joo_company_pinned', c.pinned ? '1' : ''],
          ['_joo_company_url', c.url],
        ],
      })
    );
  });

  const termMeta = (m) =>
    Object.entries(m)
      .map(
        ([k, v]) =>
          `<wp:termmeta><wp:meta_key>${cdata(k)}</wp:meta_key><wp:meta_value>${cdata(v)}</wp:meta_value></wp:termmeta>`
      )
      .join('');
  const xml = `<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
<title>JOOservices demo</title>
<link>http://example.test</link>
<description>Deterministic demo content (seed ${seed})</description>
<pubDate>${rfc(END)}</pubDate>
<language>en-US</language>
<wp:wxr_version>1.2</wp:wxr_version>
<wp:base_site_url>http://example.test</wp:base_site_url>
<wp:base_blog_url>http://example.test</wp:base_blog_url>
<wp:author><wp:author_id>2</wp:author_id><wp:author_login>${cdata('viet')}</wp:author_login><wp:author_email>${cdata('viet@example.test')}</wp:author_email><wp:author_display_name>${cdata('Viet VU')}</wp:author_display_name><wp:author_first_name>${cdata('Viet')}</wp:author_first_name><wp:author_last_name>${cdata('VU')}</wp:author_last_name></wp:author>
<wp:author><wp:author_id>3</wp:author_id><wp:author_login>${cdata('editor')}</wp:author_login><wp:author_email>${cdata('editor@example.test')}</wp:author_email><wp:author_display_name>${cdata('Demo Editor')}</wp:author_display_name><wp:author_first_name>${cdata('Demo')}</wp:author_first_name><wp:author_last_name>${cdata('Editor')}</wp:author_last_name></wp:author>
${categories.map((c, i) => `<wp:category><wp:term_id>${10 + i}</wp:term_id><wp:category_nicename>${cdata(c.slug)}</wp:category_nicename><wp:category_parent>${cdata(c.parent)}</wp:category_parent><wp:cat_name>${cdata(c.name)}</wp:cat_name><wp:category_description>${cdata(c.description)}</wp:category_description>${termMeta({ _joo_demo: '1', joo_short_description: c.description })}</wp:category>`).join('\n')}
${tags.map((t, i) => `<wp:tag><wp:term_id>${40 + i}</wp:term_id><wp:tag_slug>${cdata(t.slug)}</wp:tag_slug><wp:tag_name>${cdata(t.name)}</wp:tag_name><wp:tag_description>${cdata(t.intro)}</wp:tag_description>${termMeta({ _joo_demo: '1', joo_intro: t.intro })}</wp:tag>`).join('\n')}
${items.join('\n')}
</channel>
</rss>
`;
  void rand;

  const menus = {
    primary: [
      { title: 'Blog', type: 'category', slug: 'blog' },
      { title: 'AI', type: 'category', slug: 'ai' },
      { title: 'R&D', type: 'category', slug: 'research-and-development' },
      { title: 'About', type: 'page', slug: 'about' },
    ],
    footer: [
      { title: 'Privacy', type: 'page', slug: 'privacy' },
      { title: 'Contact', type: 'page', slug: 'contact' },
    ],
  };
  const settings = {
    options: {
      blogname: 'JOOservices',
      blogdescription: 'Technical notes on AI, engineering and R&D',

      posts_per_page: '10',
      show_on_front: 'posts',
      default_comment_status: 'open',
      timezone_string: 'UTC',
    },
    profile: {
      user: 'viet',
      role: 'administrator',
      meta: {
        joo_title: 'Software engineer · Founder',
        joo_short_bio: 'I build developer tools, AI agents and WordPress products, and write down what I learn.',
        joo_socials: [
          { label: 'GitHub', url: 'https://github.com/jooservices' },
          { label: 'LinkedIn', url: 'https://www.linkedin.com/in/jooservices' },
        ],
      },
      avatar: attachments[0].name,
    },
    users: [{ login: 'editor', role: 'editor' }],
    topics: {
      images: Object.fromEntries(
        ['mcp', 'llm', 'laravel', 'docker'].map((t, i) => [t, attachments[(i + 4) % attachments.length].name])
      ),
    },
  };
  const manifest = { seed, generator: 1, counts, expected: { items: items.length, mediaFiles: media.length } };
  return { xml, media, menus, settings, manifest };
}

const args = process.argv.slice(2);
const flag = (name, fallback) => (args.includes(name) ? args[args.indexOf(name) + 1] : fallback);
if (import.meta.url === `file://${process.argv[1]}`) {
  const out = flag('--out', 'demo');
  const result = build(Number(flag('--seed', '42')));
  mkdirSync(join(out, 'media'), { recursive: true });
  writeFileSync(join(out, 'content.xml'), result.xml);
  for (const m of result.media) writeFileSync(join(out, 'media', m.name), m.buffer);
  writeFileSync(join(out, 'menus.json'), JSON.stringify(result.menus, null, 2) + '\n');
  writeFileSync(join(out, 'settings.json'), JSON.stringify(result.settings, null, 2) + '\n');
  writeFileSync(join(out, 'manifest.json'), JSON.stringify(result.manifest, null, 2) + '\n');
  console.log(`wrote ${out}: ${result.manifest.expected.items} items, ${result.media.length} media files`);
}
