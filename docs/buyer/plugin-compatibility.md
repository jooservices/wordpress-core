# Plugin compatibility

Compatibility is based on the repository's current test matrix and varies by plugin version. Read the [full compatibility table](../compatibility.md) before installing a plugin. **PASS** means the recorded version passed its matrix test; **NOT RUN** means no test result is recorded; **UNSUPPORTED** identifies an item with no 1.0 support promise.

The [product compatibility matrix](../../themes/jooservices/docs/compatibility-matrix.md) covers WordPress, PHP, databases, hosting, installation modes, browser targets, and the Tier 1 support policy. It also labels cells that are unsupported or still unvalidated for 1.0.

The theme is designed to work alongside SEO, caching, multilingual, forms, security, and image plugins. Avoid enabling two plugins that both generate canonical tags, social metadata, structured data, or sitemaps. After activation, check a post, archive, search page, and the browser console.

WooCommerce is outside the 1.0 feature set. It is not styled as a shop, and product, cart, and checkout layouts are not guaranteed. The theme should not break when WooCommerce is active.

The compatibility table records test date and version where available. A matrix pass is not a guarantee for every hosting environment or plugin configuration. For a broken page, include the plugin name and version, URL, browser, and console error in a support request.
