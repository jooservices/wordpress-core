# JOOservices 1.0 compatibility matrix

This matrix describes the JOOservices block theme and the JOOservices Resume companion plugin (`jooservices-resume`) for the ThemeForest release. Evidence is dated; a target version is supported only when the row says **PASS**. **UNVALIDATED** means the release makes no compatibility promise for that cell. **UNSUPPORTED** identifies a deliberate 1.0 boundary.

Last reviewed: 2026-10-16. The nightly compatibility workflow refreshes runtime and plugin reports. See the [plugin results](../../../docs/compatibility.md) and [browser target notes](browser-compatibility.md).

## WordPress and PHP

| Dimension | 1.0 target | Evidence/status | Notes |
| --- | --- | --- | --- |
| WordPress 6.6 | Supported | PASS — integration suite on WordPress 6.6.2 / PHP 8.3.13 in JOOS-108 | Minimum supported WordPress release. |
| WordPress 6.7 | Supported | UNVALIDATED in this report | Run `WP_VERSION=6.7 make test-matrix THEME=jooservices ARGS="--runtime-only"`. |
| WordPress 6.8 | Supported | UNVALIDATED in this report | Run `WP_VERSION=6.8 make test-matrix THEME=jooservices ARGS="--runtime-only"`. |
| Latest stable WordPress | Supported | PASS — 7.1.3 / PHP 8.3.35 integration suite in JOOS-108 | Nightly job records the exact installed core version. |
| Next WordPress beta | Evaluation only | Not a 1.0 support promise | Run `WP_VERSION=beta` for early compatibility feedback. A beta failure does not change the stable support range. |
| PHP 8.1–8.5 | Supported by product code | UNVALIDATED across the full range in this report | Run a runtime variant with `WP_PHP_VERSION=8.1` through `8.5`. PHP 8.1 is end-of-life; PHP 8.3 or newer is recommended by WordPress. |

