# Core theme assumptions audit

Audit date: 2026-10-20

Repository: `wordpress-core`, branch `JOOWT-258-core-r6-1-audit-the-core-for-first-theme`

This is a read-only audit. No application code or tests were changed. “Owner decision” is the proposed disposition for review: `neutralise`, `keep`, or `document`.

## Coverage and method

Inspected `packages/theme-core/src`, `packages/theme-core/resources`, including every block template under `resources/blocks`, the PHP block classes under `src/Blocks/Blocks`, terminal PHP and JavaScript, block metadata, and the theme manifests and examples under `docs/spec`. Searches covered user-facing strings, JavaScript strings, default slugs and IDs, sample paths, English-only copy, and JOOservices-specific names.

The footer/copyright/powered-by search returned no matches in `resources/blocks` or `src/Blocks/Blocks`. PHP block-template strings use WordPress gettext calls, all 17 block metadata files declare `textdomain: joo-core`, and the core has English and Vietnamese catalogs. The remaining localization findings below concern strings that bypass those paths. The slug fallback derives from the installed theme directory name (`src/Config/ManifestLoader.php:53`; `src/Config/Manifest.php:45,117`), rather than the first theme's slug.

## Findings

### A1. The not-found retry input suggests a first-theme article path

- **File and line:** `packages/theme-core/resources/blocks/not-found/render.php:46`
- **Assumption:** The example path `/ai/prompt-caching` is a JOOservices AI article path. It appears in the live 404 form as the input placeholder.
- **Proposed fix:** Use a neutral placeholder such as `/example-path`, or omit the placeholder. This is a neutral default.
- **Owner decision:** **neutralise**

### A2. The profile card assumes an `about` page slug

- **File and line:** `packages/theme-core/src/Blocks/Blocks/ProfileCard.php:44`; default block value: `packages/theme-core/resources/blocks/profile-card/block.json:14`
- **Assumption:** The default profile-card link should resolve to the page at the literal path `about`. A theme with a different page slug silently falls back to the profile URL.
- **Proposed fix:** Add a manifest key for the profile page path or ID, or default to the profile URL unless a page is explicitly configured. This is a manifest key.
- **Owner decision:** **neutralise**

### A3. Editor categories expose the first publisher name

- **File and line:** `packages/theme-core/src/Blocks/BlockRegistrar.php:73,82`; `packages/theme-core/src/Blocks/PatternLoader.php:15,57,95`
- **Assumption:** The blocks category title, pattern category ID/title, and missing pattern-category default are branded `JOOservices`/`jooservices`, regardless of the active theme. The `joo` block namespace itself is a separate public API convention (see A10).
- **Proposed fix:** Keep stable category IDs where needed, but use neutral display defaults such as “Theme Core” or a manifest-supplied category label. This is a manifest key or neutral default.
- **Owner decision:** **neutralise**

### A4. The default content provider is gated on JOO Resume

- **File and line:** `packages/theme-core/src/Content/ContentModule.php:57-80`; JOO-specific fields and post type: `packages/theme-core/src/Content/WpContentProvider.php:104-116,477-530`; schema use: `packages/theme-core/src/Seo/Schema/Pieces/PersonPiece.php:85-111`
- **Assumption:** Even when `content.provider` is `wp`, the module selects `NullProvider` unless the `joo_company` post type exists or JOO Resume is loaded before WordPress `init`. The WP provider also reads `joo_*` user metadata and `joo_company` posts. A second theme without that companion plugin can therefore have no content provider for otherwise ordinary WordPress content features.
- **Proposed fix:** Use the WordPress provider whenever `content.provider` is `wp`; treat company/profile companion data as optional, or isolate the JOO Resume mapping behind a selectable adapter. This is a neutral default, with a manifest/provider key for the optional integration.
- **Owner decision:** **neutralise**

### A5. Default terminal shortcut searches for Docker

- **File and line:** `packages/theme-core/resources/js/terminal/ui.js:44`
- **Assumption:** The terminal should suggest `grep -ri docker .`, which presumes the site's content is about Docker. The terminal filesystem itself is built from the active site's posts and categories (`src/Terminal/FsBuilder.php:94-164`).
- **Proposed fix:** Replace the shortcut with a content-neutral command or make suggested commands theme-configurable. This is a neutral default or manifest key.
- **Owner decision:** **neutralise**

