# JOOS-120: Resume readme and changelog

## Changes

- Documented resume features, admin tools, import/export, shortcode, block, REST endpoints and private fields in the plugin readme.
- Added matching feature bullets to the plugin 0.1.0 changelog and release changelog without changing version numbers.

## Checks

- `node tools/release-check.mjs --theme jooservices` — passed; versions and changelog mirrors validated.
- Prettier on changed Markdown — skipped because `node_modules` does not exist.
