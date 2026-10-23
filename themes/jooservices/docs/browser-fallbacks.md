# Cross-engine fallbacks (WebKit and Firefox)

JOOWP-204 audit of the engine-sensitive features of the jooservices theme. Every row names its fallback and the test
that exercises it. The tests are in `themes/jooservices/tests/e2e/cross-engine.spec.ts` (Playwright, run in the
`webkit-*` and `firefox-*` projects), `packages/theme-core/resources/js/code-copy.test.js` and
`packages/theme-core/resources/blocks/search-trigger/view.test.js` (Vitest), and
`themes/jooservices/tests/cross-engine.test.mjs` (static guards, run by `make test-unit`).

| Feature | Engine behaviour | Fallback | Test |
| --- | --- | --- | --- |
| `content-visibility: auto` (`.joo-cv`, with `contain-intrinsic-size: auto`) | Safari 18+ and Firefox 125+ skip off-screen rendering. Older engines ignore the property. | Sections render normally; nothing depends on skipping. The reserved size keeps scroll height stable where it is supported. | `content-visibility sections keep a reserved size…` (e2e) |
| `color-mix()` | Safari 16.2+, Firefox 113+. | The theme's minimum targets (Safari 16.4, Firefox ESR) support it, so no fallback is shipped. | `color-mix and container queries resolve…` (e2e) |
| Container queries (`container: shell / inline-size`, `@container shell`) | Safari 16+, Firefox 110+. | Same as above: inside the minimum targets. | same test |
| `<dialog>` search overlay | `showModal()` everywhere in scope. | If `showModal` is missing, `<joo-search>` does not intercept the link, so it navigates to the search page. | `the search overlay is a native dialog…` (e2e), `view.test.js` |
| Scroll-snap slider | `scroll-snap-type: x mandatory` on a flex track; Firefox and WebKit snap on `scrollTo`. | Without snap the track is still a scrollable row and the prev/next/dot controls scroll by one slide. | `the slider track scroll-snaps…` (e2e) |
| `100dvh` / `dvh` (terminal sheet `min(62dvh, 480px)`) | Safari 15.4+, Firefox 101+; follows the URL bar. | Resolves to the visible viewport; never taller than `window.innerHeight`. | `…viewport units follow the visible viewport` (e2e) |
| `position: sticky` in a flex row (`.joo-term`) | Needs `align-self: flex-start` in WebKit and Firefox or the item stretches and cannot stick. | The dock sets `align-self: flex-start`. | `the sticky terminal dock sticks…` (e2e) |
| `loading="lazy"` | Thresholds and decode timing differ between engines. | The first slider image is eager; lazy images carry width and height so layout does not shift. | `below-the-fold images are lazy…` (e2e) |
| Clipboard API (code copy) | Safari and Firefox reject `navigator.clipboard.writeText` outside a user gesture or without permission; Firefox before 63 has no `execCommand` copy outside a gesture. | `code-copy.js` falls back to a hidden `<textarea>` and `document.execCommand('copy')`. If that also fails the button reads "Press Ctrl+C" and the status says "Copy failed". | `falls back to execCommand…` and `tells the visitor to copy by hand…` (e2e), `code-copy.test.js` |
| `:has()` | Safari 15.4+, Firefox 121+ (Firefox ESR 115 lacks it). | Both uses (`.joo-card:not(:has(.joo-card__thumb))`, `.joo-header:has(.wp-block-site-logo img)`) sit inside `@supports selector(:has(*))`. Without it a thumbless card keeps its thumbnail column and the text logo stays next to the logo image; content and function are unchanged. | `the :has() rules are guarded…` (e2e), `cross-engine.test.mjs` |
| Popover API | Not used. | Overlays use `<dialog>`. | `the theme does not use the popover API` (e2e), `cross-engine.test.mjs` |
| Speculation rules | Chromium only; WebKit and Firefox ignore `<script type="speculationrules">`. | The script is inert data in other engines and the site works without prefetching. | `speculation rules do not raise console errors…` (e2e) |

## Running

```sh
E2E_ENGINES=webkit,firefox make test-e2e THEME=jooservices \
  ARGS="tests/e2e/specs/shared/smoke.spec.ts themes/jooservices/tests/e2e/responsive.spec.ts themes/jooservices/tests/e2e/cross-engine.spec.ts --project=webkit-phone --project=webkit-tablet --project=webkit --project=firefox-phone --project=firefox-tablet --project=firefox"
```

`<engine>-phone` is 390×844 with touch and a 2× pixel ratio (mobile emulation on WebKit; Firefox has no `isMobile`
option, so it gets the viewport, touch and pixel ratio only), `<engine>-tablet` is 820×1180 and `<engine>` is 1440×900.
The manual `cross-engine` job in `.github/workflows/nightly.yml` installs the browsers (`playwright install --with-deps`)
and runs the same subset per engine.

## Bugs and deferrals

Failures found by these runs are recorded as Jira Bugs, fixed and rerun. A bug that stays open needs the owner's
approval to be deferred and is listed in the evidence record. See
`artifacts/evidence/JOOWP-204/results.md`.
