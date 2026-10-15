<?php
/**
 * Block joo/reading-progress.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\DynamicBlock;

/**
 * Thin progress bar for the article (updated by the view module).
 *
 * @internal
 */
final class ReadingProgress extends DynamicBlock {

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/reading-progress';
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
		return array(
			'show'   => is_singular(),
			'_class' => 'joo-progress',
		);
	}
}
