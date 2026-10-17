# JOOS-122: rail card and About timeline read the new company fields

## Decision
- **About timeline: the plugin's `joo/resume` block.** It already renders roles, achievements, technologies and the public custom fields and applies the privacy rules in one place (`CompanyRecord::toPublic`). Re-implementing it in the theme through `ContentProviderInterface` would duplicate that logic and risk leaking private fields.
- **Rail card (`joo/companies`): the theme provider reads two public values of the plugin data** and the plugin stays independent of theme-core: the newest role title (`_joo_company_roles`, versioned envelope `{"v":1,"items":[...]}` or a plain list, newest first) replaces the single role field, and `_joo_company_summary` replaces the excerpt. Both fall back to the old fields when missing or invalid. Nothing else is read, so private fields (compensation, equity) are never touched.

## Changes
- `packages/theme-core/src/Content/WpContentProvider.php`: `company()` uses the summary and `currentRole()`.
- `packages/theme-core/tests/Integration/ContentTest.php`: summary and newest role (envelope and plain list), fallbacks (invalid JSON, empty list, empty envelope), no private value in the model.
- Demo data: `tools/wxr-generator/data/topics.json` (roles, achievements, technologies, summary, employment, location, two public custom fields and one private), `generate.mjs` (writes the plugin meta as JSON strings, the custom field definitions option, and the About page with `<!-- wp:joo/resume /-->`), `generate.test.mjs`.
- `docker/scripts/seed.php`: the custom field definitions are applied before the companies are imported (the plugin drops custom values that have no definition) and the JSON meta is slashed (`update_post_meta()` unslashes).
- E2E: `themes/jooservices/tests/e2e/about-resume.spec.ts` (new: timeline content, private fields absent, axe, no horizontal scroll, rail card) and the About selector of `archives.spec.ts`.
- `docs/modules/blocks.md`.

## Defects found while testing (all fixed here)
- **Real product defect (theme-core cache).** The cached owner profile ignored changes of the setting `content.profile_user_id`: the entry was not tagged `settings`, and the fragment cache listened only to `updated_option`, while `SettingsRepository::write()` deletes the option row when every setting is back to its default and the next save is an `add_option()`. A change of the profile user showed the old owner for up to five minutes. Fix: the profile entry carries the `settings` tag and `FragmentCache` also hooks `added_option` and `deleted_option`; `ContentTest` covers change, reset and first save after a reset (the test fails without the fix).
- The plugin stores the roles as a versioned envelope `{"v":1,"items":[...]}`, not a plain list: the first draft of the provider read it wrongly; found by inspecting the real stored value, now covered for both shapes.
- Custom values were silently dropped because the definitions did not exist yet when the demo companies were imported (seed order); fixed in `seed.php`.
- Spec `customization.spec.ts` (JOOS-90, never confirmed before) passed a RegExp to `selectOption({ label })`, which takes a string; it failed halfway and left the profile reset, which broke `home`, `single` and `privacy` (the default avatar calls Gravatar) after it. Fixed (the option is resolved by its text first).
- Spec `resume-frontend.spec.ts` assumed the demo companies have no roles, achievements or technologies; its rich-company assertions are now scoped to the companies of the test.
- Specs that are only slow through the Dev VM tunnel: `rtl.spec.ts` (320 to 2560 px sweep) and `settings.spec.ts` (axe per tab) use `test.slow()`. `resume-admin.spec.ts` used `download.path()`, which does not exist for a remote browser; it uses `saveAs()`.
- The new rail test opens the rail drawer below 900 px.

## Verification (on the per-worktree site `joos-122.jooservices-wp-themes.localhost:8082`, browser on the Dev VM)

| Check | Result |
|---|---|
| `make test-integration` | OK: 8, 325, 43, 21 tests (3 new) |
| `make test-unit` | 273 passed (1 new generator test) |
| `make lint`, `make analyse`, `make docs-check` | pass |
| E2E desktop, theme and shared specs (data reseeded from the snapshot first) | 95 passed |
| E2E desktop, admin specs | 22 passed |
| E2E `about-resume` on phone, tablet and desktop | 9 passed |
| E2E `archives`, `home` on phone and tablet | passed |
| Screenshots `artifacts/screenshots/jooservices/about-*.png` | timeline, chips and custom fields look right in dark and light |

Not a regression of this ticket: `tests/e2e/specs/matrix.spec.ts` reports layout shift above its 0.02 budget through the VM tunnel (the VM is reached through a jump host; measured 0.03 to 0.07), see `docs/guides/docker.md`.

## Notes
- The About page needs the Resume plugin active (it also provides the `joo_company` data); without it the block is not registered.
- Buyers who install the theme without demo import see the pattern `jooservices/about` unchanged.
