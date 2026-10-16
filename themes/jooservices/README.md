# JOOservices theme

A dark-first technical blog block theme built on `jooservices/wp-theme-core`: left rail, featured slider, post list, a read-only terminal dock on the right, a terminal-style 404, EN/VI, SEO, lazy loading and performance budgets.

## Requirements
WordPress 6.6 or newer, PHP 8.1 or newer. Release QA runs the minimum supported WordPress version (6.6) and the latest release before packaging. JOOservices Resume (`plugins/jooservices-resume`) provides the Company type and the profile, topic and featured-post fields; without it the theme still works and blocks that need its data hide themselves.

## Install
1. Build the package: `make package THEME=jooservices` (writes `artifacts/package/jooservices-<version>.zip`).
2. Upload the zip under Appearance, Themes, Add New, or `wp theme install jooservices-<version>.zip --activate`.
3. To add company and profile content, extract `jooservices/plugins/jooservices-resume.zip`
   from the theme download, then upload and activate it under Plugins, Add New,
   Upload Plugin. The source lives in `plugins/jooservices-resume/` in this repo.
4. Appearance, Menus: assign a menu to **Primary menu**. Appearance, Editor: the header uses the Navigation block.
5. Users, your profile: set Title, Short bio, socials and avatar (they feed the profile card and the author box).
6. Posts: tick **Featured** (and an order) on up to five posts for the slider.
7. Settings, JOOservices: SEO, lazy loading, performance and terminal options. The owner can switch lazy loading off globally or per type.

## Configuration
`joo-theme.json` is the manifest (modules, features, fonts, image sizes, SEO, lazy and terminal defaults). Settings saved in the admin override it. `features.hide_featured_in_list` hides the slider posts from the first page of the list.

## Design
`design/` holds the approved reference, a checklist, the tokens and the list of deviations. Colours come from `theme.json`; `styles/light.json` is the light scheme and `assets/src/css/components/tokens.css` repeats it for the runtime switch (a test keeps them identical). Fonts are self-hosted subsets (`assets/fonts/README.md`).

## Quality gates
| Gate | Command |
|---|---|
| Lint and static analysis | `make lint`, `make analyse` |
| Design contract (tokens, contrast, fonts) | `npx vitest run themes/jooservices` |
| Integration | `make test-integration THEME=jooservices TEST_THEME=jooservices` |
| End to end (3 viewports) | `E2E_THEME=jooservices make test-e2e THEME=jooservices` |
| Performance budgets | `make perf THEME=jooservices` |
| Plugin matrix | `make test-matrix THEME=jooservices` |

## Releasing
`style.css` and the Resume plugin main-file header are the canonical SemVer values. Mirror them in the theme and plugin changelogs, `Plugin::VERSION`, and the plugin readme stable tag. See the [theme changelog](CHANGELOG.md). `node tools/release-check.mjs` validates the mirrors, WordPress/PHP requirements, GPL notices, and the ThemeForest preview image. See [the release policy](../../docs/release-policy.md).

After all gates pass on the latest WordPress and 6.6, run `make release THEME=jooservices WP=7.1.3`, replacing `7.1.3` with the exact latest WordPress version tested. It builds the theme and JOOservices Resume zips, the ThemeForest main download, demo-content and preview zips, checksums, a dependency list, and an evidence report. The release tool performs two full builds and fails if the archive bytes differ. Use `make release THEME=jooservices WP=7.1.3 TAG=1` only when the release is approved; this creates local `theme-<version>` and `plugin-<version>` tags and never pushes.

## Rollback
Re-install the previous theme and Resume plugin zips. Settings are stored in the `joo_jooservices_theme_settings` option; company records use the stable `joo_company` post type and `_joo_company_*` metadata. Code rollback does not require a database rollback. See the [release and recovery policy](../../docs/release-policy.md).
