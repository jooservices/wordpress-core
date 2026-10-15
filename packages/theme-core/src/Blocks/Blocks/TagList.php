<?php
/**
 * Block joo/tag-list.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\FragmentBlock;
use JOOservices\ThemeCore\Performance\FragmentCache;

/**
 * Tags as chips or a weighted cloud (font size 0.85 to 1.15 rem).
 *
 * @internal
 */
final class TagList extends FragmentBlock {

	public const MIN_WEIGHT = 0.85;
	public const MAX_WEIGHT = 1.15;

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/tag-list';
	}

	/**
	 * Fragment name.
	 */
	public static function fragment(): string {
		return 'tag-list';
	}

	/**
	 * Fragment schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function schema(): array {
		return array(
			'limit'   => array(
				'type'    => 'int',
				'default' => 24,
				'min'     => 5,
				'max'     => 60,
			),
			'orderby' => array(
				'type'    => 'enum',
				'enum'    => array( 'count', 'name' ),
				'default' => 'count',
			),
			'counts'  => array(
				'type'    => 'bool',
				'default' => true,
			),
			'style'   => array(
				'type'    => 'enum',
				'enum'    => array( 'chips', 'cloud' ),
				'default' => 'chips',
			),
			'title'   => array(
				'type'      => 'string',
				'maxLength' => 60,
			),
		);
	}

	/**
	 * Fragment arguments.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return array<string, int|string|bool>
	 */
	protected function fragmentArgs( array $attrs ): array {
		return array(
			'limit'   => max( 5, min( 60, (int) ( $attrs['limit'] ?? 24 ) ) ),
			'orderby' => 'name' === ( $attrs['orderby'] ?? 'count' ) ? 'name' : 'count',
			'counts'  => ! array_key_exists( 'showCounts', $attrs ) || (bool) $attrs['showCounts'],
			'style'   => 'cloud' === ( $attrs['style'] ?? 'chips' ) ? 'cloud' : 'chips',
			'title'   => (string) ( $attrs['title'] ?? '' ),
		);
	}

	/**
	 * Attributes from fragment arguments.
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @return array<string, mixed>
	 */
	protected function attrsFromArgs( array $args ): array {
		return array(
			'limit'      => (int) ( $args['limit'] ?? 24 ),
			'orderby'    => (string) ( $args['orderby'] ?? 'count' ),
			'showCounts' => (bool) ( $args['counts'] ?? true ),
			'style'      => (string) ( $args['style'] ?? 'chips' ),
			'title'      => (string) ( $args['title'] ?? '' ),
		);
	}

	/**
	 * Weight of a tag among the shown ones (0.85 to 1.15).
	 *
	 * @param int $count Tag count.
	 * @param int $min   Smallest count.
	 * @param int $max   Largest count.
	 */
	public static function weight( int $count, int $min, int $max ): float {
		if ( $max <= $min ) {
			return 1.0;
		}
		$ratio = ( $count - $min ) / ( $max - $min );
		return round( self::MIN_WEIGHT + max( 0.0, min( 1.0, $ratio ) ) * ( self::MAX_WEIGHT - self::MIN_WEIGHT ), 3 );
	}

	/**
	 * Data.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @param \WP_Block            $block Block.
	 * @return array<string, mixed>
	 */
	protected function data( array $attrs, \WP_Block $block ): array {
		unset( $block );
		$args = $this->fragmentArgs( $attrs );
		/** @var FragmentCache $cache */
		$cache  = $this->container->get( FragmentCache::class );
		$rows   = $cache->remember(
			sprintf( 'tag-list-%d-%s', $args['limit'], $args['orderby'] ),
			300,
			static function () use ( $args ): array {
				$terms = get_terms(
					array(
						'taxonomy'   => 'post_tag',
						'orderby'    => (string) $args['orderby'],
						'order'      => 'count' === $args['orderby'] ? 'DESC' : 'ASC',
						'number'     => (int) $args['limit'],
						'hide_empty' => true,
					)
				);
				$out   = array();
				foreach ( is_array( $terms ) ? $terms : array() as $term ) {
					if ( $term instanceof \WP_Term ) {
						$link  = get_term_link( $term );
						$out[] = array(
							'name'  => $term->name,
							'url'   => is_string( $link ) ? $link : '',
							'count' => (int) $term->count,
						);
					}
				}
				return $out;
			},
			array( 'terms', 'posts' ),
			'blocks'
		);
		$rows   = is_array( $rows ) ? $rows : array();
		$counts = array_column( $rows, 'count' );
		$min    = array() === $counts ? 0 : (int) min( $counts );
		$max    = array() === $counts ? 0 : (int) max( $counts );
		foreach ( $rows as $i => $row ) {
			$rows[ $i ]['weight'] = self::weight( (int) $row['count'], $min, $max );
		}
		return array(
			'tags'       => $rows,
			'showCounts' => (bool) $args['counts'],
			'cloud'      => 'cloud' === $args['style'],
			'title'      => '' !== (string) $args['title'] ? (string) $args['title'] : __( 'Tags', 'joo-core' ),
			'_class'     => 'joo-tags' . ( 'cloud' === $args['style'] ? ' joo-tags--cloud' : '' ),
		);
	}
}
