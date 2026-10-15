# SEO head module

Namespace `Seo\`. Switches: `seo.enabled`, `modules.seo.enabled`, `seo.yield` (auto|never|always), `seo.yield_hreflang`, `seo.paged_noindex`, `seo.title_separator`, `seo.title_template.*`, `seo.default_og_image`, `seo.twitter_site`.

## Output order (`HeadPrinter`, `wp_head` priority 1)
1. `<title>` via `pre_get_document_title` (TitleBuilder)
2. `<meta name="description">` (not on search/404/restricted posts)
3. `<link rel="canonical">` (none on 404/search/date/restricted)
4. `<link rel="alternate" hreflang>` incl. `x-default` (only with >= 2 languages)
5. Open Graph then Twitter tags (indexable pages only)
6. action `joo/seo/head_end` (JSON-LD, story C8)

Robots go through WordPress `wp_robots`; `X-Robots-Tag` is sent on `template_redirect` for non-indexable contexts. Core `rel_canonical` and `wp_shortlink_wp_head` are removed while core prints SEO. Nothing is attached for feeds, embeds, REST and AJAX.

## Robots matrix
home/single/page/archives: `index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1`; date, search, 404, attachment, posts with `_joo_seo_noindex`: `noindex, follow`; password-protected/private: `noindex, nofollow`; paged archives `noindex, follow` only with `seo.paged_noindex`. Attachment pages 301 to a published parent for visitors.

## Titles and descriptions
Per-post `_joo_seo_title`, else the template for the context (`%title% %sitename% %tagline% %category% %author% %page% %sep%`; unknown variables are removed, dangling separators trimmed). Hard limit 70 characters: the `%title%` part is shortened first so the site name survives. Descriptions: `_joo_seo_description`, manual excerpt, auto summary (155 characters, sentence boundary), term short description / description, author bio, tagline. Password-protected posts never expose their content.

## Social image chain
`_joo_seo_og_image` -> featured image -> first `core/image` block with an attachment -> `seo.default_og_image`. Per image the smallest size with width >= 1200 is preferred, otherwise the largest with width >= 600; smaller images are skipped (card falls back to `summary`).

## Languages
Built-in tagging: `_joo_lang` and `_joo_translation_of` (validated on save: no self reference, target must exist and must not be a translation itself). Adapters supply alternates through `joo/seo/alternates`. `<html lang>` follows `_joo_lang`.

## Yield
`Compat\Detector` recognises Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework and Slim SEO by their version constants. In `auto` core prints nothing while one is active. Site Health shows the state and warns when two SEO plugins are active.

## Extension points
`joo/seo/context`, `joo/seo/title`, `joo/seo/description`, `joo/seo/canonical`, `joo/seo/canonical_query_args`, `joo/seo/allow_external_canonical`, `joo/seo/robots`, `joo/seo/og`, `joo/seo/alternates`, `joo/seo/x_default`, `joo/seo/yield`, `joo/seo/head_end`.

## Editor
Panel "SEO" (title, description with live counters and a search-result preview from `resources/editor/serp.js`, noindex, canonical, social image) and panel "Language".
