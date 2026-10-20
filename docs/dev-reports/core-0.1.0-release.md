# Core 0.1.0 Release Gate Evidence

Date: 2026-10-20. Machine: WSL (`wsl`). Ticket: JOOWT-288. Source commit tested: `1bd6483881aae786a2c5219e3b535d66c781d336`.

This report records G6-G9 for the `starter` and `jooservices` themes on the shared WordPress runtime (WordPress 7.1.3, PHP 8.3.30, MariaDB 12.3.3). Both demo runs used the isolated `wp_JOOWT_288` worktree database.

Supplementary `make lint` passed on this worktree (headers, core i18n, PHPCS, ESLint, Stylelint, and Prettier).

| Gate | Starter | Jooservices |
| --- | --- | --- |
| G6 Demo and health | PASS: `make demo` and `make health`; 17/17 checks each | PASS: `make demo` and `make health`; 17/17 checks each |
| G7 Budgets | BLOCKED: `make perf` cannot read missing `themes/starter/budgets.json` | BLOCKED: `make perf` cannot launch the absent shared-runtime Chromium binary |
| G8 Matrix | FAIL: 22 pass, 1 fail, 2 unsupported | FAIL: 0 pass, 23 fail, 2 unsupported |
| G9 Package | PASS: `starter-0.1.0.zip`, 275 files, 477 KB | PASS: `jooservices-0.1.0.zip`, 311 files, 836 KB |

## Release blockers

- Starter G7 stops before measurement because `tools/perf.mjs` reads `themes/starter/budgets.json`, which is not present. Follow-up: [JOOWT-303](https://jooservices.atlassian.net/browse/JOOWT-303).
- Jooservices G7 stops because `/tmp/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome` is absent from the shared runtime cache. Follow-up: [JOOWT-304](https://jooservices.atlassian.net/browse/JOOWT-304).
- Starter G8 fails the `woocommerce` row (WooCommerce 11.2.1) on a PHP notice from `_load_textdomain_just_in_time`. Follow-up: [JOOWT-302](https://jooservices.atlassian.net/browse/JOOWT-302).
- Jooservices G8 fails all 23 testable plugin rows on the browser CLS budget; every row fails `home`, and several also fail category, tag, search, or single routes. The failing keys are `yoast`, `rankmath`, `aioseo`, `seopress`, `litespeed-cache`, `w3-total-cache`, `autoptimize`, `polylang`, `translatepress`, `cf7`, `wpforms`, `fluentform`, `jetpack`, `site-kit`, `smush`, `shortpixel`, `ewww`, `tablepress`, `acf`, `akismet`, `wordfence`, `woocommerce`, and `elementor`. Follow-up: [JOOWT-301](https://jooservices.atlassian.net/browse/JOOWT-301).
- Both matrices report `wp-rocket` and `wpml` as unsupported because their licensed ZIPs are absent from `tests/matrix/vendor/`.

The first starter matrix attempt could not create `artifacts/reports/e2e` as `www-data` and is retained as a setup diagnostic. After preparing that ignored evidence directory, the full matrix was rerun; the counts above come from the valid retry. The jooservices CLS failures are consistent with the documented sensitivity of tunnel-based browser measurements, but they remain gate failures on this run.

## Evidence

- Full logs and run manifest: `artifacts/reports/JOOWT-288/`.
- Final matrix reports: `artifacts/reports/matrix/starter-joowt288-retry-2026-10-20.{md,json}` and `artifacts/reports/matrix/jooservices-joowt288-retry-2026-10-20.{md,json}`.
- Per-plugin browser JSON: `artifacts/reports/e2e/`.
- Package artifacts and checksums: `artifacts/package/` and `artifacts/reports/release/core-0.1.0/JOOWT-288.md`.

Release readiness remains **blocked by follow-up Bugs [JOOWT-303](https://jooservices.atlassian.net/browse/JOOWT-303), [JOOWT-304](https://jooservices.atlassian.net/browse/JOOWT-304), [JOOWT-302](https://jooservices.atlassian.net/browse/JOOWT-302), and [JOOWT-301](https://jooservices.atlassian.net/browse/JOOWT-301)**. G6 and G9 pass for both themes; G7 is unavailable for both and G8 has the failures listed above. This report does not declare the release ready. Existing G2-G5 evidence files remain under `artifacts/reports/release/core-0.1.0/`; JOOWT-288 did not rerun those gates.
