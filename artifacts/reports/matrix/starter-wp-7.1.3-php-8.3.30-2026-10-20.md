# Plugin compatibility matrix: starter (2026-10-20)

Variant: wp-7.1.3-php-8.3.30. WordPress 7.1.3 / PHP 8.3.30 / 12.3.3-MariaDB-ubu2404.
Tested 22 pass, 1 fail, 0 skipped, 2 unsupported, 0 blocked.

| Key | Plugin or check | Version | Result | Notes |
| --- | --- | --- | --- | --- |
| yoast | Yoast SEO | 28.7 | PASS |  |
| rankmath | Rank Math SEO | 1.0.280 | PASS |  |
| aioseo | All in One SEO | 5.0.3 | PASS |  |
| seopress | SEOPress | 10.3 | PASS |  |
| litespeed-cache | LiteSpeed Cache | 7.9.1 | PASS |  |
| wp-rocket | WP Rocket | - | UNSUPPORTED | licensed plugin: zip not present in tests/matrix/vendor/ (not tested) |
| w3-total-cache | W3 Total Cache | 2.10.7 | PASS |  |
| autoptimize | Autoptimize | 3.1.16 | PASS |  |
| polylang | Polylang | 3.9.0 | PASS |  |
| wpml | WPML | - | UNSUPPORTED | licensed plugin: zip not present in tests/matrix/vendor/ (not tested) |
| translatepress | TranslatePress | 3.3.8 | PASS |  |
| cf7 | Contact Form 7 | 6.2.2 | PASS |  |
| wpforms | WPForms Lite | 2.0.2.2 | PASS |  |
| fluentform | Fluent Forms | 6.2.16 | PASS |  |
| jetpack | Jetpack | 16.3.1 | PASS |  |
| site-kit | Site Kit by Google | 1.190.0 | PASS |  |
| smush | Smush | 4.3.4 | PASS |  |
| shortpixel | ShortPixel Image Optimizer | 6.6.1 | PASS |  |
| ewww | EWWW Image Optimizer | 8.9.0 | PASS |  |
| tablepress | TablePress | 3.4.1 | PASS |  |
| acf | Advanced Custom Fields | 6.8.10 | PASS |  |
| akismet | Akismet | 5.7.2 | PASS |  |
| wordfence | Wordfence | 9.0.2 | PASS |  |
| woocommerce | WooCommerce | 11.2.1 | FAIL | php log: 1 problem(s), first: [20-Oct-2026 08:27:34 UTC] PHP Notice:  Function _load_textdomain_just_in_time was called <strong>incorrectly</strong>. Translation loading for the <code>woocom; Basic compatibility only; no custom page-builder or shop design is included.; Shop templates are unstyled; only non-shop pages are checked. |
| elementor | Elementor | 4.3.5 | PASS | environment warning: WordPress.org update check could not establish TLS (1 warning(s)); Basic compatibility only; no custom page-builder or shop design is included. |
