<?php
/**
 * Block joo/breadcrumbs.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\DynamicBlock;
use JOOservices\ThemeCore\Seo\Breadcrumbs\TrailBuilder;
use JOOservices\ThemeCore\Seo\HeadPrinter;

/**
 * The same trail as the BreadcrumbList structured data.
 *
 * @internal
 */
final class Breadcrumbs extends DynamicBlock {

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/breadcrumbs';
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
		/** @var TrailBuilder $trails */
		$trails = $this->container->get( TrailBuilder::class );
		/** @var HeadPrinter $printer */
		$printer = $this->container->get( HeadPrinter::class );
		$context = $printer->context();
		$trail   = $trails->build( $context );
		$show    = ! array_key_exists( 'showHome', $attrs ) || (bool) $attrs['showHome'];
		if ( ! $show ) {
			array_shift( $trail );
		}
		$max     = max( 20, min( 80, (int) ( $attrs['maxLabel'] ?? 50 ) ) );
		$display = $trails->forDisplay( $trail, $context );
		$items   = array();
		foreach ( $display as $i => $crumb ) {
			$full    = $trail[ $i ]->label ?? $crumb->label;
			$items[] = array(
				'label' => \JOOservices\ThemeCore\Seo\Text::truncate( $crumb->label, $max ),
				'title' => $full,
				'url'   => $crumb->url,
			);
		}
		$separator = in_array( $attrs['separator'] ?? 'slash', array( 'slash', 'chevron' ), true ) ? (string) ( $attrs['separator'] ?? 'slash' ) : 'slash';
		return array(
			'items'  => $items,
			'_class' => 'joo-breadcrumbs joo-breadcrumbs--' . $separator,
		);
	}
}
