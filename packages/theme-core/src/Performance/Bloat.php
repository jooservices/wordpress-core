<?php
/**
 * Front-end bloat removal.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Performance;

use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * Switches (settings perf.*) that remove unneeded WordPress output on the front end only.
 *
 * @internal
 */
final class Bloat {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Creates the service.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Applies the enabled switches (front end requests only).
	 */
	public function hook(): void {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$active = array_keys( array_filter( $this->switches() ) );
		$active = Hooks::filter( 'joo/perf/bloat', $active );
		$active = is_array( $active ) ? $active : array();

		if ( in_array( 'disable_emoji', $active, true ) ) {
			$this->emoji();
		}
		if ( in_array( 'disable_embeds', $active, true ) ) {
			$this->embeds();
		}
		if ( in_array( 'dashicons_guests', $active, true ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'dashicons' ), 100 );
		}
		if ( in_array( 'block_css_granular', $active, true ) ) {
			add_filter( 'should_load_separate_core_block_assets', '__return_true' );
			add_filter( 'wp_should_load_block_assets_on_demand', '__return_true' );
		}
		if ( in_array( 'remove_head_noise', $active, true ) ) {
			$this->headNoise();
		}
	}

	/**
	 * Switch states.
	 *
	 * @return array<string, bool>
	 */
	public function switches(): array {
		$out = array();
		foreach ( array( 'disable_emoji', 'disable_embeds', 'dashicons_guests', 'block_css_granular', 'remove_head_noise' ) as $name ) {
			$out[ $name ] = (bool) $this->settings->get( 'perf.' . $name, true );
		}
		return $out;
	}

	/**
	 * Removes the emoji detection script, styles and related filters.
	 */
	private function emoji(): void {
		$this->removeAction( 'wp_head', 'print_emoji_detection_script' );
		$this->removeAction( 'wp_print_styles', 'print_emoji_styles' );
		$this->removeAction( 'embed_head', 'print_emoji_detection_script' );
		$this->removeAction( 'the_content_feed', 'wp_staticize_emoji' );
		$this->removeAction( 'comment_text_rss', 'wp_staticize_emoji' );
		$this->removeAction( 'wp_mail', 'wp_staticize_emoji_for_email' );
	}

	/**
	 * Removes a core callback whatever priority WordPress registered it with (priorities differ between releases).
	 *
	 * @param string $hook     Hook name.
	 * @param string $callback Callback name.
	 */
	private function removeAction( string $hook, string $callback ): void {
		// Some callbacks are registered at several priorities (for example the oEmbed discovery links).
		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$priority = has_action( $hook, $callback );
			if ( false === $priority ) {
				return;
			}
			remove_action( $hook, $callback, (int) $priority );
		}
	}

	/**
	 * Removes wp-embed and oEmbed discovery output.
	 */
	private function embeds(): void {
		$this->removeAction( 'wp_head', 'wp_oembed_add_discovery_links' );
		$this->removeAction( 'wp_head', 'wp_oembed_add_host_js' );
		add_action(
			'wp_enqueue_scripts',
			static function (): void {
				wp_dequeue_script( 'wp-embed' );
			},
			100
		);
	}

	/**
	 * Removes Dashicons for visitors unless a queued style depends on them.
	 */
	public function dashicons(): void {
		if ( is_user_logged_in() ) {
			return;
		}
		$styles = wp_styles();
		foreach ( $styles->queue as $handle ) {
			if ( 'dashicons' !== $handle && isset( $styles->registered[ $handle ] ) && in_array( 'dashicons', (array) $styles->registered[ $handle ]->deps, true ) ) {
				return;
			}
		}
		wp_dequeue_style( 'dashicons' );
	}

	/**
	 * Removes generator, RSD, WLW and shortlink output (feeds and the REST discovery link stay).
	 */
	private function headNoise(): void {
		$this->removeAction( 'wp_head', 'rsd_link' );
		$this->removeAction( 'wp_head', 'wlwmanifest_link' );
		$this->removeAction( 'wp_head', 'wp_generator' );
		$this->removeAction( 'wp_head', 'wp_shortlink_wp_head' );
		$this->removeAction( 'template_redirect', 'wp_shortlink_header' );
		add_filter( 'the_generator', '__return_empty_string' );
	}
}
