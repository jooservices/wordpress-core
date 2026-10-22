<?php
// phpcs:ignoreFile -- standalone probe defining minimal WordPress stubs.
/**
 * Boots the real Kernel without WordPress (minimal hook stubs) and judges it with BootBudget, like KernelTest does.
 * Exit 0 when the median boot stays under BootBudget::MAX_BOOT_UNITS, 1 otherwise. Usage: php tools/boot-units-probe.php
 *
 * @package JOOservices\Tools
 */

declare(strict_types=1);

use JOOservices\TestKit\BootBudget;
use JOOservices\ThemeCore\Kernel;

define( 'ABSPATH', '/tmp/' );
$GLOBALS['wp_version'] = '6.6';
$GLOBALS['wp_filter']  = array();
$GLOBALS['wp_actions'] = array();

function add_filter( $hook, $callback ) {
	$GLOBALS['wp_filter'][ $hook ][] = $callback;
	return true;
}
function add_action( $hook, $callback ) {
	return add_filter( $hook, $callback );
}
function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['wp_filter'][ $hook ] ?? array() as $callback ) {
		$value = $callback( $value, ...$args );
	}
	return $value;
}
function do_action( $hook, ...$args ) {
	$GLOBALS['wp_actions'][ $hook ] = ( $GLOBALS['wp_actions'][ $hook ] ?? 0 ) + 1;
	foreach ( $GLOBALS['wp_filter'][ $hook ] ?? array() as $callback ) {
		$callback( ...$args );
	}
}
function did_action( $hook ) {
	return $GLOBALS['wp_actions'][ $hook ] ?? 0;
}
function has_action( $hook ) {
	return ! empty( $GLOBALS['wp_filter'][ $hook ] );
}
function __( $text ) {
	return $text;
}
function esc_html__( $text ) {
	return $text;
}
function esc_html( $text ) {
	return $text;
}
function get_option( $key, $default = false ) {
	return $default;
}
function is_admin() {
	return false;
}
function wp_json_encode( $value, $flags = 0 ) {
	return json_encode( $value, $flags );
}
function trailingslashit( $text ) {
	return rtrim( $text, '/' ) . '/';
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
function current_user_can() {
	return false;
}
function get_bloginfo() {
	return '6.6';
}
function sanitize_key( $key ) {
	return strtolower( $key );
}

$root = dirname( __DIR__ );
require $root . '/packages/theme-core/core.php';
require $root . '/packages/test-kit/src/BootBudget.php';
do_action( 'after_setup_theme' );

$manifest = $root . '/packages/theme-core/tests/Fixtures/manifests/valid.json';
$hooks    = $GLOBALS['wp_filter'];
$samples  = BootBudget::measure(
	static function () use ( $manifest, $hooks ): float {
		$GLOBALS['wp_filter'] = $hooks;
		Kernel::reset();
		$start = hrtime( true );
		Kernel::boot( $manifest );
		return ( hrtime( true ) - $start ) / 1e6;
	},
	20
);
$units    = BootBudget::units( $samples['boots'], $samples['spins'] );
printf( "boot %.3f ms, spin %.3f ms, %.3f units (ceiling %.1f), container %s\n", BootBudget::median( $samples['boots'] ), BootBudget::median( $samples['spins'] ), $units, BootBudget::MAX_BOOT_UNITS, Kernel::container() ? 'built' : 'MISSING' );
exit( null !== Kernel::container() && $units < BootBudget::MAX_BOOT_UNITS ? 0 : 1 );
