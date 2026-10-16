# JOOS-90 — Theme variations and buyer options

## Changes

- Added Ocean, Forest, and Paper style variations alongside Dark and Light, with runtime switching and contrast coverage.
- Added buyer settings for the title treatment, owner profile links, footer content, rail and slider behavior, card density, terminal, back-to-top, and dates.
- Added six editor patterns, child-theme customization guidance, and documented the appropriate edit location for each option.
- Updated Vietnamese theme catalogs for the revised scheme control, palette names, and patterns.

## Verification

| Check | Result |
|---|---|
| `make lint` | Pass: headers, PHPCS (286 files), ESLint, Stylelint, and Prettier. |
| `make analyse` | Pass: the full run completed before the final integration-fixture-only edit. A repeat reached PHPStan core `[OK]`, then was stopped while theme PHPStan was still running; no full repeat result. |
| `make test-unit` | PHPUnit passed: 272 tests / 1,424 assertions. Vitest’s changed design (16) and theme-toggle (5) tests passed; the full run exited 2 after 30 files / 165 tests passed and 10 worker startup timeouts. |
| WordPress 7.1.3 integration | Pass: test kit 8 / 26, core 322 / 1,625, plugin 34 / 297, theme 21 / 258 (7 skipped). The theme check switches to JOOServices while registering manifest patterns, matching the runtime path. |
| WordPress 6.6.2 integration | Pass: test kit 8 / 26, core 322 / 1,625, plugin 34 / 297, theme 21 / 258 (7 skipped). |
| `make i18n THEME=jooservices` | WP-CLI regenerated catalogs, then the target exited 2 because the shared Node runtime has no `msgfmt` (`spawnSync msgfmt ENOENT`). Host catalog generation compiled the JOOServices core and theme files; its overall run also reported 137 untranslated strings in the unrelated resume plugin catalog, whose generated files were restored. |
| Schema validation | Pass: settings descriptor (68 keys), schemas, and blocks. |
| Documentation references | Pass: `tools/docs-reference.mjs --check`. |
| Theme registry | Pass: 144 names, 68 settings keys, 307 source files scanned. |
| `git diff --check` | Pass. |

## Browser checks

- The earlier focused buyer-settings run reached its assertions in tablet and desktop, then exposed duplicate final cleanup resets; the phone run failed during login navigation with `ERR_ABORTED`.
- Added guarded cleanup so successful runs do not repeat resets and a crashed page does not mask the original failure. The latest focused rerun still failed on the phone project: Playwright reported a 45-second timeout because the browser target crashed while waiting for `#wpadminbar`. The runner then produced no progress for over seven minutes. I interrupted it at 14 minutes to stay within the shared-lock limit (exit 130) and verified the lock was released. The cleanup change is therefore not fully verified in-browser.
- The focused scheme-cycle E2E was not run after the browser runner stalled. The compatibility matrix, full E2E suite, and performance checks were not needed for this feature slice.
- Prettier passed on the edited E2E file; the full lint gate passed before that small cleanup edit.

## QA addendum (Claude)

- Merged `develop` (JOOS-89, 99, 102), regenerated the API reference, and re-ran: `make lint`, `make analyse`, PHPUnit (280 tests), Vitest (a single worker-start failure under load passed alone), `node tools/i18n.mjs --check`, integration on WordPress 7.1.3 (kit 8, core 322, plugin 43, theme 21 with 7 starter-only skips).
- E2E defects found and fixed in the specs (not in the theme): `customization.spec.ts` reset the whole content tab at the start, which wiped the seeded owner profile and broke `home.spec.ts` for every later spec (it showed `admin`); it now restores the profile and cleans up on a fresh page. The reset click is followed by a form reload, and the next `goto` raced it (`ERR_ABORTED`); it now waits for the load. `home.spec.ts` scoped the profile name to the rail because the footer now also shows a profile card (JOOS-90 replaced the footer Connect links with `joo/profile-card`).
- With those fixes `home.spec.ts` passes (9 of 9). The long `customization.spec.ts` journey could not be confirmed end to end: Chromium crashed during it on every attempt while the Docker VM was out of memory (`Target crashed` even on the login form); settings pages themselves load fine (`settings.spec.ts` 5 of 6, the sixth a random crash). Re-run it once the Docker memory is raised.
- Note for the owner: the footer Connect column no longer lists RSS, GitHub and LinkedIn as fixed links; it shows the owner's profile card with their saved social links.
