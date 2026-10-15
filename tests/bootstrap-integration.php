<?php
/**
 * Bootstrap for integration tests (real WordPress, wp_tests schema).
 *
 * Environment: WP_TESTS_DIR (set by docker/scripts/phpunit-integration.sh), JOO_TEST_THEME (optional theme slug).
 *
 * @package JOOservices
 */

$joo_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $joo_tests_dir || ! is_file( $joo_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WP_TESTS_DIR is not set or invalid. Run via: make test-integration\n" );
	exit( 1 );
}

require_once '/workspace/vendor/autoload.php';
require_once $joo_tests_dir . '/includes/functions.php';

// Core is loaded exactly like a theme would load it (core.php), before WordPress finishes booting.
$joo_core = '/workspace/packages/theme-core/core.php';
if ( is_file( $joo_core ) ) {
	define( 'JOO_CORE_THROW', true );
	require_once $joo_core;
}

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		$plugin = '/workspace/plugins/jooservices-content/jooservices-content.php';
		if ( is_file( $plugin ) ) {
			require_once $plugin;
		}
	}
);

tests_add_filter(
	'after_setup_theme',
	static function (): void {
		$theme = getenv( 'JOO_TEST_THEME' );
		if ( $theme ) {
			$dir = '/workspace/themes/' . $theme;
			if ( is_file( $dir . '/functions.php' ) ) {
				require_once $dir . '/functions.php';
			}
		}
	},
	1
);

require $joo_tests_dir . '/includes/bootstrap.php';
