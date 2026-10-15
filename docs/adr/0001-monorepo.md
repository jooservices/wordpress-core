# D1 Monorepo with Composer path repositories and npm workspaces

- Status: Accepted
- Date: 2026-10-14
- Jira: JOOWT-40 (P7.1), JOOWT-4 (C1)

## Context
Several themes, one core and one plugin must evolve together and share tooling.

## Decision
One repository `jooservices-wp-themes`; Composer path repositories for `packages/*`; npm workspaces for JS.

## Alternatives considered
- One repository per theme and per package: independent releases but cross-repo changes and duplicated tooling.
- Git submodules: awkward workflow.

## Consequences
- Atomic changes across core and themes.
- A single CI definition.
- Releases are tagged per package (`core@x.y.z`, `theme-<slug>@x.y.z`).
