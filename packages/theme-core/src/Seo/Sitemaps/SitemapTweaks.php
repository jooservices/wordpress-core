<?php
/**
 * Improvements on top of the WordPress core sitemaps.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo\Sitemaps;

use JOOservices\ThemeCore\Performance\FragmentCache;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * Excludes noindex and protected posts, adds lastmod, limits taxonomies and keeps one Sitemap line in robots.txt.
 *
 * @internal
 */
final class SitemapTweaks {

	/**
	 * Cache.
	 *
	 * @var FragmentCache
	 */
	private FragmentCache $cache;

	/**
	 * Creates the service.
	 *
	 * @param FragmentCache $cache Fragment cache.
	 */
	public function __construct( FragmentCache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'postQuery' ) );
		add_filter( 'wp_sitemaps_posts_entry', array( $this, 'postEntry' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies', array( $this, 'taxonomies' ) );
		add_filter( 'wp_sitemaps_taxonomies_entry', array( $this, 'termEntry' ), 10, 2 );
		add_filter( 'wp_sitemaps_users_query_args', array( $this, 'userQuery' ) );
		add_filter( 'wp_sitemaps_max_urls', static fn() => 2000 );
		add_filter( 'robots_txt', array( $this, 'robots' ), 20, 2 );
	}

	/**
	 * Excludes noindex and password protected posts.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>
	 */
	public function postQuery( array $args ): array {
		$args['has_password'] = false;
		$plugin_noindex       = array_filter( array_map( 'intval', (array) Hooks::filter( 'joo/seo/noindex_post_ids', array() ) ) );
		if ( array() !== $plugin_noindex ) {
			$args['post__not_in'] = array_values( array_unique( array_merge( (array) ( $args['post__not_in'] ?? array() ), $plugin_noindex ) ) );
		}
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- sitemap queries are cached by core.
		$args['meta_query'] = array(
			'relation' => 'OR',
			array(
				'key'     => '_joo_seo_noindex',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_joo_seo_noindex',
				'value'   => array( '1', 'true' ),
				'compare' => 'NOT IN',
			),
		);
		return $args;
	}

	/**
	 * Adds lastmod to post entries.
	 *
	 * @param array<string, mixed> $entry Entry.
	 * @param \WP_Post             $post  Post.
	 * @return array<string, mixed>
	 */
	public function postEntry( array $entry, $post ): array {
		if ( $post instanceof \WP_Post && '0000-00-00 00:00:00' !== $post->post_modified_gmt ) {
			$entry['lastmod'] = gmdate( 'c', (int) strtotime( $post->post_modified_gmt . ' UTC' ) );
		}
		return $entry;
	}

	/**
	 * Only categories and tags.
	 *
	 * @param array<string, \WP_Taxonomy> $taxonomies Taxonomies.
	 * @return array<string, \WP_Taxonomy>
	 */
	public function taxonomies( array $taxonomies ): array {
		return array_intersect_key( $taxonomies, array_flip( array( 'category', 'post_tag' ) ) );
	}

	/**
	 * Adds lastmod to term entries (date of the newest post in the term).
	 *
	 * @param array<string, mixed> $entry Entry.
	 * @param \WP_Term|int|mixed   $term  Term.
	 * @return array<string, mixed>
	 */
	public function termEntry( array $entry, $term ): array {
		$term = $term instanceof \WP_Term ? $term : get_term( (int) $term );
		if ( ! $term instanceof \WP_Term ) {
			return $entry;
		}
		$lastmod = $this->cache->remember(
			'sitemap-term-' . $term->taxonomy . '-' . $term->term_id,
			HOUR_IN_SECONDS,
			static function () use ( $term ): string {
				$ids = get_posts(
					array(
						'post_type'        => 'post',
						'post_status'      => 'publish',
						'posts_per_page'   => 1,
						'orderby'          => 'modified',
						'order'            => 'DESC',
						'fields'           => 'ids',
						'no_found_rows'    => true,
						'suppress_filters' => true,
						'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
							array(
								'taxonomy' => $term->taxonomy,
								'terms'    => array( $term->term_id ),
							),
						),
					)
				);
				if ( array() === $ids ) {
					return '';
				}
				$post = get_post( (int) $ids[0] );
				return $post instanceof \WP_Post ? gmdate( 'c', (int) strtotime( $post->post_modified_gmt . ' UTC' ) ) : '';
			},
			array( 'posts' ),
			'seo'
		);
		if ( is_string( $lastmod ) && '' !== $lastmod ) {
			$entry['lastmod'] = $lastmod;
		}
		return $entry;
	}

	/**
	 * Only authors with published posts.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>
	 */
	public function userQuery( array $args ): array {
		$args['has_published_posts'] = true;
		return $args;
	}

	/**
	 * Ensures exactly one Sitemap line in the virtual robots.txt.
	 *
	 * @param string $output Output.
	 * @param bool   $public Site visibility.
	 */
	public function robots( $output, $public = true ): string {
		$output = (string) $output;
		if ( ! $public || ! wp_sitemaps_get_server()->sitemaps_enabled() ) {
			return $output;
		}
		$url = home_url( '/wp-sitemap.xml' );
		if ( 0 === preg_match( '/^Sitemap:/mi', $output ) ) {
			return rtrim( $output ) . "\nSitemap: " . esc_url_raw( $url ) . "\n";
		}
		return $output;
	}
}
