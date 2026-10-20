<?php
/**
 * Translation groups of the built-in language tagging.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\I18n;

use JOOservices\ThemeCore\Performance\FragmentCache;
use JOOservices\ThemeCore\Seo\Context;

/**
 * The post without `_joo_translation_of` is the root of its group; translations point to the root.
 *
 * @internal
 */
final class Translations {

	/**
	 * Cache.
	 *
	 * @var FragmentCache|null
	 */
	private ?FragmentCache $cache;

	/**
	 * Creates the service.
	 *
	 * @param FragmentCache|null $cache Cache (optional).
	 */
	public function __construct( ?FragmentCache $cache = null ) {
		$this->cache = $cache;
	}

	/**
	 * Root post id of the group of a post.
	 *
	 * @param int $postId Post id.
	 */
	public function root( int $postId ): int {
		$source = (int) get_post_meta( $postId, '_joo_translation_of', true );
		return $source > 0 ? $source : $postId;
	}

	/**
	 * Published members of the group (including the post itself when published) as language => post id.
	 *
	 * @param int $postId Post id.
	 * @return array<string, int>
	 */
	public function forPost( int $postId ): array {
		$compute = fn(): array => $this->compute( $postId );
		if ( null === $this->cache ) {
			return $compute();
		}
		$result = $this->cache->remember( 'translations-' . $this->root( $postId ), 300, $compute, array( 'posts' ), 'i18n' );
		return is_array( $result ) ? $result : array();
	}

	/**
	 * Computes the group.
	 *
	 * @param int $postId Post id.
	 * @return array<string, int>
	 */
	private function compute( int $postId ): array {
		$root = $this->root( $postId );
		$ids  = get_posts(
			array(
				'post_type'        => (string) get_post_type( $postId ),
				'post_status'      => 'publish',
				'posts_per_page'   => 20, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded group size.
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_joo_translation_of',
						'value' => (string) $root,
					),
				),
			)
		);
		$out  = array();
		foreach ( array_unique( array_merge( array( $root ), array_map( 'intval', $ids ) ) ) as $id ) {
			$lang = (string) get_post_meta( $id, '_joo_lang', true );
			if ( '' !== $lang && 'publish' === get_post_status( $id ) && ! isset( $out[ Context::normaliseLanguage( $lang ) ] ) ) {
				$out[ Context::normaliseLanguage( $lang ) ] = (int) $id;
			}
		}
		return $out;
	}

	/**
	 * When a root is trashed the oldest remaining translation becomes the root and the others follow it.
	 *
	 * @param int $postId Trashed post id.
	 */
	public function promoteAfterTrash( int $postId ): void {
		$members = get_posts(
			array(
				'post_type'        => (string) get_post_type( $postId ),
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'   => 20, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded group size.
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_joo_translation_of',
						'value' => (string) $postId,
					),
				),
			)
		);
		if ( array() === $members ) {
			return;
		}
		$newRoot = (int) array_shift( $members );
		delete_post_meta( $newRoot, '_joo_translation_of' );
		foreach ( $members as $id ) {
			update_post_meta( (int) $id, '_joo_translation_of', $newRoot );
		}
	}
}
