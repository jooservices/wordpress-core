# Core 0.1.0 Release Gate Evidence

Date: 2026-10-20. Machine: WSL (`wsl`). Ticket: JOOWT-288. Source commit tested: `c336f08432b3ccb3c6cc53a01b45d20e94121f68`.

This report records G6-G9 for the `starter` and `jooservices` themes on the shared WordPress runtime (WordPress 7.1.3, PHP 8.3.30, MariaDB 12.3.3). Both demo runs used the isolated `wp_JOOWT_288` worktree database.

Supplementary `make lint` passed on this worktree (headers, core i18n, PHPCS, ESLint, Stylelint, and Prettier).

| Gate | Starter | Jooservices |
| --- | --- | --- |
| G6 Demo and health | PASS: `make demo` and `make health`; 17/17 checks each | PASS: `make demo` and `make health`; 17/17 checks each |
| G7 Budgets | BLOCKED: `make perf` cannot read missing `themes/starter/budgets.json` | BLOCKED: `make perf` cannot launch the absent shared-runtime Chromium binary |
| G8 Matrix | FAIL: 22 pass, 1 fail, 2 unsupported | FAIL: 0 pass, 23 fail, 2 unsupported |
| G9 Package | PASS: `starter-0.1.0.zip`, 275 files, 477 KB | PASS: `jooservices-0.1.0.zip`, 311 files, 836 KB |

## Release blockers

- Starter G7 stops before measurement because `tools/perf.mjs` reads missing `themes/starter/budgets.json`. Follow-up: [JOOWT-303](https://jooservices.atlassian.net/browse/JOOWT-303).
- Jooservices G7 stops because `/tmp/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome` is absent from the shared runtime cache. Follow-up: [JOOWT-304](https://jooservices.atlassian.net/browse/JOOWT-304).
- Starter G8 fails the `woocommerce` row (WooCommerce 11.2.1) on a PHP notice from `_load_textdomain_just_in_time`. Follow-up: [JOOWT-302](https://jooservices.atlassian.net/browse/JOOWT-302).
- Jooservices G8 fails all 23 testable plugin rows on the browser CLS budget, with failures across home, category, tag, search, and single routes; route-specific results are in the final matrix report. The failing keys are `yoast`, `rankmath`, `aioseo`, `seopress`, `litespeed-cache`, `w3-total-cache`, `autoptimize`, `polylang`, `translatepress`, `cf7`, `wpforms`, `fluentform`, `jetpack`, `site-kit`, `smush`, `shortpixel`, `ewww`, `tablepress`, `acf`, `akismet`, `wordfence`, `woocommerce`, and `elementor`. Follow-up: [JOOWT-301](https://jooservices.atlassian.net/browse/JOOWT-301).
- Both matrices report `wp-rocket` and `wpml` as unsupported because their licensed ZIPs are absent from `tests/matrix/vendor/`.

The jooservices CLS failures are consistent with the documented sensitivity of tunnel-based browser measurements, but they remain gate failures on this run.

## Evidence

- Run manifest: `artifacts/reports/JOOWT-288/run-manifest.md`.
- Final matrix reports: `artifacts/reports/matrix/starter-joowt288-rebased-2026-10-20.{md,json}` and `artifacts/reports/matrix/jooservices-joowt288-rebased-2026-10-20.{md,json}`.
- Gate results, tested commit, and package checksums: `artifacts/reports/release/core-0.1.0/JOOWT-288.md`.
- Built package archives: `artifacts/package/starter-0.1.0.zip`, `artifacts/package/jooservices-0.1.0.zip`, and `artifacts/package/jooservices-resume-0.1.0.zip`.

Owner decision: **JOOWT-288 is reporting-only.** It records G6-G9 results; remediation for the G7 and G8 findings remains in follow-up Bugs [JOOWT-301](https://jooservices.atlassian.net/browse/JOOWT-301) through [JOOWT-304](https://jooservices.atlassian.net/browse/JOOWT-304). G6 and G9 pass for both themes; G7 is unavailable for both and G8 has the failures listed above. This report does not declare the release ready. Existing G2-G5 evidence files remain under `artifacts/reports/release/core-0.1.0/`; JOOWT-288 did not rerun those gates.
