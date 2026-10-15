# D8 Naming conventions

- Status: Accepted
- Date: 2026-10-14
- Jira: JOOWT-40 (P7.1), JOOWT-4 (C1)

## Context
Avoid collisions and keep names greppable.

## Decision
PHP namespace `JOOservices\ThemeCore\*`; hooks `joo/{module}/{event}`; options/meta `joo_`/`_joo_`; REST `joo/v1`; blocks `joo/`; elements `joo-`; CSS `joo-`.

## Alternatives considered
- Per-theme prefixes: breaks reuse.

## Consequences
- `docs/spec/naming-registry.json` is authoritative and checked in CI.