The product minimum remains WordPress 6.6 and PHP 8.1 as defined by the theme and companion plugin. WordPress currently recommends PHP 8.3+, MariaDB 10.11+, or MySQL 8.0+; PHP 8.1 is outside upstream security support. Review the [WordPress requirements](https://wordpress.org/about/requirements/) and [PHP supported versions](https://www.php.net/supported-versions.php) before choosing a hosting runtime.

## Database and installation modes

| Dimension | 1.0 target | Evidence/status | Notes |
| --- | --- | --- | --- |
| MariaDB 12.3.3 | Lab baseline | Current shared Workspaces database; runtime result recorded by the matrix | Each developer worktree uses its own `wp_JOOS_<number>` database. |
| MariaDB 10.11+ | Candidate minimum | UNVALIDATED below 12.3.3 | WordPress recommendation; the theme has not been tested against every MariaDB patch. |
| MySQL 8.0+ | Candidate minimum | UNVALIDATED | WordPress recommendation; no MySQL service is part of the shared developer stack. |
| WordPress multisite | Unsupported in 1.0 | Deliberate product boundary | Multisite-specific behavior is outside the first release. |
| WordPress installed in a subdirectory | UNVALIDATED | No completed subdirectory-host smoke test | Standard root installations are the supported baseline. |
| Redis object cache off | Supported baseline | Uses WordPress's normal cache behavior | The theme does not require Redis. |
| Redis object cache on | UNVALIDATED | No theme-specific Redis object-cache run recorded | Hosting and cache-plugin interaction depends on the selected drop-in and configuration. |

The database floors follow WordPress's current recommendations. The shared development service currently uses MariaDB 12.3.3; this proves the lab baseline only. Database support outside the recorded cell remains unvalidated.

## Plugins and themes

| Area | 1.0 support | Verification |
| --- | --- | --- |
| Tier 1 plugins | Supported only for rows marked PASS in the [plugin matrix](../../../docs/compatibility.md) | Install, health checks, PHP log scan, and browser smoke; reports record the installed plugin version and runtime. |
| WP Rocket and WPML | Not tested here: WP Rocket, WPML (licensed) | The licensed packages are not included in the lab; no compatibility claim is made. See the plugin matrix for adapters and manual test steps. |
| Elementor | Basic compatibility only | Smoke checks non-builder theme routes. Elementor styling and widgets are outside the theme's design support. |
| WordPress block editor | Supported | The theme is a block theme and uses the Site Editor; no page-builder kit is included. |
| WooCommerce | Basic compatibility only | The matrix checks non-shop routes for fatal errors and layout regressions. Product, cart, and checkout templates are unstyled and unsupported. |
| JOOservices Resume | Supported companion plugin | Included in the showcase and activated during plugin-matrix runs. |
| Child themes | Supported as a customization route | `make test-matrix ARGS="--child-theme"` creates a temporary child theme and repeats the plugin smoke before removing it. Custom child-theme code is outside Tier 1 support. |

`make test-matrix` runs the Tier 1 and Tier 3 plugin list by default. A licensed item without a ZIP in `tests/matrix/vendor/` has not been tested and carries no compatibility claim. A network failure is **BLOCKED** and makes the command fail so the affected cell must be rerun.

### WP Rocket and WPML manual tests

Not tested here: WP Rocket, WPML (licensed). No compatibility claim is made until a matrix run records a result.

- **WP Rocket adapter:** `CacheAdapter`, configured in `packages/theme-core/src/Compat/Adapters/CacheAdapters.php`. Supply the licensed ZIP as `tests/matrix/vendor/wp-rocket.zip`, then run `make test-matrix THEME=jooservices ARGS="--only wp-rocket"`. Review the result under `artifacts/reports/matrix/`. On the test site, clear the WP Rocket cache, exercise minify, defer, Delay JavaScript, and lazy-load settings, and check the tested pages for browser-console and PHP errors.
- **WPML adapter:** `WpmlAdapter`, implementing `Multilingual` and registered by `packages/theme-core/src/Compat/CompatModule.php`. Supply the licensed ZIP as `tests/matrix/vendor/wpml.zip`, then run `make test-matrix THEME=jooservices ARGS="--only wpml"`. Review the result under `artifacts/reports/matrix/`. On the test site, configure two languages, translate a post and a term, and verify the language switcher, translated URLs, and hreflang alternates on the rendered pages.

## Hosting requirements

| Requirement | Minimum for 1.0 | Evidence/status |
| --- | --- | --- |
| WordPress | 6.6+ | See the supported version table above. |
| PHP | 8.1+; recommend 8.3+ | PHP 8.1 has reached upstream end-of-life. |
| Database | MariaDB 10.11+ or MySQL 8.0+ | Candidate minimum based on WordPress recommendations; exact engine/version cells still need validation. |
| Web server | Apache or Nginx with WordPress rewrite support | No LiteSpeed-specific server behavior is required by the theme. |
| HTTPS | Required for a public sale/demo site | Standard WordPress hosting requirement. |
| PHP memory | 128 MB for front-end rendering; 256 MB for admin operations | WordPress's PHP performance guide documents these memory settings; the limited-memory shared-host smoke remains UNVALIDATED. |
| Shared hosting and LiteSpeed | UNVALIDATED | No hosting account/profile has been run against this release. |

WordPress recommends Apache or Nginx and HTTPS. Its [PHP performance guide](https://developer.wordpress.org/advanced-administration/performance/php/) documents `WP_MEMORY_LIMIT` and the higher admin memory limit. Hosting providers may enforce lower server-level limits that WordPress cannot raise.

## Browsers

The owner-approved 1.0 minimums are Chrome and Edge latest plus previous stable, Firefox ESR and latest stable, Safari 16.4+, iOS Safari 16.4+, and Android Chrome current stable. Current Playwright engine checks are not a substitute for exact browser-version or real-device validation; see the [browser compatibility record](browser-compatibility.md).

The theme uses CSS container queries, `:has()`, and `color-mix()`. Safari 16.4 is the declared Safari cutoff. Browsers below the listed minimums are unsupported. The selected target set is checked for these CSS features by the matrix E2E smoke; exact Safari/iOS/Android release tests are recorded separately. Safari 16.4 feature availability is documented by [Apple's Safari 16.4 release notes](https://developer.apple.com/documentation/safari-release-notes/safari-16_4-release-notes) and the [WebKit 16.4 feature summary](https://webkit.org/blog/13966/webkit-features-in-safari-16-4/).

## Tier 1 support policy

Support covers installation and use of the shipped theme and JOOservices Resume plugin, plus reproducible defects on cells marked supported in this matrix. A support request should include the theme and plugin versions, WordPress and PHP versions, hosting/database versions, active plugins, theme or child-theme details, affected URL, browser and operating system, reproduction steps, and relevant console or PHP log output.

Support does not cover custom development, custom child-theme code, hosting administration, content migration, or defects owned by third-party products. The ThemeForest item must state the selected support entitlement and any response commitment; this product does not promise a separate response-time SLA.
