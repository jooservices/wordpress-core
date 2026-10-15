# Showing real site content in the local demo

Reads the PUBLIC REST API of a live WordPress site (GET only) and replaces the generated demo with it. Nothing is written to the live site.

1. Fetch: the script `tools/live-import.mjs` needs `artifacts/live/<site>.json` with `categories`, `tags`, `pages`, `posts` and `media` (each the paginated result of `/wp-json/wp/v2/<type>?per_page=100`). Drafts and private posts need an authenticated request (an application password); without it only published content is imported.
2. Import: `make seed-live THEME=jooservices [SOURCE=artifacts/live/jooservices.com.json]`. Images used by posts are downloaded once into `artifacts/live/media` and uploaded to the local Media Library; post HTML is kept as it is rendered on the site, with image URLs rewritten.
3. Local additions: the five newest posts with an image (plus any in a `featured` category) become the featured slider, posts with Vietnamese text are tagged `vi`, an About page is created when the site has none, and the companies come from the demo data.
4. Go back to the generated demo with `make seed-reset THEME=jooservices` (`make health` and the theme e2e specs assume that dataset).

## Plugins of the live site and what to run locally
`make demo-plugins` installs the plugins listed in `docker/demo-plugins.json` (same versions the live site runs at the time of writing).

| Plugin on jooservices.com | Local demo | Why |
|---|---|---|
| Yoast SEO 28.7 | installed | titles, meta descriptions, schema, sitemap; the theme yields to it. The import copies each post's SEO title and description and its primary category (so permalinks match) |
| SyntaxHighlighter Evolved 3.7.2 | installed, theme Midnight | 73 code blocks in the posts use its block; without it they render as plain boxes |
| Akismet 5.7.2 | installed | comment spam filter (no key needed to browse) |
| LiteSpeed Cache 7.9.1 | installed (optional) | the live server runs LiteSpeed; locally Apache, so only the settings screens and headers are comparable |
| Health Check 1.7.1 | installed (optional) | troubleshooting; its translation notice in the PHP log is its own |
| Really Simple Security 9.8.3 | not installed | two-factor and hardening: they would lock the demo admin; install by hand to test |
| Site Kit by Google 1.189.0 | not installed | needs a Google account |
| Advanced Google reCAPTCHA 5.42 | not installed | needs reCAPTCHA keys |
| JOOservices WordPress - MCP 1.4.5 | not installed | your own plugin (`projects/wordpress-mcp`); mount or zip it into `wp-content/plugins` if you want its custom post type locally |
| W3 Total Cache (inactive) | not installed | inactive on the live site |

Other plugins worth trying in the demo for a blog like this: Redirection (keep old URLs), Rank Math or SEOPress (alternative SEO, both pass the plugin matrix), Contact Form 7 or WPForms Lite for a contact page, Smush or EWWW for image optimisation, Polylang for real Vietnamese and English versions of posts, Query Monitor (development only).

## What was duplicated
| Item | Result on the local demo |
|---|---|
| Published posts | 102 of 102 with the same URL (`/<category>/<slug>/`) |
| Drafts | 10 as drafts (admin only) |
| Pages | Hosting (published); the draft "About me" is not copied, an About page is generated |
| Categories, tags | all 11 categories with the same tree, 294 tags in use; the Featured category is kept |
| Media | the whole image library (about 140 files), featured images attached |
| Menu | the Primary menu with its category tree (submenus) |
| Settings | site title, tagline, date and time format, week start, UTC+7, posts per page, comments open |
| SEO titles | 101 of 102 identical to the live titles (the last one differs only in how quotes are encoded) |
| Multi-page posts | page links after the content (36 `nextpage` breaks in the content) |
| Not copied | users other than the author, the one held comment, widgets (all inactive), Site Kit and reCAPTCHA keys, plugin options other than the code theme |
