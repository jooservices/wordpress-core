<?php
/**
 * Block joo/related-posts.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\DynamicBlock;
use JOOservices\ThemeCore\Content\ContentProviderInterface;
use JOOservices\ThemeCore\Lazy\FragmentLazy;

/**
 * Related post cards, deferred as a `<joo-lazy>` fragment when the lazy policy allows it.
 *
 * @internal
 */
final class RelatedPosts extends DynamicBlock {

	public const FRAGMENT = 'related';

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/related-posts';
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
		/** @var ContentProviderInterface $provider */
		$provider = $this->container->get( ContentProviderInterface::class );
		$cards    = $postId > 0 ? $provider->related( $postId, max( 1, min( 8, (int) ( $attrs['limit'] ?? 3 ) ) ) ) : array();
		return array(
			'cards'       => $cards,
			'showImage'   => ! array_key_exists( 'showImage', $attrs ) || (bool) $attrs['showImage'],
			'showExcerpt' => ! empty( $attrs['showExcerpt'] ),
			'_class'      => 'joo-related',
		);
	}

	/**
	 * Fragment renderer (same HTML inline and over REST).
	 *
	 * @param int  $postId      Post id.
	 * @param int  $limit       Limit.
	 * @param bool $showImage   Show images.
	 * @param bool $showExcerpt Show excerpts.
	 */
	public function html( int $postId, int $limit, bool $showImage, bool $showExcerpt ): string {
		$block = new \WP_Block(
			array(
				'blockName' => self::name(),
				'attrs'     => array(),
			),
			array( 'postId' => $postId )
		);
		return parent::render(
			array(
				'limit'       => $limit,
				'showImage'   => $showImage,
				'showExcerpt' => $showExcerpt,
			),
			'',
			$block
		);
	}

	/**
	 * Render: lazy fragment around the cards when deferral is allowed.
	 *
	 * @param array<string, mixed> $attrs   Attributes.
	 * @param string               $content Content.
	 * @param \WP_Block            $block   Block.
	 */
	public function render( array $attrs, string $content, \WP_Block $block ): string {
		$postId = (int) ( $block->context['postId'] ?? get_the_ID() );
		if ( $postId < 1 ) {
			return '';
		}
		$limit       = max( 1, min( 8, (int) ( $attrs['limit'] ?? 3 ) ) );
		$showImage   = ! array_key_exists( 'showImage', $attrs ) || (bool) $attrs['showImage'];
		$showExcerpt = ! empty( $attrs['showExcerpt'] );
		/** @var FragmentLazy $fragments */
		$fragments = $this->container->get( FragmentLazy::class );
		return $fragments->wrap(
			self::FRAGMENT,
			array(
				'post'    => $postId,
				'limit'   => $limit,
				'image'   => $showImage,
				'excerpt' => $showExcerpt,
			),
			fn(): string => $this->html( $postId, $limit, $showImage, $showExcerpt )
		);
	}
}
