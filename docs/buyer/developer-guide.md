# Developer guide

## Stable extension surface

Only symbols tagged `@api` in `packages/theme-core/src` are stable. Public hook names follow `joo/<module>/<event>`. The generated [hooks reference](../reference/hooks.md) lists hook names; read the corresponding source and docblock for the callback signature and accepted values. Deprecations are kept for at least one minor release. See the [API policy](../guides/api-policy.md).

## REST routes and blocks

The generated [surface reference](../reference/surface.md) lists REST routes in the `joo/v1` namespace, blocks, post types, and stored data. The theme's routes below `/wp-json/joo/v1/` are unauthenticated, read-only `GET` requests and return published public content. The controller source is the contract for validation and response fields; see [content routes](../../packages/theme-core/src/Content/Rest/ContentRoutes.php), [fragment route](../../packages/theme-core/src/Lazy/Rest/FragmentController.php), and [terminal file-system route](../../packages/theme-core/src/Terminal/Rest/FsRoute.php).

| Path | Query parameters | Response |
|---|---|---|
| `/companies` | `page` (integer, default `1`); `per_page` (integer, default `5`, range `1`–`20`); `exclude_pinned` (boolean, default `true`) | Company cards. Pagination totals are in `X-WP-Total` and `X-WP-TotalPages` headers. |
| `/categories/tree` | `hide_empty` (boolean, default `true`) | Category tree. |
| `/topics` | `limit` (integer, default from `content.topics_limit`, range `1`–`20`); `strategy` (`count` or `manual`, default from `content.topics_strategy`) | Topic list. |
| `/featured` | `limit` (integer, default `5`, range `1`–`12`) | Featured post cards. |
| `/related/{post_id}` | `post_id` (positive integer in the path); `limit` (integer, default `3`, range `1`–`8`) | Related post cards. |
| `/profile` | None | Selected public profile, or an empty array when none is configured. |
| `/fragment/{name}` | Fragment-specific validated query parameters | `{ "html": "…", "etag": "…" }`. Unknown or unavailable fragments return `404`; invalid parameters return `400`. |
| `/terminal/fs` | `lang` (optional 2–3 letter language code with optional region) | A read-only virtual tree of published site content. It does not expose server files, drafts, private posts, or password-protected posts. |

Successful `GET` responses have public cache headers for 300 seconds, a weak `ETag`, and support `If-None-Match` with `304 Not Modified`. Do not edge-cache `/wp-json/joo/v1/fragment/comments` for logged-in visitors. Keep routes read-only and never expose private data; add an explicit permission callback before introducing any protected or writable route.

## Theme templates and child theme

Templates and parts are under `themes/jooservices/templates` and `themes/jooservices/parts`. A WordPress child theme can override a template by using the same relative path. Minimal `style.css` header:

```css
/*
Theme Name: JOOservices Child
Template: jooservices
Version: 1.0.0
*/
```

Place the child theme in `wp-content/themes/jooservices-child/`. Copy only the template or part you need, such as `templates/single.html`; keep core files and the parent theme unchanged. Verify overrides after every parent update.

## Settings schema

`packages/theme-core/schema/settings.schema.json` defines supported settings. The generated [settings reference](../reference/settings.md) lists keys, types, defaults, groups, and descriptions. The resolution order is the `joo/settings/value` filter, saved admin value, theme manifest, then schema default.

## Terminal commands

The terminal is read-only. The `joo/terminal/commands` PHP filter adds same-origin JavaScript module URLs to the public runtime configuration. Each module must default-export an array of command objects; a command has a lowercase `name` and a `run` function, and may also provide `usage`, `summary`, `completion`, and `readonly` metadata. The browser ignores modules from another origin and skips a failed import.

1. Put an ES module in your child theme, for example `assets/terminal/commands.js`.
2. Register its same-origin URL from the child theme's PHP:

   ```php
   add_filter(
       'joo/terminal/commands',
       static function ( array $urls ): array {
           $urls[] = get_stylesheet_directory_uri() . '/assets/terminal/commands.js';
           return $urls;
       }
   );
   ```

3. Export commands from that module and follow the existing command examples in `packages/theme-core/resources/js/terminal/commands/` for the `run({ shell, args, result })` contract.

Keep commands read-only. Do not execute operating-system commands, make network requests, or expose WordPress files. The command object and shell helpers are implementation details, not a stable `@api`; re-check them after theme-core updates and run terminal integration tests before release.

## Coding standards and checks

Follow the repository's [coding standards](../guides/coding-standards.md). Before opening a change, run `make lint`, `make analyse`, and the relevant unit or WordPress integration tests. Keep core changes generic; theme-specific layout belongs in the theme. See [architecture](../architecture.md) and the [testing guide](../guides/testing.md).

## Generated API documentation

Keep prose in `docs/buyer/`. Regenerate the reference pages from the naming registry and settings schema with `node tools/docs-reference.mjs`, then check freshness with `node tools/docs-reference.mjs --check`. CI checks local buyer-guide links and generated reference freshness.
