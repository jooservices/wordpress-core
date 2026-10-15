# Assets module

## What it does
- Builds JS (ES modules, esbuild) and CSS (Lightning CSS) into content-hashed files with `assets.json` (`node tools/build-assets.mjs --root <package-or-theme>`). Top-level files in `resources/js/` and `resources/css/` are entry points; subfolders hold shared code (emitted as chunks); `resources/blocks/<slug>/view.js` and `style.css` are per-block entries.
- Registers script modules with WordPress (`wp_register_script_module`) and styles, enqueues only what the request context needs, and strips the `ver` query string from hashed assets.
- Prints the page configuration as `<script type="application/json" id="joo-config">` (data only, `JSON_HEX_*` encoded) for the runtime.
- Inlines critical CSS (`<style id="joo-critical">`) and moves deferred styles to the footer when it exists.
- Prints at most four preload links; applies the jQuery policy.

## Registering assets
```php
add_action( 'joo/assets/register', static function ( \JOOservices\ThemeCore\Assets\AssetRegistry $assets ): void {
	$assets->addManifest( 'theme', \JOOservices\ThemeCore\Assets\AssetManifest::fromFile( get_theme_file_path( 'assets/build/assets.json' ), get_theme_file_uri( 'assets/build' ) ) );
	$assets->module( 'joo-slider', 'slider', array( 'manifest' => 'theme', 'when' => 'home' ) );
	$assets->style( 'joo-theme', 'main', array( 'manifest' => 'theme', 'defer' => true, 'preload' => true ) );
} );
```
Options: `when` (`any|home|front|single|page|singular|archive|search|404` or callable), `manifest`, `lazy` (module is registered and listed in the runtime config but never enqueued; import it with `load()`), `deps`, `defer` (styles: footer when critical CSS exists), `preload`, `media`.

## Runtime (`joo-runtime`)
`config()`, `feature(name)`, `t(key, fallback)`, `restUrl(path)`, `ready(fn)`, `define(tag, class)`, `idle(fn)`, `inView(el, cb, {rootMargin, once})` (one shared IntersectionObserver per margin), `load(name)`, `bus`, `store(key)`, `prefersReducedMotion()`, `saveData()`. Other modules import it as the script module id `joo-runtime` (marked external in the build, provided by the import map).

## Critical CSS
`node tools/critical.mjs --theme <slug>` visits the demo (home, single, archive, page, search, 404) at 390/820/1440 and writes `themes/<slug>/assets/critical/<context>.css` (limit 14 KB gzip each). A theme file overrides core's `resources/critical/<context>.css`.

## jQuery policy (`perf.jquery_frontend`)
`auto` (default) dequeues jQuery and jquery-migrate on the front end when no queued script depends on them; `keep`; `remove`.

## Caching
Serve `/resources/build/` and `/assets/build/` with `Cache-Control: public, max-age=31536000, immutable` (names are content-hashed).

## Hooks
`joo/assets/register` (action), `joo/assets/runtime_config` (filter), `joo/perf/preload` (filter).
