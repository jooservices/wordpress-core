# JOOWP-302: WooCommerce early text-domain notice

## Finding and attribution

The JOOWT-239 starter report records WooCommerce 11.2.1 as the only failing plugin row on WordPress 7.1.3 / PHP 8.3.30. The PHP log contains the `_load_textdomain_just_in_time` notice for the `woocommerce` domain. The same notice appears in the JOOservices report, so it is not specific to the starter theme. The source scan for this change found no WooCommerce text-domain translation calls in `themes/starter` or `packages/theme-core`. The core's WooCommerce adapter only declares theme support on `after_setup_theme`; its translated compatibility notice uses `joo-core`. The early domain load is therefore attributed to WooCommerce's own plugin bootstrap, and WooCommerce source is not patched.

The prior JOOWT-288 starter rerun uses the same WordPress, PHP, and WooCommerce versions. Its report records 23 PASS, 0 FAIL, 2 UNSUPPORTED and one WooCommerce early-textdomain notice as a known third-party note. The PHP classifier now documents and preserves that exact notice in the WooCommerce row while continuing to fail other PHP problems and the same notice in any other row.

## Evidence

- Original failing starter row: [Markdown report](../../artifacts/reports/matrix/starter-wp-7.1.3-php-8.3.30-2026-10-20.md) · [JSON report](../../artifacts/reports/matrix/starter-wp-7.1.3-php-8.3.30-2026-10-20.json).
- Cross-theme observation: [JOOservices Markdown report](../../artifacts/reports/matrix/jooservices-wp-7.1.3-php-8.3.30-2026-10-20.md) · [JSON report](../../artifacts/reports/matrix/jooservices-wp-7.1.3-php-8.3.30-2026-10-20.json).
- Passing starter rerun with the exception documented in the row: [Markdown report](../../artifacts/reports/matrix/starter-joowt288-final-2026-10-20.md) · [JSON report](../../artifacts/reports/matrix/starter-joowt288-final-2026-10-20.json).
- Earlier evidence and test commands: [JOOWT-239 report](JOOWT-239.md).

The matrix rule is documented in [testing guidance](../guides/testing.md#woocommerce-early-text-domain-notice). `tools/matrix.test.mjs` verifies that the WooCommerce row passes with the notice reported, while the notice remains a PHP problem for other rows and unrelated WooCommerce PHP warnings still fail.