### A6. Terminal command help and responses are English-only

- **File and line:** `packages/theme-core/resources/js/terminal/commands/system.js:23-27,38-42`; `commands/site.js:16,29,38,41,58,73`; `commands/files.js:16-27`
- **Assumption:** The terminal's help, command summaries, errors, and file labels are hard-coded English. These paths do not call `t()`; the shared `JsStrings` map covers terminal chrome and selected status text, not these command modules (`src/I18n/JsStrings.php:44-60`).
- **Proposed fix:** Route visible help and response copy through the existing runtime translation map, with gettext-backed entries. This is a neutral default with translatable strings.
- **Owner decision:** **neutralise**

### A7. The virtual terminal README includes untranslated English copy

- **File and line:** `packages/theme-core/src/Terminal/FsBuilder.php:146-150`
- **Assumption:** The generated `/README.md` always appends an English explanation of the virtual filesystem and its read-only behavior, even for translated sites. The configurable banner does not cover that sentence.
- **Proposed fix:** Mark the sentence for gettext translation or expose the explanatory copy through a manifest key. This is a manifest key or translatable neutral default.
- **Owner decision:** **neutralise**

### A8. Custom block inspector labels bypass translation

- **File and line:** `packages/theme-core/src/Blocks/EditorBundle.php:57-63,100`; `packages/theme-core/resources/editor/blocks.js:22,35-36`; example labels: `packages/theme-core/resources/blocks/companies/block.json:19-23`
- **Assumption:** The `x-joo.controls` labels and select-option labels are copied from JSON into JavaScript and rendered as raw strings. The editor script reads `control.label` and `option[1]` without `wp.i18n` or the runtime translation map, so the block metadata `textdomain` does not localize these custom controls.
- **Proposed fix:** Neutralise the editor copy by translating custom control labels/options before serialization or looking them up through the editor's WordPress i18n API.
- **Owner decision:** **neutralise**

### A9. Portfolio-specific blocks are registered unless each theme opts out

- **File and line:** `packages/theme-core/src/Blocks/BlockRegistrar.php:114-126`; `packages/theme-core/resources/blocks/companies/block.json:5-7`; `packages/theme-core/resources/blocks/profile-card/block.json:5-7`; second-theme example: `docs/spec/examples/magazine.json:47-49`
- **Assumption:** The registrar enables every bundled block by default, including “Companies” (current and previous employers) and “Profile card (About me)”. The magazine example has to disable these by name. These are valid optional features, but their portfolio semantics are not neutral to every theme.
- **Proposed fix:** Keep these as optional core features and document the `blocks.disabled` manifest entries for themes that do not use them; consider an opt-in list if the default inserter should be domain-neutral. This is by design/documentation.
- **Owner decision:** **document**

### A10. Terminal test data represents the first theme, but is fixture-only

- **File and line:** `packages/theme-core/resources/js/terminal/__fixtures__/demo-fs.json:5-15,60-69,130-139,345-350`
- **Assumption:** The checked-in terminal fixture contains `jooservices.com`, user `viet`, `/ai` categories, JOOservices article paths, and the corresponding first-theme URLs and copy. Tests import this fixture; the production filesystem is generated from the current site's name, host, users, terms, and posts (`src/Terminal/FsBuilder.php:94-164,172-182`).
- **Proposed fix:** Keep the fixture clearly test-only; use neutral fixture data only if it is reused in consumer-facing demos. This is by design.
- **Owner decision:** **keep**

## Reviewed and retained by design

### Core package namespace and internal identity

- **File and line:** `packages/theme-core/src/Blocks/BlockRegistrar.php:81-83,122`; `packages/theme-core/resources/js/terminal/commands/system.js:49,150`
- **Assumption:** Public block names use `joo/…`, the registrar validates that namespace, and the terminal identifies its implementation as `joo-sh`/`joo`. These identify the core API and implementation rather than the active theme's content.
- **Proposed fix:** By design, retain the stable public namespace and implementation identifier.
- **Owner decision:** **keep**

## Verification

- Read-only searches and source inspection only; no tests were specified by the ticket and none were run.
- No source code was changed. The report is the complete output for this ticket.
