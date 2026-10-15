# D11 Security baseline

- Status: Accepted
- Date: 2026-10-14
- Jira: JOOWT-40 (P7.1), JOOWT-4 (C1)

## Context
Safe by default.

## Decision
Escape late, sanitise early, capability and nonce checks, public REST read-only and published-only, no eval, CSP-friendly (no inline scripts except JSON data and one nonce-capable bootstrap).

## Alternatives considered
- Per-feature ad-hoc rules: inconsistent.

## Consequences
- ESLint and PHPCS rules enforce parts of it.
