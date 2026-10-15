<?php
/**
 * Base for SEO plugin adapters.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Adapters;

/**
 * Primary category and noindex data from an SEO plugin. Every call is guarded and never throws.
 *
 * @internal
 */
abstract class AbstractSeoAdapter extends AbstractAdapter {

	/**
	 * Primary category of a post (null when the plugin has none).
	 *
	 * @param int $postId Post id.
	 */
	public function primaryCategory( int $postId ): ?int {
		try {
			$id = $this->readPrimaryCategory( $postId );
		} catch ( \Throwable $error ) {
			return null;
		}
		return null !== $id && $id > 0 ? $id : null;
	}

	/**
	 * Whether the plugin marks a post noindex (null when unknown).
	 *
	 * @param int $postId Post id.
	 */
	public function noindex( int $postId ): ?bool {
		try {
			return $this->readNoindex( $postId );
		} catch ( \Throwable $error ) {
			return null;
		}
	}

	/**
	 * Meta clause that selects noindex posts (null when the plugin keeps the flag outside post meta).
	 *
	 * @return array<string, mixed>|null
	 */
	public function noindexMetaClause(): ?array {
		return null;
	}

	/**
	 * Hooks the primary category and sitemap exclusions into core.
	 */
	public function boot(): void {
		add_filter( 'joo/seo/primary_category', array( $this, 'filterPrimary' ), 10, 2 );
		add_filter( 'joo/seo/noindex_post_ids', array( $this, 'filterNoindexIds' ) );
	}

	/**
	 * Filter: primary category (only fills it when nothing else decided).
	 *
	 * @param mixed $current Current value.
	 * @param int   $postId  Post id.
	 */
	public function filterPrimary( $current, $postId ): int {
		return (int) $current > 0 ? (int) $current : (int) $this->primaryCategory( (int) $postId );
	}

	/**
	 * Filter: post ids the plugin marks noindex (bounded query, newest first).
	 *
	 * @param mixed $ids Ids from other sources.
	 * @return list<int>
	 */
	public function filterNoindexIds( $ids ): array {
		$ids    = array_values( array_map( 'intval', is_array( $ids ) ? $ids : array() ) );
		$clause = $this->noindexMetaClause();
		if ( null === $clause ) {
			return $ids;
		}
		$found = get_posts(
			array(
				'post_type'        => 'any',
				'post_status'      => 'publish',
				'posts_per_page'   => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded id lookup.
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => array( $clause ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		return array_values( array_unique( array_merge( $ids, array_map( 'intval', $found ) ) ) );
	}

	/**
	 * Plugin specific primary category lookup.
	 *
	 * @param int $postId Post id.
	 */
	abstract protected function readPrimaryCategory( int $postId ): ?int;

	/**
	 * Plugin specific noindex lookup.
	 *
	 * @param int $postId Post id.
	 */
	abstract protected function readNoindex( int $postId ): ?bool;
}
