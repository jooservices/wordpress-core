<?php
/**
 * WooCommerce basics.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Adapters;

/**
 * Tier 3: theme support, no zoom/lightbox/slider by default, utility pages noindex, no lazy fragments and no speculation there.
 *
 * @internal
 */
final class WooCommerceAdapter extends AbstractAdapter {

	/**
	 * Key.
	 */
	public function key(): string {
		return 'woocommerce';
	}

	/**
	 * Notice.
	 */
	public function notice(): string {
		return __( 'Basic support (Tier 3): cart, checkout and account are noindex, excluded from speculation rules and never lazy-fragmented. No storefront design is shipped.', 'joo-core' );
	}

	/**
	 * Hooks.
	 */
	public function boot(): void {
		add_action( 'after_setup_theme', array( $this, 'themeSupport' ), 20 );
		add_filter( 'joo/seo/robots', array( $this, 'robots' ) );
		add_filter( 'joo/lazy/should_lazy', array( $this, 'lazy' ), 10, 2 );
		add_filter( 'wp_speculation_rules_href_exclude_paths', array( $this, 'speculation' ) );
	}

	/**
	 * Declares WooCommerce support without the heavy gallery features.
	 */
	public function themeSupport(): void {
		add_theme_support( 'woocommerce' );
	}

	/**
	 * Whether the current page is a cart, checkout or account page.
	 */
	public function isUtilityPage(): bool {
		foreach ( array( 'is_cart', 'is_checkout', 'is_account_page' ) as $check ) {
			if ( function_exists( $check ) && (bool) $check() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Filter: noindex utility pages.
	 *
	 * @param mixed $robots Directives.
	 * @return mixed
	 */
	public function robots( $robots ) {
		if ( is_array( $robots ) && $this->isUtilityPage() ) {
			return array(
				'noindex' => true,
				'follow'  => true,
			);
		}
		return $robots;
	}

	/**
	 * Filter: no lazy fragments or sections on utility pages.
	 *
	 * @param mixed  $lazy Decision.
	 * @param string $type Item type.
	 */
	public function lazy( $lazy, $type = '' ): bool {
		if ( in_array( $type, array( 'fragment', 'section', 'comments' ), true ) && $this->isUtilityPage() ) {
			return false;
		}
		return (bool) $lazy;
	}

	/**
	 * Filter: paths that must never be speculatively loaded.
	 *
	 * @param mixed $paths Excluded paths.
	 * @return list<string>
	 */
	public function speculation( $paths ): array {
		$paths = is_array( $paths ) ? array_values( array_map( 'strval', $paths ) ) : array();
		foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
			if ( function_exists( 'wc_get_page_permalink' ) ) {
				$path = wp_parse_url( (string) wc_get_page_permalink( $page ), PHP_URL_PATH );
				if ( is_string( $path ) && '' !== $path ) {
					$paths[] = trailingslashit( $path ) . '*';
				}
			}
		}
		return array_values( array_unique( $paths ) );
	}
}
