# A new theme in ten minutes

Prerequisites: Docker running, `make init-env` and `make deps` done once.

1. **Scaffold**: `make new-theme NAME=acme TITLE="Acme Co"`. It copies `themes/starter`, renames the slug, text domain, namespace and filenames, links the core (`themes/acme/core`), and registers the text domain in `phpcs.xml.dist` and the folder in `deptrac.yaml`. Then update the workspace lockfile with `bash docker/scripts/runtime.sh npm install`; commit `package-lock.json` together with the theme. Without the lockfile entry, `npm ci`, `make deps` and CI fail with `Missing: @jooservices/theme-acme`.
2. **Run it**: `make demo THEME=acme`. This builds the assets, installs WordPress and the theme, seeds deterministic demo content, and finishes with the health checks.
3. **Look**: open the `WP_SITE_URL` printed by `make demo`. The URL is ticket-scoped; admin credentials stay in `.env`.
4. **Change the look**: edit `themes/acme/theme.json` (palette, fonts and sizes), templates and parts under `themes/acme/templates` and `themes/acme/parts`. The core blocks (`joo/*`) provide the structure. The scaffold has no `assets` folder, so create `themes/acme/assets/src/css/` for your CSS and build with `bash docker/scripts/runtime.sh node tools/build-assets.mjs --root themes/acme --src assets/src --out assets/build` (output is git-ignored). Give custom CSS classes an approved prefix such as `joo-`; Stylelint also permits `wp-`, `is-` and `has-` classes.

   The generated `languages/acme-vi.mo` is copied from the starter catalog and keeps its `starter` metadata. `make i18n` only rebuilds the bundled core, plugin, starter and jooservices catalogs; it does not regenerate a new theme's catalog.

5. **Check**: run `make lint`, `make analyse`, `make test-unit`, and `make test-integration THEME=acme TEST_THEME=acme`. For browser checks, the shared container needs a writable report directory; use a unique `/tmp` path and the common API, HTTP, SEO and smoke specs:

   ```sh
   E2E_REPORT_DIR="/tmp/acme-e2e-$$" E2E_THEME=acme make test-e2e THEME=acme ARGS="tests/e2e/specs/shared/content-api.spec.ts tests/e2e/specs/shared/http.spec.ts tests/e2e/specs/shared/seo.spec.ts tests/e2e/specs/shared/smoke.spec.ts"
   ```

   The full `specs/shared` directory also contains release RTL evidence and view-level accessibility checks. On an uncustomized starter scaffold, those checks fail on contrast and tap-target requirements, and the RTL test writes to `artifacts/evidence/JOOWT-279`, which the shared web user cannot create. Run them after the theme styling and evidence output are ready.

6. **Prove the core stayed untouched**: after committing the theme, run `tools/check-core-untouched.sh origin/develop HEAD acme`. It fails when one commit changes both the theme and `packages/theme-core`.

Keep theme PHP under 50 lines (`functions.php` boots the core from `joo-theme.json`); put the rest in configuration, templates, parts and CSS.

## Walk-through record

Independent literal run on WSL on 2026-10-20 from a clean tracked checkout. The existing ignored `.env`, ticket database and inherited artifacts were preserved; the unique `codex281` theme was created for this run. Times are wall-clock seconds from `time -p`; the demo measurement was after the images were already available. The repository checks used the shared runtimes.

| Step | Result | Time |
| --- | --- | ---: |
| Environment | `make init-env` kept the existing ignored environment file. `make deps` passed; the Composer dependencies were already installed and `npm ci` restored locked packages. | 0.01 s; 9.35 s |
| Scaffold and lockfile | `make new-theme NAME=codex281 TITLE="Codex 281"` created 34 files. `npm install` added the workspace link to the lockfile. | 0.18 s; 4.91 s |
| Demo | `make demo THEME=codex281` completed and health reported 17/17 checks. | 18.16 s |
| Look | The printed worktree URL returned HTTP 200. | 0.06 s |
| Custom CSS | Built one hashed asset, 56 B (73 B gzip). | 0.19 s |
| Lint | `make lint` passed for the generated theme and repository. | 13.12 s |
| Analysis | `make analyse` passed with no PHPStan or Deptrac violations. | 9.05 s |
| Unit tests | `make test-unit`: 284 PHPUnit tests and 350 Vitest tests passed, including the 10 new-theme tests. | 11.88 s |
| Integration | `make test-integration THEME=codex281 TEST_THEME=codex281` passed with 399 tests across its suites; 22 default-excluded tests were skipped. Wall time includes the queue behind the shared test lock. | 823.09 s |
| Browser checks | The focused API, HTTP, SEO and smoke suite passed 66/66 tests through the Dev VM browser (15.6 s in Playwright). Wall time includes the queue behind the shared test lock. | 203.59 s |
| Core proof | The command passed and reported 0 committed theme changes; the scaffold was intentionally uncommitted. Run it after committing a theme to check that commit. | 0.03 s |

Corrections from earlier walk-throughs; this independent replay confirmed them and found no new generator or tooling mismatches:

- The first demo attempt failed because GNU `cp` on WSL rejects macOS's `-cR` option. The snapshot code now uses portable `cp -a`; its failure cleanup also removes the lock and temporary export directory.
- The initial integration run failed after 40.05 s because it loaded `acme` while running the portfolio fixture in the same process. The runner now executes the selected generated theme tests and the portfolio fixture in separate processes.
- The full `specs/shared` browser suite took 161.84 s and failed 23 of 102 checks on the untouched scaffold. The guide now selects the 66 shared API, HTTP, SEO and smoke checks that pass for a new theme.
- The first browser run stopped after 78.53 s because `www-data` could not create its report folder. The guide now sets `E2E_REPORT_DIR` to a writable `/tmp` path through the project runtime wrapper.
- The previous instruction to run `make i18n THEME=acme` was removed. That target does not generate a custom theme catalog and failed on untranslated strings in the bundled catalogs (18.26 s).
- The sample CSS first failed Stylelint in 8.82 s; it was changed to use the theme accent token. The planner test now uses a temporary copy of the real starter so an existing `themes/acme` directory cannot break the suite.
- In the earlier replay, a custom CSS sample failed Stylelint because its class lacked an approved prefix. The guide now documents the accepted `joo-`, `wp-`, `is-` and `has-` prefixes; the corrected sample and lint pass.
- An earlier analysis run failed after 8.24 s because PHPStan found `$argv` might be undefined in `tools/check-core-i18n.php`. The `$argv ?? array()` guard was already present on `develop` before this ticket, so it is baseline context rather than a change from this walkthrough.
