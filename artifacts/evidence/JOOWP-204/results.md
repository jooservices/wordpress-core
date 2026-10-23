# JOOWP-204 Cross-engine runs (WebKit and Firefox)

## Prior evidence

JOOWT-246 (`artifacts/evidence/JOOWT-246/results.md`, 2026-10-21): the shared suite passed on WebKit 38/38 and Firefox 38/38
on the seeded `jooservices` site, with no failures or engine limits.

## Changes in this ticket

- `tests/e2e/playwright.config.ts`: `E2E_ENGINES` now creates `<engine>-phone` (390×844, touch, 2× ratio; mobile emulation
  on WebKit), `<engine>-tablet` (820×1180) and `<engine>` (1440×900) for WebKit and Firefox. Firefox has no `isMobile`.
- `themes/jooservices/tests/e2e/cross-engine.spec.ts`: dialog, popover absence, scroll-snap, `color-mix`, container queries,
  `:has()` support, sticky dock, lazy images, `content-visibility`, `dvh`, speculation rules and the clipboard fallback paths.
- Fix found by the audit: the two `:has()` rules (card without thumbnail, header logo) and the editor copy were unguarded.
  They now sit in `@supports selector(:has(*))` in the source and critical CSS.
- `.github/workflows/nightly.yml`: manual `cross-engine` job that installs WebKit and Firefox and runs the subset.
- `themes/jooservices/docs/browser-fallbacks.md`: every fallback and the test that covers it.

## Not produced on this machine

This run had Chromium only (Playwright 1.64.1); WebKit and Firefox binaries and the shared WordPress site were not
available, so the new e2e specs and the engine subsets were **not executed** here. Run the command in
`themes/jooservices/docs/browser-fallbacks.md` on the Dev VM or the `cross-engine` workflow and record the result
below. No Jira Bugs were raised because no engine run happened; any failure found in that run becomes a Bug, is fixed and
rerun, or is deferred with the owner's approval.

| Engine | Viewport project | Result |
| --- | --- | --- |
| WebKit | phone / tablet / desktop | not run |
| Firefox | phone / tablet / desktop | not run |
