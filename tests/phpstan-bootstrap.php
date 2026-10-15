<?php
/**
 * Constants that exist at runtime but are unknown to static analysis.
 *
 * @package JOOservices
 */

define( 'ABSPATH', '/var/www/html/' );
define( 'WPINC', 'wp-includes' );
define( 'JOO_CORE_VERSION', '0.1.0' );
define( 'JOO_CORE_DIR', dirname( __DIR__ ) . '/packages/theme-core' );
define( 'JOO_CORE_MIN_PHP', '8.1' );
define( 'JOO_CORE_MIN_WP', '6.6' );
