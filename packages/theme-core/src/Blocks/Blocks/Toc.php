<?php
/**
 * Block joo/toc.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\DynamicBlock;
use JOOservices\ThemeCore\Blocks\HeadingAnchors;

/**
 * Table of contents from the post headings (the same ids the heading filter writes).
 *
 * @internal
 */
final class Toc extends DynamicBlock {

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/toc';
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
		$levels = array_values( array_intersect( array( 2, 3, 4 ), array_map( static fn( $l ) => (int) ltrim( (string) $l, 'hH' ), (array) ( $attrs['levels'] ?? array( 'h2', 'h3' ) ) ) ) );
		$min    = max( 1, (int) ( $attrs['minHeadings'] ?? 3 ) );
		$items  = $post instanceof \WP_Post ? HeadingAnchors::outline( $post->post_content, $levels ) : array();
		return array(
			'items'     => count( $items ) >= $min ? $items : array(),
			'collapsed' => ! empty( $attrs['collapsed'] ),
			'_class'    => 'joo-toc',
		);
	}
}
