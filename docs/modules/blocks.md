# Block library foundation

All custom blocks are dynamic (server-rendered), so their HTML is in the first response.

## Adding a block
Create `packages/theme-core/resources/blocks/<slug>/block.json` (`apiVersion` 3, `name` `joo/<slug>`, `category` `joo`) and `render.php`. No PHP registration code is needed. Themes add folders with the `joo/blocks/register` filter. Disable blocks in the theme manifest: `"blocks": { "disabled": ["joo/related-posts"] }`.

Optional files: `view.js` (script module) and `style.css`; the build writes hashed files and the registrar wires them as `joo-block-<slug>` (module id and style handle), so they load only on pages that render the block. They are not referenced from `block.json` (the schema forbids `viewScriptModule`/`style` there).

## Rendering
* `DynamicBlock` subclasses (`Blocks\Blocks\<StudlySlug>`) implement `name()` and `data($attrs, $block)`; blocks without a class use `GenericBlock` (data = attributes, adjustable with `joo/blocks/data/{slug}`).
* Template lookup (`TemplateLocator`): child theme `parts/joo/{slug}.php`, parent theme, then the block folder `render.php`. Templates get `$data`, `$attrs`, `$block`, `$wrapper` and must escape (`Html::e`, `Html::attrs`, `Html::link`, `Html::img`).
* A template exception returns an empty string (an HTML comment when `WP_DEBUG`), output buffers are closed and the error is logged.
* `joo/blocks/data/{slug}` and `joo/blocks/html/{slug}` filters; blocks may opt into `FragmentCache` with `cacheable()`/`cacheKey()` (never for logged-in users).

## Editor
`resources/editor/blocks.js` gives every block a `ServerSideRender` preview and inspector controls generated from `x-joo.controls` in `block.json` (`range`, `toggle`, `select`, `text`, `number`, `color`). Unknown types and controls for undeclared attributes are ignored.

## Patterns, scheme
`PatternLoader` registers the `theme-core` pattern category by default; themes can choose its ID and label with `blocks.patternCategory` and `blocks.patternCategoryLabel`. Patterns come from manifest folders (`blocks.patterns`) and core patterns can be removed with `blocks.removeCorePatterns`. The `joo` block category is labelled `Theme Core` by default and can be named with `blocks.categoryLabel`. `ThemeScheme` adds `data-theme` to `<html>` (the no-JS default) and prints one inline script (<= 300 bytes, nonce through `joo/assets/inline_script_nonce`) that applies the stored choice (`localStorage` key `joo-theme`) or the system preference before first paint. With a single scheme no script is printed.

## Content blocks (C12)
| Block | Notes |
|---|---|
| `joo/breadcrumbs` | Same `TrailBuilder` trail as the BreadcrumbList; `separator`, `showHome`, `maxLabel`; no JS |
| `joo/featured-slider` | `ContentProvider::featured()`; scroll-snap track (works without JS), first image eager with `fetchpriority=high`, `<joo-slider>` adds buttons, dots, keyboard, autoplay with a pause button (WCAG 2.2.2), paused on hover, focus, touch, hidden tab and reduced motion. View module 1,176 B gzip (5,120 B budget) |
| `joo/post-meta` | category, date, updated (only when more than a day later), reading time, author, tags; Query Loop aware |
| `joo/toc`, `joo/reading-progress` | ids from `HeadingAnchors` (the heading filter and the TOC compute the same ids); `<joo-toc>` scroll-spy sets `aria-current="location"` |
| code blocks | `CodeEnhancer` wraps `core/code` with a language label and a copy button (hidden until `code-copy.js` loads; the module is enqueued only on pages with code) |
| `joo/related-posts` | deferred as a `<joo-lazy>` fragment `related`; identical HTML inline and over REST |
| `joo/author-box` | owner profile, else WordPress user data; `rel="author"`, socials `rel="me noopener"` |
| `joo/theme-toggle`, `joo/lang-switcher`, `joo/search-trigger` | toggle hidden without JS and with one scheme; switcher from `Compat\Multilingual` (nothing with one language); search link opens a `<dialog>` (`/` shortcut) |

Reading time: `Support\ReadingTime::forPost()` (code at 25%, 220 wpm English / 200 Vietnamese) is computed on `save_post`, cached in `_joo_reading_cache` and used by the post-meta block, post cards and JSON-LD `timeRequired`/`wordCount`.

## Sidebar blocks (C13)
All five render their content on the server. Only `companies`, `category-tree`, `top-topics` and `tag-list` extend `Blocks\FragmentBlock` and support fragment deferral; `profile-card` is server-rendered but is not deferred as a fragment. The fragment endpoint calls the same method as the inline path, so the HTML is identical for the four fragment blocks.

| Block | Notes |
|---|---|
| `joo/profile-card` | About me; links to the page with slug `about`, else the author archive (`linkTo`); nothing without JOOservices Resume |
| `joo/companies` | pinned "current company" card plus a list; pager links are real URLs (`?joo_companies_page=N#companies`, rendered server-side and clamped to the last page); `<joo-companies>` (1,594 B gzip, 2,560 B budget) swaps pages in place from `/joo/v1/companies`, moves focus to the heading, announces "Page N of M", caches pages, aborts stale requests and offers a retry on errors. The query variable is not part of the canonical URL With the Resume plugin the card shows the newest role (`_joo_company_roles`, newest first, versioned `{"v":1,"items":[...]}` or a plain list) instead of the single role field and the public summary (`_joo_company_summary`) instead of the excerpt; only these two public values are read, so the plugin stays independent of the theme and private fields are never touched. For the full timeline (roles, achievements, technologies, public custom fields) use the plugin's `joo/resume` block, as the demo About page does. |
| `joo/category-tree` | nested list with counts and descriptions; every branch is visible without JS; `<joo-tree>` (563 B gzip, 2,048 B budget) collapses them (the branch with the current page stays open, `expandedDepth` keeps levels open, state in `sessionStorage`) |
| `joo/top-topics` | image 44x44 (placeholder with initials), count, intro; `strategy` `inherit` uses `content.topics_strategy` / `content.topics_limit` |
| `joo/tag-list` | chips or cloud (weights 0.85 to 1.15 rem), `rel="tag"`, cached with the `terms` tag |
