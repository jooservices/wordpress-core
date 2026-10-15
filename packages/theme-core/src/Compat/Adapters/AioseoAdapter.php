<?php
/**
 * All in One SEO adapter.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Adapters;

/**
 * AIOSEO keeps its data in its own tables, so everything goes through guarded API calls.
 *
 * @internal
 */
final class AioseoAdapter extends AbstractSeoAdapter {

	/**
	 * Key.
	 */
	public function key(): string {
		return 'aioseo';
	}

	/**
	 * Notice.
	 */
	public function notice(): string {
		return __( 'Core SEO output yields to All in One SEO. Noindex posts are not removed from the core sitemap automatically (AIOSEO stores them in its own table).', 'joo-core' );
	}

	/**
	 * Primary category through aioseo()->standalone->primaryTerm when available.
	 *
	 * @param int $postId Post id.
	 */
	protected function readPrimaryCategory( int $postId ): ?int {
		if ( ! function_exists( 'aioseo' ) ) {
			return null;
		}
		$aioseo = aioseo();
		$term   = is_object( $aioseo ) && isset( $aioseo->standalone->primaryTerm ) ? $aioseo->standalone->primaryTerm : null;
		if ( is_object( $term ) && is_callable( array( $term, 'getPrimaryTerm' ) ) ) {
			$primary = $term->getPrimaryTerm( $postId, 'category' );
			return is_numeric( $primary ) ? (int) $primary : null;
		}
		return null;
	}

	/**
	 * Noindex through aioseo_get_post().
	 *
	 * @param int $postId Post id.
	 */
	protected function readNoindex( int $postId ): ?bool {
		if ( ! function_exists( 'aioseo_get_post' ) ) {
			return null;
		}
		$post = aioseo_get_post( $postId );
		return is_object( $post ) && isset( $post->robots_noindex ) ? (bool) $post->robots_noindex : null;
	}
}
