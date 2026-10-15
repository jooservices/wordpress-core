<?php
/**
 * Block joo/theme-toggle.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\DynamicBlock;
use JOOservices\ThemeCore\Blocks\ThemeScheme;

/**
 * Dark/light button (hidden without JavaScript); renders nothing when the theme has a single scheme.
 *
 * @internal
 */
final class ThemeToggle extends DynamicBlock {

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/theme-toggle';
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
		/** @var ThemeScheme $scheme */
		$scheme = $this->container->get( ThemeScheme::class );
		return array(
			'enabled' => count( $scheme->schemes() ) > 1,
			'default' => $scheme->defaultScheme(),
			'_class'  => 'joo-theme-toggle',
		);
	}
}
