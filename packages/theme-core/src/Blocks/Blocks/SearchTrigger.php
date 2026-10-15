<?php
/**
 * Block joo/search-trigger.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\DynamicBlock;

/**
 * A link to the search page that opens a search dialog when JavaScript is available.
 *
 * @internal
 */
final class SearchTrigger extends DynamicBlock {

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/search-trigger';
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
		return array(
			'label'       => (string) ( $attrs['label'] ?? __( 'Search', 'joo-core' ) ),
			'placeholder' => __( 'Search articles…', 'joo-core' ),
			'action'      => home_url( '/' ),
			'fallback'    => home_url( '/?s=' ),
			'_class'      => 'joo-search',
		);
	}
}
