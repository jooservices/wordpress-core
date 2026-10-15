# D6 CSS from design tokens, modern CSS, dark/light via data attribute

- Status: Accepted
- Date: 2026-10-14
- Jira: JOOWT-40 (P7.1), JOOWT-4 (C1)

## Context
Themes need consistent, themeable, small CSS.

## Decision
Tokens in `theme.json` become CSS custom properties; component CSS uses nesting and container queries (Lightning CSS); dark/light via `prefers-color-scheme` plus `data-theme`.

## Alternatives considered
- Sass/Tailwind: extra toolchain and output size.

## Consequences
- Modern browser baseline (container queries, `color-mix`).
