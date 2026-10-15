<?php
/**
 * Block joo/lang-switcher.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\DynamicBlock;
use JOOservices\ThemeCore\Compat\Multilingual\Resolver;

/**
 * Language links from the multilingual abstraction; nothing is rendered with a single language.
 *
 * @internal
 */
final class LangSwitcher extends DynamicBlock {

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/lang-switcher';
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
		/** @var Resolver $resolver */
		$resolver  = $this->container->get( Resolver::class );
		$languages = $resolver->get()->languages();
		if ( ! empty( $attrs['hideCurrent'] ) ) {
			$languages = array_values( array_filter( $languages, static fn( $l ) => ! $l->isCurrent ) );
		}
		$style = 'dropdown' === ( $attrs['style'] ?? 'list' ) ? 'dropdown' : 'list';
		return array(
			'languages' => $languages,
			'style'     => $style,
			'show'      => count( $resolver->get()->languages() ) >= 2,
			'_class'    => 'joo-lang joo-lang--' . $style,
		);
	}
}
