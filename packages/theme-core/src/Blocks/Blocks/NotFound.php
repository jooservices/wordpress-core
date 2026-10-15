<?php
/**
 * Block joo/not-found.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\DynamicBlock;
use JOOservices\ThemeCore\Performance\FragmentCache;
use JOOservices\ThemeCore\Seo\Text;

/**
 * Terminal-style 404 view: a static transcript (meaningful without JavaScript), a search form and server-side suggestions.
 * The page itself is a real 404 (status and robots are handled by the SEO module).
 *
 * @internal
 */
final class NotFound extends DynamicBlock {

	private const INDEX_SIZE = 200;

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/not-found';
	}

	/**
	 * The requested path (no query string, decoded, at most 200 characters).
	 */
	public static function requestedPath(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the value is only echoed escaped.
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$path = rawurldecode( '' === $path ? '/' : $path );
		$path = (string) preg_replace( '/[\x00-\x1F\x7F]+/', '', $path );
		return Text::truncate( $path, 200 );
	}

	/**
	 * Similarity (0..100) between the requested path and a slug.
	 *
	 * @param string $path Requested path.
	 * @param string $slug Post slug.
	 */
	public static function score( string $path, string $slug ): float {
		$last = basename( rtrim( strtolower( $path ), '/' ) );
		$last = (string) preg_replace( '/\.[a-z0-9]+$/', '', $last );
		if ( '' === $last || '' === $slug ) {
			return 0.0;
		}
		similar_text( $last, strtolower( $slug ), $percent );
		return round( (float) $percent, 2 );
	}

	/**
	 * Suggestions: the best matching published posts (at most 3).
	 *
	 * @param string                                          $path  Requested path.
	 * @param list<array{slug: string, title: string, url: string}> $index Post index.
	 * @return list<array{title: string, url: string}>
	 */
	public static function suggest( string $path, array $index ): array {
		$scored = array();
		foreach ( $index as $row ) {
			$scored[] = array(
				'row'   => $row,
				'score' => self::score( $path, $row['slug'] ),
			);
		}
		usort( $scored, static fn( array $a, array $b ) => $b['score'] <=> $a['score'] );
		$best = array_filter( $scored, static fn( array $s ) => $s['score'] >= 30.0 );
		$pick = array() === $best ? array_slice( $scored, 0, 3 ) : array_slice( array_values( $best ), 0, 3 );
		return array_map(
			static fn( array $s ) => array(
				'title' => $s['row']['title'],
				'url'   => $s['row']['url'],
			),
			$pick
		);
	}

	/**
	 * Data.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @param \WP_Block            $block Block.
	 * @return array<string, mixed>
	 */
	protected function data( array $attrs, \WP_Block $block ): array {
		unset( $attrs, $block );
		/** @var FragmentCache $cache */
		$cache = $this->container->get( FragmentCache::class );
		$index = $cache->remember(
			'not-found-index',
			600,
			static function (): array {
				$ids = get_posts(
					array(
						'post_type'      => 'post',
						'post_status'    => 'publish',
						'has_password'   => false,
						'posts_per_page' => self::INDEX_SIZE,
						'orderby'        => 'date',
						'order'          => 'DESC',
						'fields'         => 'ids',
						'no_found_rows'  => true,
					)
				);
				$out = array();
				foreach ( $ids as $id ) {
					$post = get_post( (int) $id );
					if ( $post instanceof \WP_Post ) {
						$out[] = array(
							'slug'  => $post->post_name,
							'title' => Text::plain( get_the_title( $post ) ),
							'url'   => (string) get_permalink( $post ),
						);
					}
				}
				return $out;
			},
			array( 'posts' ),
			'blocks'
		);
		/** @var list<array{slug: string, title: string, url: string}> $index */
		$index = is_array( $index ) ? array_values( $index ) : array();
		$path  = self::requestedPath();
		return array(
			'path'        => $path,
			'suggestions' => self::suggest( $path, $index ),
			'index'       => $index,
			'home'        => home_url( '/' ),
			'_class'      => 'joo-404',
		);
	}
}
