# Release policy

## Versions and compatibility

The WordPress theme version in `themes/jooservices/style.css` and the plugin version in `plugins/jooservices-resume/jooservices-resume.php` are the canonical SemVer values for their products. The theme changelog and plugin bootstrap constant, readme stable tag, and changelog mirror those values. `node tools/release-check.mjs` blocks a release when a mirror differs.

Both products require WordPress 6.6 and PHP 8.1. `Tested up to` is the newest WordPress minor for which the required release gates have run. Update the theme header and plugin readme together after those gates pass. The theme core compatibility range comes from `themes/jooservices/joo-theme.json`.

Use SemVer for each product independently. Fixes increment PATCH, backward-compatible features increment MINOR, and breaking public changes increment MAJOR. Add a newest-first changelog entry in the theme `CHANGELOG.md` and plugin `readme.txt` for every release. Keep the theme README linked to its changelog; the combined release changelog is published in `docs/releases/CHANGELOG.md`.

## Distribution and updates

ThemeForest is the primary sales channel. `make release` produces an installable theme zip, a separate JOOservices Resume plugin zip, the ThemeForest main download, a preview-image zip, a demo-content zip, SHA-256 checksums, a dependency list, and an evidence report. The theme does not contact a JOOservices licence server. Envato purchase codes and ThemeForest downloads provide marketplace purchase and update delivery. If a buyer's marketplace entitlement expires, the installed theme and plugin continue working; marketplace updates may no longer be available. No feature is disabled and no licence check is sent from a buyer's site.

The product zip includes the GPL license and notices for the self-hosted IBM Plex Sans and JetBrains Mono fonts. Demo posts and gradient images are generated locally from the deterministic fixture set. Do not package live-site exports, third-party articles, or assets without redistribution rights.

## Release and recovery

Run the required CI gates before `make release`. The release command validates metadata, builds every deliverable twice from the same committed checkout, compares SHA-256 hashes, and writes `EVIDENCE.md`. `make release TAG=1` may create local annotated `theme-x.y.z` and `plugin-x.y.z` tags after all checks succeed. The tool never pushes.

For a normal update, back up the database and uploads, install the new theme zip, and upload/activate the Resume plugin zip if it is new or changed. The theme settings are stored in the `joo_jooservices_theme_settings` option. Company records and their `_joo_company_*` metadata are owned by the plugin and are not deleted during updates or uninstall by default.

To roll back, reinstall the previous theme and plugin zips and clear page caches. The current release has no destructive schema migration; do not restore an older database solely to roll back code. For a hotfix, branch from the last accepted release, increment the affected product's PATCH version, add a changelog entry, rerun the full gates and upgrade test, run `make release`, then publish the new artifacts through ThemeForest. Keep the previous downloads available until the marketplace update is accepted.

## ThemeForest submission assets

The primary additional preview is `themes/jooservices/release-assets/preview/01_jooservices.png` (590×300). The package also contains the 1200×900 WordPress theme screenshot. Recheck the marketplace's current preview formatting, thumbnail rules, title, and image sizes immediately before submission; Envato may change upload requirements.

Current references checked 2026-10-15:

- [Themes Item Preparation & Technical Requirements](https://help.author.envato.com/hc/en-us/articles/360000470826-Themes-Item-Preparation-Technical-Requirements)
- [WordPress Theme Requirements Part 1 - General](https://help.author.envato.com/hc/en-us/articles/360000481263-WordPress-Theme-Requirements-Part-1-General)
- [Adding Key Features and Additional Images](https://help.author.envato.com/hc/en-us/articles/360000471326-Adding-Key-Features-and-Additional-images)
