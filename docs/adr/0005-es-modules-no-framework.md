# D5 Native ES modules, no framework, no jQuery

- Status: Accepted
- Date: 2026-10-14
- Jira: JOOWT-40 (P7.1), JOOWT-4 (C1)

## Context
Smallest JavaScript and best INP.

## Decision
Frontend JS is native ES modules built with esbuild and registered with `wp_register_script_module`; editor-only code uses `@wordpress/scripts`.

## Alternatives considered
- WordPress Interactivity API: heavier runtime; may be revisited with measurements.
- React/Vue islands: too heavy.

## Consequences
- Hand-written custom elements and small modules.
- Progressive enhancement is a rule.
