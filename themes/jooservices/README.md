# JOOservices theme

A dark-first technical blog block theme built on `jooservices/wp-theme-core`: left rail, featured slider, post list, a read-only terminal dock on the right, a terminal-style 404, EN/VI, SEO, lazy loading and performance budgets.

## Requirements
WordPress 6.6 or newer (tested on 6.6 and the latest release), PHP 8.1 or newer. The companion plugin `jooservices-content` provides the Company type and the term, user and featured fields; without it the theme still works and the rail blocks hide themselves.

## Install
1. Build the package: `make package THEME=jooservices` (writes `artifacts/package/jooservices-<version>.zip`).
2. Upload the zip under Appearance, Themes, Add New, or `wp theme install jooservices-<version>.zip --activate`.
3. Install and activate the companion plugin (`plugins/jooservices-content`).
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
1. Update `Version` in `style.css` and the entry in `CHANGELOG.md`; set `Tested up to` to the newest WordPress you ran the gates on.
2. Run every gate above on the latest WordPress and on 6.6 (`JOOWT_PROJECT=joowt-wp66 WP_VERSION=6.6 ...`).
3. `make package THEME=jooservices`, install the zip on a clean site, run `make health`.
4. Tag the commit `jooservices-<version>`. Nothing is pushed by the tooling.

## Rollback
Re-install the previous zip (`wp theme install previous.zip --force`). Settings are stored in the `joo_jooservices_settings` option and are forward and backward compatible within a minor version; content is not touched by the theme.
