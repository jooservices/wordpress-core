# D7 Manifest, settings and filter precedence

- Status: Accepted
- Date: 2026-10-14
- Jira: JOOWT-40 (P7.1), JOOWT-4 (C1)

## Context
Predictable overrides across theme, owner and code.

## Decision
`joo-theme.json` < option `joo_{slug}_settings` < PHP filters.

## Alternatives considered
- Customizer-only config: not available for block themes.

## Consequences
- Settings page and docs are generated from a single descriptor.
