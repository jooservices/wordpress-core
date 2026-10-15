# Second-theme thought experiment

Purpose: prove the contract is not tied to `jooservices`. Manifests: `docs/spec/examples/magazine.json` and `portfolio.json` (both validate with `node tools/validate-schemas.mjs`).

## magazine (grid home, no left rail, no terminal)
| Core module | Use |
|---|---|
| seo, perf, lazy, compat, i18n, content | enabled as is |
| blocks | featured slider (hero), post-meta, breadcrumbs, related posts, TOC, theme-toggle disabled via `features`; `joo/companies`, `joo/profile-card`, `joo/terminal-dock` disabled in `blocks.disabled` |
| terminal | module disabled |
Gaps found: none in core. Theme-owned: grid template, mega menu (core navigation block), image sizes `mag-hero`, `mag-tile`.

## portfolio (case studies, no blog features)
| Core module | Use |
|---|---|
| seo (siteType portfolio, ProfilePage), perf, lazy, compat, content | enabled |
| i18n, terminal | disabled |
| blocks | profile card, companies timeline layout, breadcrumbs; taxonomy blocks disabled; `canvas` template enabled for builder pages |
Gaps found: none. Case studies would be a theme- or plugin-owned custom post type (data survives theme switches), listed under "Later" for a dedicated content module.
