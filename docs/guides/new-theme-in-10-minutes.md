# A new theme in ten minutes

Prerequisites: Docker running, `make init-env` done once.

1. **Scaffold** (10 seconds): `make new-theme NAME=acme TITLE="Acme Co"`. It copies `themes/starter`, renames the slug, text domain, namespace and files, links the core (`themes/acme/core`), registers the text domain in `phpcs.xml.dist` and the folder in `deptrac.yaml`, and adds the theme to `tests/matrix/themes.json`.
2. **Run it** (about 20 seconds after the images are built): `make demo THEME=acme`. It seeds the same deterministic demo content for every theme and finishes with `make health`.
3. **Look**: open the `WP_SITE_URL` from the worktree `.env` (defaults to `http://127.0.0.1:8082`); admin credentials stay in `.env`.
4. **Change the look**: edit `themes/acme/theme.json` (palette, fonts, sizes) and the templates and parts in `themes/acme/templates` and `parts`. The block markup of the core blocks (`joo/*`) gives you the structure; add CSS under `assets/src/css` and build with `node tools/build-assets.mjs --root themes/acme --src assets/src --out assets/build`.
5. **Check**: `make lint`, `make analyse`, `make test-integration THEME=acme TEST_THEME=acme`, `E2E_THEME=acme make test-e2e THEME=acme ARGS="specs/shared"`.
6. **Prove the core stayed untouched**: `tools/check-core-untouched.sh <base> HEAD acme` fails when one commit changes both the theme and `packages/theme-core`.

The generated `acme` theme passes lint, `tools/check-headers.php`, the shared e2e specs and `make health` without changes (measured: 16 s from scaffold to a healthy demo on a warm Docker cache).

Keep theme PHP under 50 lines (`functions.php` boots the core from `joo-theme.json`); everything else is configuration, templates and CSS.
