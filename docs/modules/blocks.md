# Block library foundation

All custom blocks are dynamic (server-rendered), so their HTML is in the first response.

## Adding a block
Create `packages/theme-core/resources/blocks/<slug>/block.json` (`apiVersion` 3, `name` `joo/<slug>`, `category` `joo`) and `render.php`. No PHP registration code is needed. Themes add folders with the `joo/blocks/register` filter. Disable blocks in the theme manifest: `"blocks": { "disabled": ["joo/term-image"] }`.

Optional files: `view.js` (script module) and `style.css`; the build writes hashed files and the registrar wires them as `joo-block-<slug>` (module id and style handle), so they load only on pages that render the block. They are not referenced from `block.json` (the schema forbids `viewScriptModule`/`style` there).

## Rendering
* `DynamicBlock` subclasses (`Blocks\Blocks\<StudlySlug>`) implement `name()` and `data($attrs, $block)`; blocks without a class use `GenericBlock` (data = attributes, adjustable with `joo/blocks/data/{slug}`).
* Template lookup (`TemplateLocator`): child theme `parts/joo/{slug}.php`, parent theme, then the block folder `render.php`. Templates get `$data`, `$attrs`, `$block`, `$wrapper` and must escape (`Html::e`, `Html::attrs`, `Html::link`, `Html::img`).
* A template exception returns an empty string (an HTML comment when `WP_DEBUG`), output buffers are closed and the error is logged.
* `joo/blocks/data/{slug}` and `joo/blocks/html/{slug}` filters; blocks may opt into `FragmentCache` with `cacheable()`/`cacheKey()` (never for logged-in users).

## Editor
`resources/editor/blocks.js` gives every block a `ServerSideRender` preview and inspector controls generated from `x-joo.controls` in `block.json` (`range`, `toggle`, `select`, `text`, `number`, `color`). Unknown types and controls for undeclared attributes are ignored.

## Patterns, scheme
`PatternLoader` registers the `jooservices` pattern category, patterns from manifest folders (`blocks.patterns`) and can remove the core patterns (`blocks.removeCorePatterns`). `ThemeScheme` adds `data-theme` to `<html>` (the no-JS default) and prints one inline script (<= 300 bytes, nonce through `joo/assets/inline_script_nonce`) that applies the stored choice (`localStorage` key `joo-theme`) or the system preference before first paint. With a single scheme no script is printed.
