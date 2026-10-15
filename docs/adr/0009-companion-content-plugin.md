# D9 Companion plugin for content model

- Status: Accepted
- Date: 2026-10-14
- Jira: JOOWT-40 (P7.1), JOOWT-4 (C1)

## Context
Data (companies, term/user meta, featured flags) must survive theme changes.

## Decision
Plugin `jooservices-content` owns the CPT and meta; core reads through `ContentProviderInterface` and degrades gracefully without it.

## Alternatives considered
- CPT in the theme: data is hidden on theme switch.

## Consequences
- One more plugin to install; documented in the deployment runbook.
