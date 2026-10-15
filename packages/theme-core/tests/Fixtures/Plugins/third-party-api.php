<?php
// phpcs:ignoreFile
/**
 * Fake third-party APIs for the compatibility integration tests. Behaviour comes from $GLOBALS['joo_fake'].
 * Functions are only called when the matching adapter is made "active" by a fake Detector.
 */

if ( ! function_exists( 'pll_the_languages' ) ) {
	function pll_the_languages( $args = array() ) {
		return $GLOBALS['joo_fake']['pll_languages'] ?? array();
	}
	function pll_current_language( $field = 'slug' ) {
		return $GLOBALS['joo_fake']['pll_current'] ?? 'en';
	}
	function pll_default_language( $field = 'slug' ) {
		return $GLOBALS['joo_fake']['pll_default'] ?? 'en';
	}
	function pll_get_post_translations( $id ) {
		return $GLOBALS['joo_fake']['pll_posts'][ $id ] ?? array();
	}
	function pll_get_term_translations( $id ) {
		return $GLOBALS['joo_fake']['pll_terms'][ $id ] ?? array();
	}
	function pll_home_url( $slug = '' ) {
		return 'https://example.test/' . $slug . '/';
	}
}
if ( ! function_exists( 'trp_get_languages' ) ) {
	function trp_get_languages() {
		return array(
			'en_US' => 'English',
			'vi'    => 'Tiếng Việt',
		);
	}
	function trp_get_url_converter() {
		return new class() {
			public function get_url_for_language( $locale, $url = null ) {
				return 'https://example.test/' . substr( $locale, 0, 2 ) . '/' . ( null === $url ? 'page/' : '' );
			}
		};
	}
}
if ( ! function_exists( 'is_cart' ) ) {
	function is_cart() {
		return ! empty( $GLOBALS['joo_fake']['woo_page'] );
	}
	function is_checkout() {
		return false;
	}
	function is_account_page() {
		return false;
	}
	function wc_get_page_permalink( $page ) {
		return 'https://example.test/' . $page . '/';
	}
}
