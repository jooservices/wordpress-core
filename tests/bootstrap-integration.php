<?php
/**
 * Bootstrap for integration tests (real WordPress, ticket-scoped schema).
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

$joo_root      = dirname( __DIR__ ); // The worktree (or any checkout) this bootstrap belongs to.
$joo_theme     = getenv( 'JOO_TEST_THEME' );
$joo_theme_dir = null;
if ( is_string( $joo_theme ) && 1 === preg_match( '/^[a-z][a-z0-9-]{1,30}$/', $joo_theme ) ) {
	foreach ( array( $joo_root . '/themes/' . $joo_theme, $joo_root . '/tests/fixtures/themes/' . $joo_theme ) as $candidate ) {
		if ( is_file( $candidate . '/functions.php' ) ) {
			$joo_theme_dir = $candidate;
			break;
		}
	}
}
require_once $joo_root . '/vendor/autoload.php';
require_once __DIR__ . '/worktree-autoload.php';
require_once $joo_tests_dir . '/includes/functions.php';

// Core is loaded exactly like a theme would load it (core.php), before WordPress finishes booting.
$joo_core = $joo_root . '/packages/theme-core/core.php';
if ( is_file( $joo_core ) ) {
	define( 'JOO_CORE_THROW', true );
	require_once $joo_core;
}

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $joo_root, $joo_theme ): void {
		if ( 'magazine' === $joo_theme ) {
			return;
		}

		$plugin = $joo_root . '/plugins/jooservices-resume/jooservices-resume.php';
		if ( is_file( $plugin ) ) {
			require_once $plugin;
		}
	}
);

tests_add_filter(
	'after_setup_theme',
	static function () use ( $joo_theme_dir ): void {
		if ( null !== $joo_theme_dir && is_file( $joo_theme_dir . '/functions.php' ) ) {
			require_once $joo_theme_dir . '/functions.php';
		}
	},
	1
);

require $joo_tests_dir . '/includes/bootstrap.php';

// WP's bootstrap has already executed these theme callbacks. Some integration
// cases replay after_setup_theme after Kernel::reset(); retain core callbacks,
// but do not repeat the theme's one-time supports/editor setup after wp_loaded.
$joo_theme = getenv( 'JOO_TEST_THEME' );
if ( null !== $joo_theme_dir && $joo_theme && isset( $GLOBALS['wp_filter']['after_setup_theme'] ) && $GLOBALS['wp_filter']['after_setup_theme'] instanceof WP_Hook ) {
	$joo_theme_functions = realpath( $joo_theme_dir . '/functions.php' );
	if ( $joo_theme_functions ) {
		foreach ( $GLOBALS['wp_filter']['after_setup_theme']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'];
				if ( $function instanceof Closure && realpath( ( new ReflectionFunction( $function ) )->getFileName() ) === $joo_theme_functions ) {
					remove_action( 'after_setup_theme', $function, $priority );
				}
			}
		}
	}
}
