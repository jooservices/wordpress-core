<?php
/**
 * Bootstrap for fast unit tests (no WordPress).
 *
 * @package JOOservices
 */

define( 'ABSPATH', sys_get_temp_dir() . '/joo-abspath/' );
define( 'WPINC', 'wp-includes' );

require dirname( __DIR__ ) . '/vendor/autoload.php';

// Core runtime constants normally defined by packages/theme-core/core.php.
if ( ! defined( 'JOO_CORE_DIR' ) ) {
	define( 'JOO_CORE_DIR', dirname( __DIR__ ) . '/packages/theme-core' );
}
