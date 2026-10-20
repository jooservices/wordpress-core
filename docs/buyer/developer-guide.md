# Developer guide

## Stable extension surface

Only symbols tagged `@api` in `packages/theme-core/src` are stable. Public hook names follow `joo/<module>/<event>`. The generated [hooks reference](../reference/hooks.md) lists hook names; read the corresponding source and docblock for the callback signature and accepted values. Deprecations are kept for at least one minor release. See the [API policy](../guides/api-policy.md).

## REST routes and blocks

The generated [surface reference](../reference/surface.md) lists REST routes in the `joo/v1` namespace, blocks, post types, and stored data. Every route below is read-only, accepts `GET`, and uses `__return_true` as its permission callback. Responses contain published public content only. The controller source is the validation and response contract: [content routes](../../packages/theme-core/src/Content/Rest/ContentRoutes.php), [fragment route](../../packages/theme-core/src/Lazy/Rest/FragmentController.php), and [terminal file-system route](../../packages/theme-core/src/Terminal/Rest/FsRoute.php).

### Route catalog

| Path | Method | Permission callback | Parameters and validation |
|---|---|---|---|
| `/companies` | `GET` | `__return_true` | `page`: integer, default `1`, range `1`–`1000`; `per_page`: integer, default `5`, range `1`–`20`; `exclude_pinned`: boolean, default `true`, sanitized to a boolean. |
| `/categories/tree` | `GET` | `__return_true` | `hide_empty`: boolean, default `true`, sanitized to a boolean. |
| `/topics` | `GET` | `__return_true` | `limit`: integer, default from `content.topics_limit`, range `1`–`20`; `strategy`: `count` or `manual`, default from `content.topics_strategy`. |
| `/featured` | `GET` | `__return_true` | `limit`: integer, default `5`, range `1`–`12`. |
| `/related/{post_id}` | `GET` | `__return_true` | `post_id`: positive integer in the path; `limit`: integer, default `3`, range `1`–`8`. |
| `/profile` | `GET` | `__return_true` | No parameters. Returns the selected public profile, or an empty array when none is configured. |
| `/fragment/{name}` | `GET` | `__return_true` | `name`: registered lowercase letters, digits or hyphens, 1–64 characters. Query parameters are validated against the selected fragment's schema below. Unknown fragments return `404`; invalid values return `400`. |
| `/terminal/fs` | `GET` | `__return_true` | `lang`: optional 2–3 ASCII letters, optionally followed by `-` or `_` and a 2-letter region. Invalid values return `400`. |

### Core lazy-fragment schemas

All fragment query parameters are optional unless stated otherwise. Integers must be whole numbers and obey the listed ranges; booleans are normalized; strings are sanitized and capped at the listed length.

| Fragment name | Query parameters and validation |
|---|---|
| `companies` | `page` integer ≥1 (default `1`); `per` integer 1–10 (default `2`); `desc` boolean (default `true`); `pinned` string ≤60 characters; `list` string ≤80; `title` string ≤60. |
| `category-tree` | `counts`, `desc`, `empty` booleans (default `true`); `open` integer 0–3 (default `0`); `depth` integer 1–4 (default `3`); `current` integer ≥0 (default `0`); `title` string ≤60. |
| `top-topics` | `limit` integer 1–12 (default `4`); `strategy` `count` or `manual` (default `count`); `image` and `intro` booleans (default `true`); `title` string ≤60. |
| `tag-list` | `limit` integer 5–60 (default `24`); `orderby` `count` or `name` (default `count`); `counts` boolean (default `true`); `style` `chips` or `cloud` (default `chips`); `title` string ≤60. |
| `related` | `post` positive integer (required; missing returns `400`); `limit` integer 1–8 (default `3`); `image` boolean (default `true`); `excerpt` boolean (default `false`). |
| `comments` | `post` positive integer (required; missing returns `400`); `cpage` integer ≥1 (default `1`). A snapshot is returned only for a published, unprotected post. Personalized renders are not stored. Successful responses use `private, no-store`. |

Successful responses from content, terminal, and renderer-backed fragment routes have `Cache-Control: public, max-age=300, stale-while-revalidate=600`, a weak `ETag`, and support `If-None-Match` with `304 Not Modified`. Draft, private, and password-protected posts are excluded from content and terminal results and cannot be used to read comment snapshots. Published content record IDs in `Company.id`, `PostCard.id`, and terminal post nodes are intentional public identifiers; account IDs, `user_login`, and `user_email` are not part of these response schemas. Invalid requests and missing routes are not cached.

## Theme templates and child theme

Templates and parts are under `themes/jooservices/templates` and `themes/jooservices/parts`. A WordPress child theme can override a template by using the same relative path. Minimal `style.css` header:

```css
/*
Theme Name: JOOservices Child
Template: jooservices
Version: 1.0.0
*/
```

Place the child theme in `wp-content/themes/jooservices-child/`. Copy only the template or part you need, such as `templates/single.html` or `parts/footer.html`; keep core files and the parent theme unchanged. Verify overrides after every parent update.

The theme-core dynamic block renderer also supports PHP templates at `parts/joo/{slug}.php`. The child theme is checked first, then the parent theme, then the block's bundled `render.php`. For example, `parts/joo/profile-card.php` replaces only the profile-card markup while keeping the block's data provider and editor attributes. A sample child theme under `themes/jooservices/tests/fixtures/sample-child` demonstrates both a footer template-part override and a profile-card block-template override; the theme integration suite exercises both lookup paths.

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
