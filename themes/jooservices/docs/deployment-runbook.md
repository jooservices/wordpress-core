# Deployment runbook for jooservices.com

Nothing in the repository pushes or deploys; these are the manual steps.

## Before
1. All gates green on the commit to release (see the README table) and `make package THEME=jooservices` built a zip.
2. Back up the site: database export and `wp-content/uploads` (hosting snapshot or `wp db export`).
3. Note the current theme and plugin versions: `wp theme list`, `wp plugin list`.

## Deploy
1. Put the site in maintenance mode if traffic matters (a few seconds).
2. `wp theme install jooservices-<version>.zip --force` (or Appearance, Themes, Add New, Upload).
3. If the companion plugin is new or updated: install and activate `jooservices-content`.
4. `wp theme activate jooservices`.
5. `wp rewrite flush --hard` and clear the page cache of the host or cache plugin.
6. Run the checks below.

## Check (five minutes)
- Home, a post, a category, search and a missing URL answer 200, 200, 200, 200 and 404.
- `/wp-sitemap.xml` (or the SEO plugin's sitemap) and `/robots.txt` load; robots.txt has one `Sitemap:` line.
- View source of a post: one `<title>`, one canonical, one JSON-LD `BlogPosting` (or the SEO plugin's graph, never both).
- `wp joo doctor` returns 0 or only warnings; Tools, Site Health shows no critical entry from JOOservices.
- Lighthouse on mobile: performance 90+, accessibility 95+, SEO 95+ (the repository's `make perf` uses the same budgets on a demo).
- The terminal opens and lists the posts.

## Rollback
`wp theme install previous.zip --force`, then flush rewrites and the cache. Settings live in the `joo_settings_jooservices` option and are not changed by a theme update. If the site cannot load at all, rename `wp-content/themes/jooservices` over SFTP: WordPress falls back to a default theme.

## Caching notes
Static files have content-hashed names and can be cached for a year. REST responses under `joo/v1` carry `Cache-Control` and `ETag`. Do not cache `/wp-json/joo/v1/fragment/comments` for logged-in users.
