# JOOWT-239: R2.3 plugin matrix evidence

Date: 2026-10-20
Runtime: WordPress 7.1.3, PHP 8.3.30, MariaDB 12.3.3
Branch: `JOOWT-239-core-r2-3-plugin-matrix-on-the-current-d`
Develop base: `4c52d6d`

## Matrix reports

- Starter plugin matrix: [Markdown](../../artifacts/reports/matrix/starter-wp-7.1.3-php-8.3.30-2026-10-20.md) · [JSON](../../artifacts/reports/matrix/starter-wp-7.1.3-php-8.3.30-2026-10-20.json)
- JOOservices plugin matrix: [Markdown](../../artifacts/reports/matrix/jooservices-wp-7.1.3-php-8.3.30-2026-10-20.md) · [JSON](../../artifacts/reports/matrix/jooservices-wp-7.1.3-php-8.3.30-2026-10-20.json)
- JOOservices no-third-party-plugin baseline: [Markdown](../../artifacts/reports/matrix/jooservices-baseline-no-third-party-2026-10-20.md) · [JSON](../../artifacts/reports/matrix/jooservices-baseline-no-third-party-2026-10-20.json)

## Results

The refreshed starter matrix completed on develop `4c52d6d` with 22 PASS, 1 FAIL, and 2 UNSUPPORTED. Yoast SEO 28.7, Rank Math 1.0.280, All in One SEO 5.0.3, SEOPress 10.3, LiteSpeed Cache 7.9.1, W3 Total Cache 2.10.7, and Autoptimize 3.1.16 passed. WP Rocket and WPML were unsupported because their licensed ZIPs are absent from `tests/matrix/vendor/`.

WooCommerce 11.2.1 failed the PHP log check on starter. Its notice says WooCommerce translation loading was triggered before `init`; the same notice appears in the JOOservices matrix. This is a plugin finding, not a core adapter failure.

The refreshed JOOservices matrix completed on develop `4c52d6d` with 0 PASS, 23 FAIL, and 2 UNSUPPORTED. Every installed plugin row failed at least one browser check for cumulative layout shift above the `0.02` budget. The affected plugin versions are Yoast SEO 28.7, Rank Math 1.0.280, All in One SEO 5.0.3, SEOPress 10.3, LiteSpeed Cache 7.9.1, W3 Total Cache 2.10.7, Autoptimize 3.1.16, Polylang 3.9.0, TranslatePress 3.3.8, Contact Form 7 6.2.2, WPForms Lite 2.0.2.2, Fluent Forms 6.2.16, Jetpack 16.3.1, Site Kit 1.190.0, Smush 4.3.4, ShortPixel Image Optimizer 6.6.1, EWWW 8.9.0, TablePress 3.4.1, Advanced Custom Fields 6.8.10, Akismet 5.7.2, Wordfence 9.0.2, WooCommerce 11.2.1, and Elementor 4.3.5. WooCommerce 11.2.1 also emitted the early translation-loading PHP Notice. The linked matrix report names the failing pages for each row.

The separate JOOservices baseline run on develop `4c52d6d`, with no third-party plugin active, also failed the same `<= 0.02` CLS check on all five paths: home, category, tag, search, and single. The linked baseline matrix report records each failing path. This confirms the repeated plugin-row failures are present in the theme baseline and are not isolated adapter regressions. The critical CSS and block-render changes in the incoming commits did not remove the baseline failure.

The matrix PHP classifier treats deprecations from the shared WP-CLI PHAR's bundled Symfony Finder (`wp-cli.phar/vendor/symfony/finder/`) as environment notes, while plugin PHP Notices, warnings, and deprecations remain failures. Its unit test also confirms that a Symfony Finder deprecation from another plugin PHAR remains a failure. WordPress.org TLS update-check warnings are retained as environment notes; failed package downloads are classified as BLOCKED.

## R2 bug handover

The owner assigned the matrix findings to two R2 Bugs:

- **JOOWT-301 — JOOservices CLS budget:** The no-third-party baseline exceeds the `0.02` browser CLS budget on home, category, tag, search, and single. This baseline failure also causes all 23 installed plugin rows to fail their browser check; plugin names and versions are listed in the [JOOservices plugin matrix](../../artifacts/reports/matrix/jooservices-wp-7.1.3-php-8.3.30-2026-10-20.md).
- **JOOWT-302 — WooCommerce early text-domain notice:** WooCommerce 11.2.1 fails the starter matrix PHP log check because translation loading occurs before `init`. The same notice is present in the JOOservices matrix.

These Bug issues are the R2 coverage for the CLS and WooCommerce failures. The individual plugin results, versions, and failing checks remain in the linked matrix reports above.

## Commands and checks

- `DEMO_SET=joowt239-starter-r2 make test-matrix THEME=starter` — report complete on develop `4c52d6d`: 22 PASS, 1 FAIL (WooCommerce 11.2.1 PHP Notice), 2 UNSUPPORTED; command exits 1 for the Notice.
- `make test-matrix THEME=jooservices` — report complete on develop `4c52d6d`: 0 PASS, 23 FAIL (browser CLS on every installed plugin row; WooCommerce 11.2.1 also has a PHP Notice), 2 UNSUPPORTED; command exits 1.
- `MATRIX_VARIANT=baseline-no-third-party make test-matrix THEME=jooservices ARGS="--runtime-only"` — confirms the CLS check fails on all five paths against the 0.02 budget on develop `4c52d6d`.
- `make test-unit` — PHPUnit 283 tests / 3,244 assertions and Vitest 48 files / 343 tests passed.
- `make lint` and `make docs-check` passed after the report update.

The initial starter attempt hit the shared 20-minute operation cap before it could write a report. The matrix action now has a 60-minute bounded cap while other shared WordPress operations retain the 20-minute cap. Matrix health checks run through the shared web runtime, and browser JSON output uses the writable E2E report directory.
