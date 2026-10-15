# Showing real site content in the local demo

Reads the PUBLIC REST API of a live WordPress site (GET only) and replaces the generated demo with it. Nothing is written to the live site.

1. Fetch: the script `tools/live-import.mjs` needs `artifacts/live/<site>.json` with `categories`, `tags`, `pages`, `posts` and `media` (each the paginated result of `/wp-json/wp/v2/<type>?per_page=100`). Drafts and private posts need an authenticated request (an application password); without it only published content is imported.
2. Import: `make seed-live THEME=jooservices [SOURCE=artifacts/live/jooservices.com.json]`. Images used by posts are downloaded once into `artifacts/live/media` and uploaded to the local Media Library; post HTML is kept as it is rendered on the site, with image URLs rewritten.
3. Local additions: the five newest posts with an image (plus any in a `featured` category) become the featured slider, posts with Vietnamese text are tagged `vi`, an About page is created when the site has none, and the companies come from the demo data.
4. Go back to the generated demo with `make seed-reset THEME=jooservices` (`make health` and the theme e2e specs assume that dataset).
