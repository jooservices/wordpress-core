# Lazy-load module

Everything below is switchable: `lazy.enabled` (master), `lazy.images|iframes|fragments|sections|comments|terminal`, `lazy.eager_count`, `lazy.embed_thumbnails` (`local|remote`), `lazy.yield_to_plugins`. A post can override with the editor panel (`_joo_lazy_override`: default/on/off).

## Decision (`Lazy\Policy::decide`)
Order: master/module off → non-front-end context → post override `off` → per-type switch (skipped when override is `on`) → yield to an optimisation plugin (`joo/lazy/provider` filter, images and iframes) → LCP / first `lazy.eager_count` images stay eager → default lazy/eager result. The final `joo/lazy/should_lazy` filter runs on every path and can override that result. Every `Decision` carries a `reason` code (`Decision::$reason`); no WP-CLI command prints it.

## Pieces
| Piece | Class | Result |
|---|---|---|
| Images / iframes | `ImageLazy` | `loading` attribute via the WordPress API; explicit `fetchpriority`/`loading=eager` always wins |
| Video embeds | `EmbedFacade` | `<joo-embed>` click-to-load; no third-party request before the click; link is the no-JS fallback; providers extendable with `joo/lazy/embed_providers` |
| Fragments | `FragmentLazy` | `<joo-lazy>` with placeholder (reserved height), `<noscript>` real HTML and a REST URL |
| Sections | `SectionLazy` | `content-visibility` class for manifest `lazy.sectionBlocks`; core/comments deferred behind a button |
| REST | `Rest\FragmentController` | Public `GET /joo/v1/fragment/{name}` with a 1–64 character registered name, validated args, 404 unknown/missing/non-public snapshot, 400 invalid |

Renderer fragments use the same function inline and over REST (byte-identical). Snapshot fragments (template-bound blocks) store the rendered HTML in `FragmentCache` when an anonymous request renders a published, unprotected post. The comments fragment is served with `Cache-Control: private, no-store`; snapshots for draft, private, or password-protected posts are never returned.

## Client
`<joo-lazy>` and `<joo-embed>` are small script modules loaded only when such an element exists. Fragment HTML is inserted through a sanitising `safeFragment`.

Browser checks (scroll triggers, CLS, no-JS) run in the theme e2e (T10.2).
