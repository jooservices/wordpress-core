# D3 Core is bundled into each theme zip with no runtime Composer dependencies

- Status: Accepted
- Date: 2026-10-14
- Jira: JOOWT-40 (P7.1), JOOWT-4 (C1)

## Context
Distribution must be simple and conflict-free on shared hosting.

## Decision
Core is copied into `theme/core/` at build time and loaded by its own PSR-4 autoloader; `JOO_CORE_VERSION` guards against double loading.

## Alternatives considered
- Companion plugin carrying core: another thing to install and version-skew risk.
- Composer vendor with scoping: complexity.
- Git submodule: awkward.

## Consequences
- Zero third-party runtime code in core.
- SEO is lost when switching theme; may be extracted to a plugin later (see Later list).
