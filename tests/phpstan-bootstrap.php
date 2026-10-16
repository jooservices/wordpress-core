<?php
/**
 * Constants that exist at runtime but are unknown to static analysis.
 *
 * @package JOOservices
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/var/www/html/' );
}
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}
if ( ! defined( 'JOO_CORE_VERSION' ) ) {
	define( 'JOO_CORE_VERSION', '0.1.0' );
}
if ( ! defined( 'JOO_CORE_DIR' ) ) {
	define( 'JOO_CORE_DIR', dirname( __DIR__ ) . '/packages/theme-core' );
}
if ( ! defined( 'JOO_CORE_MIN_PHP' ) ) {
	define( 'JOO_CORE_MIN_PHP', '8.1' );
}
if ( ! defined( 'JOO_CORE_MIN_WP' ) ) {
	define( 'JOO_CORE_MIN_WP', '6.6' );
}

require_once __DIR__ . '/stubs/WP_CLI.php';
