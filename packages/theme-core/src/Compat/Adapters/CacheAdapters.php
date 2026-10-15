<?php
/**
 * Specs of the Tier-1 cache, optimisation and image plugins.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed's documented purge action.

namespace JOOservices\ThemeCore\Compat\Adapters;

use JOOservices\ThemeCore\Compat\Detector;

/**
 * Builds the adapters (option names verified against the plugins' documented settings; see docs/compatibility.md).
 *
 * @internal
 */
final class CacheAdapters {

	/**
	 * Reads a key from an option that stores an array.
	 *
	 * @param string $option Option.
	 * @param string $key    Key.
	 */
	private static function option( string $option, string $key ): bool {
		$value = get_option( $option, array() );
		return is_array( $value ) && ! empty( $value[ $key ] );
	}

	/**
	 * Adapters for every supported cache, optimisation and image plugin.
	 *
	 * @param Detector $detector Detector.
	 * @return list<CacheAdapter>
	 */
	public static function build( Detector $detector ): array {
		return array(
			new CacheAdapter(
				$detector,
				'litespeed-cache',
				array(
					'lazy'       => static fn() => (bool) get_option( 'litespeed.conf.media-lazy' ),
					'ccss'       => static fn() => (bool) get_option( 'litespeed.conf.optm-ccss_gen' ),
					'minify'     => static fn() => (bool) get_option( 'litespeed.conf.optm-js_min' ),
					'exclusions' => array( array( 'litespeed_optimize_js_excludes', 'list' ), array( 'litespeed_optm_js_defer_exc', 'list' ) ),
					'purge'      => static function (): void {
						do_action( 'litespeed_purge_all' );
					},
					'notice'     => __( 'Our script handles are excluded from LiteSpeed JS optimisation and defer. Lazy loading yields when LiteSpeed lazy-loads images.', 'joo-core' ),
				)
			),
			new CacheAdapter(
				$detector,
				'wp-rocket',
				array(
					'lazy'       => static fn() => self::option( 'wp_rocket_settings', 'lazyload' ),
					'ccss'       => static fn() => self::option( 'wp_rocket_settings', 'async_css' ),
					'minify'     => static fn() => self::option( 'wp_rocket_settings', 'minify_js' ),
					'exclusions' => array( array( 'rocket_exclude_js', 'list' ), array( 'rocket_exclude_defer_js', 'list' ), array( 'rocket_delay_js_exclusions', 'list' ) ),
					'purge'      => static function (): void {
						if ( function_exists( 'rocket_clean_domain' ) ) {
							rocket_clean_domain();
						}
					},
					'notice'     => __( 'Our scripts are excluded from WP Rocket minify, defer and "Delay JavaScript". Lazy loading yields when WP Rocket lazy-loads images.', 'joo-core' ),
				)
			),
			new CacheAdapter(
				$detector,
				'w3-total-cache',
				array(
					'minify'     => static function (): bool {
						if ( ! function_exists( 'w3tc_config' ) ) {
							return false;
						}
						$config = w3tc_config();
						return is_object( $config ) && is_callable( array( $config, 'get_boolean' ) ) && (bool) $config->get_boolean( 'minify.enabled' );
					},
					'exclusions' => array( array( 'w3tc_minify_js_do_tag_minification', 'tag' ) ),
					'purge'      => static function (): void {
						if ( function_exists( 'w3tc_flush_all' ) ) {
							w3tc_flush_all();
						}
					},
					'notice'     => __( 'Our script tags are skipped by W3 Total Cache minification. Configure lazy loading in either W3TC or the theme, not both.', 'joo-core' ),
				)
			),
			new CacheAdapter(
				$detector,
				'autoptimize',
				array(
					'lazy'       => static fn() => 'on' === get_option( 'autoptimize_imgopt_checkbox_field_3' ),
					'ccss'       => static fn() => 'on' === get_option( 'autoptimize_css_defer' ),
					'minify'     => static fn() => 'on' === get_option( 'autoptimize_js' ),
					'exclusions' => array( array( 'autoptimize_filter_js_exclude', 'csv' ) ),
					'purge'      => static function (): void {
						if ( class_exists( 'autoptimizeCache', false ) && is_callable( array( 'autoptimizeCache', 'clearall' ) ) ) {
							\autoptimizeCache::clearall();
						}
					},
					'notice'     => __( 'Our script handles are excluded from Autoptimize aggregation. Lazy loading yields when Autoptimize lazy-loads images.', 'joo-core' ),
				)
			),
			new CacheAdapter(
				$detector,
				'smush',
				array(
					'lazy'   => static function (): bool {
						$settings = get_option( 'wp-smush-settings', array() );
						return is_array( $settings ) && ! empty( $settings['lazy_load'] );
					},
					'notice' => __( 'Lazy loading yields when Smush lazy-loads images. Smush WebP conversion and the theme image sizes work together.', 'joo-core' ),
				)
			),
			new CacheAdapter(
				$detector,
				'ewww',
				array(
					'lazy'   => static fn() => (bool) get_option( 'ewww_image_optimizer_lazy_load' ),
					'notice' => __( 'Lazy loading yields when EWWW lazy-loads images.', 'joo-core' ),
				)
			),
			new CacheAdapter(
				$detector,
				'shortpixel',
				array(
					'notice' => __( 'ShortPixel compresses uploads. Its lazy-loading state cannot be detected reliably, so the theme keeps lazy loading on: switch one of them off if you see double loading.', 'joo-core' ),
				)
			),
		);
	}
}
