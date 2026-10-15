<?php
/**
 * Performance diagnostics: admin bar node and self-test.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Performance;

use JOOservices\ThemeCore\Assets\AssetRegistry;
use JOOservices\ThemeCore\Config\Settings;

/**
 * Shows queries, time, enqueued assets and cache statistics in the admin bar (debug mode, users who can manage
 * options) and measures the home page with a loopback request.
 *
 * @internal
 */
final class Diagnostics {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Registry.
	 *
	 * @var AssetRegistry
	 */
	private AssetRegistry $assets;

	/**
	 * Cache.
	 *
	 * @var FragmentCache
	 */
	private FragmentCache $cache;

	/**
	 * Creates the service.
	 *
	 * @param Settings      $settings Settings.
	 * @param AssetRegistry $assets   Asset registry.
	 * @param FragmentCache $cache    Fragment cache.
	 */
	public function __construct( Settings $settings, AssetRegistry $assets, FragmentCache $cache ) {
		$this->settings = $settings;
		$this->assets   = $assets;
		$this->cache    = $cache;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_action( 'admin_bar_menu', array( $this, 'adminBar' ), 100 );
	}

	/**
	 * Whether the diagnostics node is shown.
	 */
	public function visible(): bool {
		return current_user_can( 'manage_options' ) && ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || (bool) $this->settings->get( 'core.debug', false ) );
	}

	/**
	 * Adds the node.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 */
	public function adminBar( \WP_Admin_Bar $bar ): void {
		if ( ! $this->visible() || is_admin() ) {
			return;
		}
		$assets = $this->assets->debugList();
		$stats  = $this->cache->stats();
		$bar->add_node(
			array(
				'id'    => 'joo-perf',
				'title' => sprintf( 'Joo: %1$d queries, %2$s s, %3$d JS, %4$d CSS, cache %5$d/%6$d', get_num_queries(), timer_stop( 0, 3 ), count( $assets['js'] ), count( $assets['css'] ), $stats['hits'], $stats['hits'] + $stats['misses'] ),
			)
		);
	}

	/**
	 * Structured snapshot used by the settings Tools tab and WP-CLI.
	 *
	 * @return array<string, mixed>
	 */
	public function snapshot(): array {
		return array(
			'queries'  => get_num_queries(),
			'assets'   => $this->assets->debugList(),
			'cache'    => $this->cache->stats(),
			'opcache'  => function_exists( 'opcache_get_status' ) && ini_get( 'opcache.enable' ),
			'object'   => (bool) wp_using_ext_object_cache(),
			'compress' => function_exists( 'gzencode' ),
		);
	}

	/**
	 * Measures a URL with a loopback request.
	 *
	 * @param string $url URL (default: home).
	 * @return array<string, mixed>
	 */
	public function selfTest( string $url = '' ): array {
		$url   = '' === $url ? home_url( '/' ) : $url;
		$start = microtime( true );
		$res   = wp_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 3,
			)
		);
		if ( is_wp_error( $res ) ) {
			return array(
				'ok'    => false,
				'error' => $res->get_error_message(),
			);
		}
		$body = (string) wp_remote_retrieve_body( $res );
		return array(
			'ok'      => true,
			'status'  => (int) wp_remote_retrieve_response_code( $res ),
			'ttfb_ms' => (int) round( ( microtime( true ) - $start ) * 1000 ),
			'bytes'   => strlen( $body ),
			'scripts' => substr_count( $body, '<script' ),
			'styles'  => substr_count( $body, 'rel="stylesheet"' ) + substr_count( $body, "rel='stylesheet'" ),
			'images'  => substr_count( $body, '<img' ),
		);
	}
}
