<?php
/**
 * Base of blocks that can be deferred as fragments.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks;

use JOOservices\ThemeCore\Lazy\FragmentLazy;

/**
 * A block whose HTML is identical inline and over REST (`/joo/v1/fragment/{name}`): secondary content such as the
 * left-rail blocks. When the lazy policy allows it the page carries a `<joo-lazy>` placeholder with the inline HTML
 * in `<noscript>`.
 *
 * @internal
 */
abstract class FragmentBlock extends DynamicBlock {

	/**
	 * Fragment name (`companies`).
	 */
	abstract public static function fragment(): string;

	/**
	 * Fragment arguments (scalars only) for the given block attributes.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return array<string, int|string|bool>
	 */
	abstract protected function fragmentArgs( array $attrs ): array;

	/**
	 * Block attributes rebuilt from validated fragment arguments.
	 *
	 * @param array<string, mixed> $args Fragment arguments.
	 * @return array<string, mixed>
	 */
	abstract protected function attrsFromArgs( array $args ): array;

	/**
	 * Fragment argument schema (see FragmentLazy::register).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	abstract public static function schema(): array;

	/**
	 * Reserved height in pixels (prevents layout shift while the fragment loads).
	 */
	public static function height(): int {
		return 240;
	}

	/**
	 * HTML for the fragment endpoint (same function as the inline path).
	 *
	 * @param array<string, mixed> $args Validated arguments.
	 */
	public function html( array $args ): string {
		return parent::render(
			$this->attrsFromArgs( $args ),
			'',
			new \WP_Block(
				array(
					'blockName' => static::name(),
					'attrs'     => array(),
				)
			)
		);
	}

	/**
	 * Render: deferred when allowed, inline otherwise.
	 *
	 * @param array<string, mixed> $attrs   Attributes.
	 * @param string               $content Content.
	 * @param \WP_Block            $block   Block.
	 */
	public function render( array $attrs, string $content, \WP_Block $block ): string {
		unset( $content );
		$args = $this->fragmentArgs( $attrs );
		/** @var FragmentLazy $fragments */
		$fragments = $this->container->get( FragmentLazy::class );
		return $fragments->wrap( static::fragment(), $args, fn(): string => parent::render( $this->attrsFromArgs( $args ), '', $block ) );
	}
}
