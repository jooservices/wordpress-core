# D10 Test pyramid

- Status: Accepted
- Date: 2026-10-14
- Jira: JOOWT-40 (P7.1), JOOWT-4 (C1)

## Context
Fast feedback plus confidence on real WordPress.

## Decision
PHPUnit 9.6 unit (Brain Monkey), wp-phpunit integration in Docker, Vitest, Playwright + axe, Lighthouse CI. PHPUnit 9.6 because the WordPress test suite does not support PHPUnit 10+.

## Alternatives considered
- PHPUnit 10: incompatible with wp-phpunit (verified).

## Consequences
- Integration tests need the Docker stack.
