# JOOS-119 — Unit tests for the company editor repeater script

## Changes
- `plugins/jooservices-resume/assets/company-editor.test.js` (Vitest, jsdom): adding a row (names reindexed, focus on the first field, live message), removing (focus to the neighbour, message), moving up and down (order and names follow the screen, focus stays on the button), no move beyond the ends, and the unsaved-changes `beforeunload` warning.
- `vitest.config.js`: `plugins/**/assets/**/*.test.js` is now part of the test include list.

## Verification
| Check | Result |
|---|---|
| `vitest run plugins/jooservices-resume/assets/company-editor.test.js` | 5 passed |
| `make lint` | pass |

## Notes
- First draft by opencode (it stopped when Docker was busy); finished and fixed by Claude: a wrong `returnValue` assertion (a plain `Event` reports `false` after `preventDefault`) and an `innerHTML` assignment the repo lint forbids (the DOM is now built with `DOMParser`). No change to the script was needed.
