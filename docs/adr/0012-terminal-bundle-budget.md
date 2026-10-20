# ADR 0012: Set a measured budget for the complete terminal bundle

- Status: Accepted
- Date: 2026-10-20
- Jira: JOOWT-249

## Context

The core spec limits the pure terminal engine to 5 KiB gzip, while the terminal module documentation described the complete on-demand terminal as 8 KB gzip. These refer to different scopes: the engine contains the filesystem and shell core; the complete bundle also contains built-in command handlers and the browser UI. The production build measured the complete bundle at 8,421 bytes gzip, which exceeds an 8 KiB (8,192 byte) cap. That is the measurement that motivated this decision; the current build measurement is recorded in `docs/reference/budgets.md`.

## Decision

Keep the pure engine budget at 5 KiB (5,120 bytes) gzip and set the complete on-demand terminal bundle budget to 9 KiB (9,216 bytes) gzip. Measure both as standalone minified esbuild outputs using the same Node `gzipSync()` byte-count method as the asset manifest.

## Alternatives considered

- Remove or rewrite terminal commands and UI to force the current complete bundle below 8 KiB; this would trade user-facing behavior for a rounded and unverified limit.
- Keep the 8 KB statement without a byte-level cap; this would leave the spec and docs ambiguous and would not give the budget check a precise threshold.

## Consequences

- At the original measured revision the complete bundle had 795 bytes of headroom. The 2026-10-21 build measures 8,536 B gzip, leaving 680 bytes under the same 9 KiB limit.
- `docs/reference/budgets.md`, spec section 9, and the terminal module documentation use the same scopes and limits.
- Reconsider the limit if the complete bundle exceeds 9 KiB or the terminal's responsibilities change.
