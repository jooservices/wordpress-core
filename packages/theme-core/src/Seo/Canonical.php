<?php
/**
 * Canonical URLs.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo;

use JOOservices\ThemeCore\Support\Hooks;

/**
 * Absolute canonical URL of the current page (null where none applies).
 *
 * @internal
 */
final class Canonical {

	/**
	 * Canonical URL.
	 *
	 * @param Context $context Context.
	 */
	public function url( Context $context ): ?string {
		$url = $this->resolve( $context );
		if ( null === $url ) {
			return null;
		}
		$url = $this->stripQuery( $url );
		$out = Hooks::filter( 'joo/seo/canonical', $url, $context );
		return is_string( $out ) && '' !== $out ? $out : null;
	}

	/**
	 * Validates a user supplied canonical: absolute http(s), same host unless filtered.
	 *
	 * @param string $value Raw value.
	 */
	public function validate( string $value ): ?string {
		$value = esc_url_raw( trim( $value ), array( 'http', 'https' ) );
		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return null;
		}
		$home = wp_parse_url( home_url( '/' ) );
		$same = is_array( $home ) && isset( $home['host'] ) && 0 === strcasecmp( $home['host'], $parts['host'] );
		return $same || (bool) Hooks::filter( 'joo/seo/allow_external_canonical', false, $value ) ? $value : null;
	}

	/**
	 * Resolves the URL without filtering.
	 *
	 * @param Context $context Context.
	 */
	private function resolve( Context $context ): ?string {
		$type = $context->type();
		if ( '404' === $type || 'search' === $type ) {
			return null;
		}
		$post = $context->post();
		if ( null !== $post ) {
			$override = $this->validate( (string) get_post_meta( $post->ID, '_joo_seo_canonical', true ) );
			if ( null !== $override ) {
				return $override;
			}
			$link = get_permalink( $post );
			return is_string( $link ) ? $this->paginate( $link, $context->paged() ) : null;
		}
		$object = $context->object();
		if ( $object instanceof \WP_Term ) {
			$link = get_term_link( $object );
			return is_string( $link ) ? $this->paginate( $link, $context->paged() ) : null;
		}
		if ( $object instanceof \WP_User ) {
			return $this->paginate( get_author_posts_url( $object->ID ), $context->paged() );
		}
		if ( 'home' === $type ) {
			$blog = (int) get_option( 'page_for_posts' );
			return $this->paginate( $blog > 0 ? (string) get_permalink( $blog ) : home_url( '/' ), $context->paged() );
		}
		if ( 'front' === $type ) {
			return $this->paginate( home_url( '/' ), $context->paged() );
		}
		if ( 'date' === $type ) {
			return null;
		}
		$link = get_pagenum_link( $context->paged() );
		return $link;
	}

	/**
	 * Adds the page number to a URL.
	 *
	 * @param string $url   Base URL.
	 * @param int    $paged Page.
	 */
	private function paginate( string $url, int $paged ): string {
		if ( $paged < 2 ) {
			return $url;
		}
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			return add_query_arg( is_singular() ? 'page' : 'paged', $paged, $url );
		}
		return trailingslashit( $url ) . ( is_singular() ? '' : 'page/' ) . $paged . '/';
	}

	/**
	 * Removes query arguments that are not whitelisted.
	 *
	 * @param string $url URL.
	 */
	private function stripQuery( string $url ): string {
		$allowed = (array) Hooks::filter( 'joo/seo/canonical_query_args', array() );
		$parts   = explode( '?', $url, 2 );
		if ( ! isset( $parts[1] ) ) {
			return $url;
		}
		parse_str( $parts[1], $args );
		$keep = array_intersect_key( $args, array_flip( array_map( 'strval', $allowed ) ) );
		// Plain permalinks identify content by query arguments: keep those.
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			$keep = array_merge( array_intersect_key( $args, array_flip( array( 'p', 'page_id', 'cat', 'tag', 'author', 'paged', 'page', 'attachment_id', 'm' ) ) ), $keep );
		}
		return array() === $keep ? $parts[0] : $parts[0] . '?' . http_build_query( $keep );
	}
}
