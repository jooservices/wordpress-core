# D2 Block themes only with server-rendered dynamic blocks

- Status: Accepted
- Date: 2026-10-14
- Jira: JOOWT-40 (P7.1), JOOWT-4 (C1)

## Context
Owners edit layout in the Site Editor; SEO needs server HTML.

## Decision
Themes are block themes (templates, parts, patterns, theme.json). Logic lives in dynamic blocks rendered on the server.

## Alternatives considered
- Classic PHP templates: less flexible and no Site Editor.
- Client-rendered blocks: poor SEO.

## Consequences
- Requires WordPress 6.6+.
- No support for classic themes.
