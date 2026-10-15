<?php
/**
 * Loading policy: jQuery, version query strings, style loading attributes.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Assets;

use JOOservices\ThemeCore\Config\Settings;

/**
 * Removes jQuery when nothing needs it (setting perf.jquery_frontend), drops the version query string from
 * hashed core assets and adds fetchpriority to deferred styles.
 *
 * @internal
 */
final class LoaderPolicy {

	/**
	 * Handle prefix of assets registered by core and themes through the registry.
	 */
	private const PREFIX = 'joo-';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Creates the policy.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'applyJqueryPolicy' ), 999 );
		add_filter( 'style_loader_src', array( $this, 'stripVersion' ), 10, 2 );
		add_filter( 'script_loader_src', array( $this, 'stripVersion' ), 10, 2 );
		add_filter( 'style_loader_tag', array( $this, 'styleTag' ), 10, 2 );
	}

	/**
	 * Applies perf.jquery_frontend (auto|keep|remove).
	 */
	public function applyJqueryPolicy(): void {
		if ( is_admin() || is_customize_preview() ) {
			return;
		}
		$mode = (string) $this->settings->get( 'perf.jquery_frontend', 'auto' );
		if ( 'keep' === $mode ) {
			return;
		}
		if ( 'auto' === $mode && $this->somethingNeedsJquery() ) {
			return;
		}
		foreach ( array( 'jquery', 'jquery-core', 'jquery-migrate' ) as $handle ) {
			wp_dequeue_script( $handle );
		}
	}

	/**
	 * Whether any queued script (other than jQuery itself) depends on jQuery.
	 */
	public function somethingNeedsJquery(): bool {
		$scripts = wp_scripts();
		foreach ( $scripts->queue as $handle ) {
			if ( in_array( $handle, array( 'jquery', 'jquery-core', 'jquery-migrate' ), true ) ) {
				continue;
			}
			if ( $this->dependsOnJquery( $handle, $scripts, array() ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Strips ver from URLs of assets this core registered (their names are content-hashed).
	 *
	 * @param string $src    Source URL.
	 * @param string $handle Handle.
	 */
	public function stripVersion( string $src, string $handle ): string {
		if ( 0 === strpos( $handle, self::PREFIX ) && false !== strpos( $src, 'ver=' ) ) {
			return remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	/**
	 * Adds fetchpriority="low" to styles printed late (they are not needed for the first paint).
	 *
	 * @param string $tag    Link tag.
	 * @param string $handle Handle.
	 */
	public function styleTag( string $tag, string $handle ): string {
		if ( 0 === strpos( $handle, self::PREFIX ) && did_action( 'wp_footer' ) && false === strpos( $tag, 'fetchpriority' ) ) {
			return str_replace( '<link ', '<link fetchpriority="low" ', $tag );
		}
		return $tag;
	}

	/**
	 * Recursive dependency check.
	 *
	 * @param string        $handle  Script handle.
	 * @param \WP_Scripts   $scripts Registry.
	 * @param list<string>  $seen    Handles already visited.
	 */
	private function dependsOnJquery( string $handle, \WP_Scripts $scripts, array $seen ): bool {
		if ( in_array( $handle, $seen, true ) || ! isset( $scripts->registered[ $handle ] ) ) {
			return false;
		}
		$seen[] = $handle;
		foreach ( (array) $scripts->registered[ $handle ]->deps as $dependency ) {
			if ( in_array( $dependency, array( 'jquery', 'jquery-core', 'jquery-migrate' ), true ) || $this->dependsOnJquery( (string) $dependency, $scripts, $seen ) ) {
				return true;
			}
		}
		return false;
	}
}
