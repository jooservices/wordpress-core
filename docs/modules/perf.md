# Performance module

| Part | What it does | Settings / manifest |
|---|---|---|
| Images | registers manifest `imageSizes` (with `retina: true` adding a `-2x` size of the same aspect ratio so WordPress can build a `srcset`), named `sizes` presets (`images.sizesPresets`), `Images::tag()` (width, height, srcset, sizes, decoding), LCP marker (eager, `fetchpriority=high`, preload with `imagesrcset`), WebP sub-sizes when the server supports them, dimensions for content images, `max_srcset_image_width` 1920 | manifest `imageSizes`, `images` |
| Fonts | `@font-face` with `font-display`, a metric-adjusted fallback face, at most two preloaded files | manifest `perf.fonts` |
| Hints | preconnect origins, removes the emoji DNS prefetch | `perf.preconnect` |
| Speculation | core Speculation Rules API on WordPress 6.8+, own `<script type="speculationrules">` before; none for logged-in users, previews, Save-Data | `perf.speculation` off / conservative / moderate |
| Bloat | emoji, wp-embed, Dashicons for visitors, per-block CSS on demand, head noise (generator, RSD, WLW, shortlink; feeds stay) | `perf.disable_emoji`, `disable_embeds`, `dashicons_guests`, `block_css_granular`, `remove_head_noise` |
| Fragment cache | `FragmentCache::remember($key, $ttl, $producer, $tags, $module)`; keys carry a global version (`joo_cache_version`) and a version per tag (`posts`, `terms`, `menus`, `profile`, `companies`, `settings`); soft TTL with a rebuild lock so one request refreshes while others serve the stale value | Tools tab: flush |
| HTTP caching | `CacheHeaders`: `Cache-Control: public, max-age=300, stale-while-revalidate=600`, weak `ETag`, `304` for conditional GETs on `joo/v1` routes | |
| Diagnostics | admin bar node (debug and `manage_options`), Tools tab snapshot and self-test, `wp joo doctor --perf` | `core.debug` |

## Notes
- Core already sets `loading` and `fetchpriority` on images since WordPress 6.3; the lazy-load policy (module `lazy`) hooks that API instead of reimplementing it.
- Removal of callbacks that WordPress registers at several priorities (for example `wp_oembed_add_discovery_links`) is priority-agnostic.
- In Docker the container also listens on `WP_PORT` so loopback requests to the site URL work.
