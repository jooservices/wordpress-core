# JOOS-107 — RTL support (release 1.0)

## Decisions
- **Logical properties only** (no separate RTL stylesheet): the browsers in scope support them (Safari 16.4+). One CSS file, no size cost, no per-direction critical CSS needed.
- **Fonts:** system fallback for Arabic/Hebrew (no bundled font); Latin and Vietnamese fonts unchanged. Joined scripts get `letter-spacing: 0` through `:lang(ar|fa|he|ur)`.
- Code, `kbd` and `samp` are isolated left to right inside RTL prose (`direction: ltr; unicode-bidi: isolate`).

## Changes
- Physical `left/right` CSS (margin, padding, border, inset, text-align, radius) converted to logical equivalents in the theme, core blocks, editor and admin settings CSS (75 occurrences audited).
- Terminal dock now uses `inset-inline-end`, so it docks left in RTL; the rail follows the grid direction and sits on the right.
- Slider: scroll offset corrected for RTL (negative `scrollLeft`), ArrowLeft/ArrowRight swap meaning; breadcrumb chevron and the "Read →" arrow flip through `:dir(rtl)`; the progress bar origin flips.
- Stylelint now rejects physical direction properties and `text-align`/`float`/`clear` left/right; unit test `tools/stylelint-rtl.test.mjs` covers 14 cases.
- New reusable e2e step `useRtl(page)` in `packages/test-kit/e2e/helpers.ts` and spec `themes/jooservices/tests/e2e/rtl.spec.ts` (no horizontal scroll 320–2560 px on five views, rail right / dock left, direction, code stays LTR, axe in both schemes).

## Verification
| Check | Result |
| --- | --- |
| `make lint`, `make analyse` | pass |
| `make test-unit` | PHPUnit 256, Vitest 214 pass (slider RTL keys, stylelint guard) |
| Playwright `rtl.spec.ts` (phone, tablet, desktop) | 18 passed |
| Full theme e2e (regression, LTR) | 317 passed, 3 skipped; 4 tests timed out or crashed only while the machine ran at load 16–21 (`shell` no-horizontal-scroll, admin settings axe, shared views home/notFound); `shell` passes alone in 10.6 s on phone, so `test.slow()` was added to it |
| `make critical` | critical CSS regenerated from the logical stylesheet (new target `make critical THEME=...`) |

## Risks and not done
- Not covered by this ticket: the Resume plugin admin/timeline/print mirroring and an Arabic demo translation with a locale switch (needs the showcase site, JOOS-92/93); visual baselines; WebKit and Firefox runs of the RTL spec; owner sign-off.
- Proposed backlog: RTL for the Resume plugin front end (with JOOS-89), Arabic sample catalog, `<bdi>` for mixed-script titles, RTL visual baselines.
