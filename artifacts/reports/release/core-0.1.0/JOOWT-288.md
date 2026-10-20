# JOOWT-288 G6-G9 Evidence

- Machine: WSL (`wsl`)
- Date: 2026-10-20
- Branch: `JOOWT-288-core-r11-4-gates-g6-to-g9-for-starter-an`
- Source commit tested: `1bd6483881aae786a2c5219e3b535d66c781d336`
- Runtime: WordPress 7.1.3, PHP 8.3.30, MariaDB 12.3.3; site database `wp_JOOWT_288`

## Results

| Theme | Gate | Command | Result |
| --- | --- | --- | --- |
| starter | G6 | `make demo THEME=starter` | PASS, 17/17 health checks |
| starter | G6 | `make health THEME=starter` | PASS, 17/17 checks |
| starter | G7 | `make perf THEME=starter` | BLOCKED, missing `themes/starter/budgets.json` |
| starter | G8 | `MATRIX_VARIANT=joowt288-retry make test-matrix THEME=starter` | FAIL, 22 pass, 1 fail, 2 unsupported |
| starter | G9 | `make package THEME=starter` | PASS, 275 files, 477 KB |
| jooservices | G6 | `make demo THEME=jooservices` | PASS, 17/17 health checks |
| jooservices | G6 | `make health THEME=jooservices` | PASS, 17/17 checks |
| jooservices | G7 | `make perf THEME=jooservices` | BLOCKED, Chromium executable absent from shared runtime cache |
| jooservices | G8 | `MATRIX_VARIANT=joowt288-retry make test-matrix THEME=jooservices` | FAIL, 0 pass, 23 fail, 2 unsupported |
| jooservices | G9 | `make package THEME=jooservices` | PASS, 311 files, 836 KB |

## Package checksums

| Artifact | Size | SHA-256 |
| --- | ---: | --- |
| `artifacts/package/starter-0.1.0.zip` | 488,844 bytes | `fc1c2352a416892a4eb5464fea95ca5108713f8e3921c9220f005ee2a18831bb` |
| `artifacts/package/jooservices-0.1.0.zip` | 856,077 bytes | `38265041a71bac276c097554309e9b24d073e66c74f1e763d56e3b02ba2c2b2b` |
| `artifacts/package/jooservices-resume-0.1.0.zip` | 92,832 bytes | `c0da51d1677ce82e9c28e9a90adca4a29ac244e9f98a8ab2159b655f4aab5f54` |

## Failure detail

- Starter G7: `tools/perf.mjs` exits before Lighthouse measurement with `ENOENT` reading `themes/starter/budgets.json`.
- Jooservices G7: Lighthouse exits before measurement with `ENOENT` spawning `/tmp/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome`.
- Starter G8: `woocommerce` (WooCommerce 11.2.1) records a PHP notice from `_load_textdomain_just_in_time`; `wp-rocket` and `wpml` are unsupported because licensed ZIPs are not present.
- Jooservices G8: all 23 testable plugin rows fail the browser CLS budget (all fail `home`; some also fail other routes); `wp-rocket` and `wpml` are unsupported for missing licensed ZIPs. WooCommerce also records the PHP notice.

The retained run manifest is `artifacts/reports/JOOWT-288/run-manifest.md`. Final matrix JSON and Markdown reports are retained under `artifacts/reports/matrix/`. The first starter matrix attempt failed because the shared web user could not create the missing E2E report directory; the retry is the final G8 result.

Supplementary `make lint` passed.

Ticket scope: **JOOWT-288 is reporting-only.** Release status is **BLOCKED** pending the missing starter budget policy, an available Chromium runtime for `make perf`, and resolution of the observed G8 failures. Remediation remains in follow-up Bugs JOOWT-301 through JOOWT-304.
