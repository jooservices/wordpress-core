<?php
/**
 * JOOservices theme core bootstrap. Themes require this file once from functions.php.
 *
 * Written in PHP 7.4-compatible syntax so that an unsupported PHP version produces an admin notice
 * instead of a parse error.
 *
 * @package JOOservices\ThemeCore
 */

if ( defined( 'JOO_CORE_VERSION' ) ) {
	// Another copy of core is already loaded: never redeclare, remember where the duplicate lives.
	if ( ! defined( 'JOO_CORE_DUPLICATE' ) ) {
		define( 'JOO_CORE_DUPLICATE', __DIR__ );
	}
	return;
}

define( 'JOO_CORE_VERSION', '0.1.0' );
if ( ! defined( 'JOO_CORE_DIR' ) ) {
	define( 'JOO_CORE_DIR', __DIR__ );
}
define( 'JOO_CORE_MIN_PHP', '8.1' );
define( 'JOO_CORE_MIN_WP', '6.6' );

if ( PHP_VERSION_ID < 80100 ) {
	define( 'JOO_CORE_UNSUPPORTED', 'php' );
	if ( function_exists( 'add_action' ) ) {
		add_action(
			'admin_notices',
			function () {
				if ( function_exists( 'current_user_can' ) && current_user_can( 'edit_theme_options' ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html( 'JOOservices core requires PHP ' . JOO_CORE_MIN_PHP . ' or newer. The theme features are disabled.' ) . '</p></div>';
				}
			}
		);
	}
	return;
}

spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'JOOservices\\ThemeCore\\';
		if ( strncmp( $class_name, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);
