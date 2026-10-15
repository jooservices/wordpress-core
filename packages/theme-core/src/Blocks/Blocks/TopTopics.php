<?php
/**
 * Block joo/top-topics.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\FragmentBlock;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Content\ContentProviderInterface;

/**
 * Topics (tags) with a small image, count and intro, by post count or in the owner's order.
 *
 * @internal
 */
final class TopTopics extends FragmentBlock {

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/top-topics';
	}

	/**
	 * Fragment name.
	 */
	public static function fragment(): string {
		return 'top-topics';
	}

	/**
	 * Fragment schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function schema(): array {
		return array(
			'limit'    => array(
				'type'    => 'int',
				'default' => 4,
				'min'     => 1,
				'max'     => 12,
			),
			'strategy' => array(
				'type'    => 'enum',
				'enum'    => array( 'count', 'manual' ),
				'default' => 'count',
			),
			'image'    => array(
				'type'    => 'bool',
				'default' => true,
			),
			'intro'    => array(
				'type'    => 'bool',
				'default' => true,
			),
			'title'    => array(
				'type'      => 'string',
				'maxLength' => 60,
			),
		);
	}

	/**
	 * Fragment arguments (`inherit` is resolved from the settings here, so the endpoint needs no context).
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return array<string, int|string|bool>
	 */
	protected function fragmentArgs( array $attrs ): array {
		/** @var Settings $settings */
		$settings = $this->container->get( Settings::class );
		$strategy = (string) ( $attrs['strategy'] ?? 'inherit' );
		if ( ! in_array( $strategy, array( 'count', 'manual' ), true ) ) {
			$strategy = 'manual' === (string) $settings->get( 'content.topics_strategy', 'count' ) ? 'manual' : 'count';
		}
		$limit = isset( $attrs['limit'] ) ? (int) $attrs['limit'] : (int) $settings->get( 'content.topics_limit', 4 );
		return array(
			'limit'    => max( 1, min( 12, $limit ) ),
			'strategy' => $strategy,
			'image'    => ! array_key_exists( 'showImage', $attrs ) || (bool) $attrs['showImage'],
			'intro'    => ! array_key_exists( 'showIntro', $attrs ) || (bool) $attrs['showIntro'],
			'title'    => (string) ( $attrs['title'] ?? '' ),
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
			'limit'     => (int) ( $args['limit'] ?? 4 ),
			'strategy'  => (string) ( $args['strategy'] ?? 'count' ),
			'showImage' => (bool) ( $args['image'] ?? true ),
			'showIntro' => (bool) ( $args['intro'] ?? true ),
			'title'     => (string) ( $args['title'] ?? '' ),
		);
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
		$args     = $this->fragmentArgs( $attrs );
		return array(
			'topics'    => $provider->topics( (int) $args['limit'], (string) $args['strategy'] ),
			'showImage' => (bool) $args['image'],
			'showIntro' => (bool) $args['intro'],
			'title'     => '' !== (string) $args['title'] ? (string) $args['title'] : __( 'Top topics', 'joo-core' ),
			'_class'    => 'joo-topics',
		);
	}
}
