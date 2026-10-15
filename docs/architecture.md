# Architecture

Layering, dependency rule, API, manifest, boot sequence and error model are specified in [`spec/core-spec-v0.1.md`](spec/core-spec-v0.1.md). Decisions: [`adr/`](adr/).

## Module catalogue
| Module | Responsibility | Depends on |
|---|---|---|
| assets | asset registry, hashed builds, critical CSS, JS runtime config | - |
| admin | settings page, editor panels, WP-CLI, Site Health | assets |
| perf | images, fonts, resource hints, bloat removal, fragment cache | assets |
| lazy | lazy policy, image/iframe, fragments, sections, comments | assets, perf |
| seo | head tags, canonical/robots, Open Graph, JSON-LD, sitemaps, HTTP semantics | compat, i18n |
| compat | plugin detection and adapters | - |
| content | provider interface, REST `joo/v1` | - |
| blocks | block registrar, patterns, dynamic blocks and library | assets, content |
| i18n | text domains, language service, built-in language tagging | compat |
| terminal | virtual file system, shell engine, dock | content, i18n |

## Extension points
Filters/actions `joo/{module}/{event}` (see `reference/hooks.md`), theme template parts `parts/joo/<block>.php`, container service replacement via `joo/core/services`, terminal commands via `joo/terminal/commands`, manifest `x-*` keys.
