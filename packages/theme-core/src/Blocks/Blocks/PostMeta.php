<?php
/**
 * Block joo/post-meta.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\DynamicBlock;
use JOOservices\ThemeCore\Support\ReadingTime;

/**
 * Category, dates, reading time, author and tags of a post (works in the Query Loop and on singular templates).
 *
 * @internal
 */
final class PostMeta extends DynamicBlock {

	private const ALLOWED = array( 'category', 'date', 'updated', 'reading', 'author', 'tags' );

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/post-meta';
	}

	/**
	 * Data.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @param \WP_Block            $block Block.
	 * @return array<string, mixed>
	 */
	protected function data( array $attrs, \WP_Block $block ): array {
		$postId = (int) ( $block->context['postId'] ?? get_the_ID() );
		$post   = $postId > 0 ? get_post( $postId ) : null;
		if ( ! $post instanceof \WP_Post ) {
			return array( 'items' => array() );
		}
		$wanted = array_values( array_intersect( self::ALLOWED, array_map( 'strval', (array) ( $attrs['items'] ?? array( 'category', 'date', 'updated', 'reading' ) ) ) ) );
		$items  = array();
		foreach ( $wanted as $item ) {
			$row = $this->item( $item, $post, ! array_key_exists( 'linkCategory', $attrs ) || (bool) $attrs['linkCategory'] );
			if ( null !== $row ) {
				$items[] = $row;
			}
		}
		$separator = 'slash' === ( $attrs['separator'] ?? 'dot' ) ? 'slash' : 'dot';
		return array(
			'items'  => $items,
			'_class' => 'joo-meta joo-meta--' . $separator,
		);
	}

	/**
	 * One meta item.
	 *
	 * @param string   $item         Item name.
	 * @param \WP_Post $post         Post.
	 * @param bool     $linkCategory Link the category.
	 * @return array<string, string>|null
	 */
	private function item( string $item, \WP_Post $post, bool $linkCategory ): ?array {
		switch ( $item ) {
			case 'category':
				$cats = 'post' === $post->post_type ? get_the_category( $post->ID ) : array();
				if ( array() === $cats ) {
					return null;
				}
				$link = get_category_link( $cats[0] );
				return array(
					'type'  => 'category',
					'label' => $cats[0]->name,
					'url'   => $linkCategory && is_string( $link ) ? $link : '',
				);
			case 'date':
				return array(
					'type'     => 'date',
					'label'    => (string) get_the_date( '', $post ),
					'datetime' => (string) get_post_time( 'c', true, $post ),
				);
			case 'updated':
				if ( (int) get_post_modified_time( 'U', true, $post ) <= (int) get_post_time( 'U', true, $post ) + DAY_IN_SECONDS ) {
					return null;
				}
				return array(
					'type'     => 'updated',
					/* translators: %s: date. */
					'label'    => sprintf( __( 'Updated %s', 'joo-core' ), (string) get_the_modified_date( '', $post ) ),
					'datetime' => (string) get_post_modified_time( 'c', true, $post ),
				);
			case 'reading':
				$minutes = ReadingTime::forPost( $post->ID )['minutes'];
				return $minutes > 0 ? array(
					'type'  => 'reading',
					/* translators: %d: minutes. */
					'label' => sprintf( _n( '%d min read', '%d min read', $minutes, 'joo-core' ), $minutes ),
				) : null;
			case 'author':
				return array(
					'type'  => 'author',
					'label' => (string) get_the_author_meta( 'display_name', (int) $post->post_author ),
					'url'   => get_author_posts_url( (int) $post->post_author ),
				);
			case 'tags':
				$tags = get_the_tags( $post->ID );
				return is_array( $tags ) && array() !== $tags ? array(
					'type'  => 'tags',
					'label' => implode( ', ', array_map( static fn( \WP_Term $t ) => $t->name, array_slice( $tags, 0, 4 ) ) ),
				) : null;
		}
		return null;
	}
}
