<?php
/**
 * Block joo/featured-slider.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\DynamicBlock;
use JOOservices\ThemeCore\Content\ContentProviderInterface;

/**
 * Owner-picked posts in a scroll-snap track (works without JavaScript); the first image is the LCP candidate.
 *
 * @internal
 */
final class FeaturedSlider extends DynamicBlock {

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/featured-slider';
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
		/** @var ContentProviderInterface $provider */
		$provider = $this->container->get( ContentProviderInterface::class );
		$limit    = max( 1, min( 12, (int) ( $attrs['limit'] ?? 5 ) ) );
		$cards    = $provider->featured( $limit );
		return array(
			'cards'       => $cards,
			'count'       => count( $cards ),
			'autoplay'    => ( ! array_key_exists( 'autoplay', $attrs ) || (bool) $attrs['autoplay'] ) && count( $cards ) > 1,
			'interval'    => max( 3000, min( 15000, (int) ( $attrs['interval'] ?? 6500 ) ) ),
			'showMeta'    => ! array_key_exists( 'showMeta', $attrs ) || (bool) $attrs['showMeta'],
			'showExcerpt' => ! array_key_exists( 'showExcerpt', $attrs ) || (bool) $attrs['showExcerpt'],
			'_class'      => 'joo-slider',
			'_empty'      => array() === $cards,
		);
	}

	/**
	 * Hides the block when nothing is featured and on paginated pages.
	 *
	 * @param array<string, mixed> $attrs   Attributes.
	 * @param string               $content Content.
	 * @param \WP_Block            $block   Block.
	 */
	public function render( array $attrs, string $content, \WP_Block $block ): string {
		// Featured posts are shown once, on the first page of the list.
		if ( is_paged() ) {
			return '';
		}
		/** @var ContentProviderInterface $provider */
		$provider = $this->container->get( ContentProviderInterface::class );
		if ( array() === $provider->featured( 1 ) ) {
			return '';
		}
		return parent::render( $attrs, $content, $block );
	}
}
