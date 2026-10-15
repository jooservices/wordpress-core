<?php
/**
 * Image and iframe loading attributes.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Lazy;

/**
 * Applies the lazy policy through WordPress' own loading API (6.3+), so core keeps computing fetchpriority and
 * we only override the lazy/eager decision.
 *
 * @internal
 */
final class ImageLazy {

	/**
	 * Policy.
	 *
	 * @var Policy
	 */
	private Policy $policy;

	/**
	 * Number of images evaluated in this request.
	 *
	 * @var int
	 */
	private int $index = 0;

	/**
	 * Creates the service.
	 *
	 * @param Policy $policy Policy.
	 */
	public function __construct( Policy $policy ) {
		$this->policy = $policy;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_filter( 'wp_get_loading_optimization_attributes', array( $this, 'attributes' ), 20, 4 );
		add_filter( 'wp_iframe_tag_add_loading_attr', array( $this, 'iframe' ), 20, 3 );
	}

	/**
	 * Filter for wp_get_loading_optimization_attributes.
	 *
	 * @param array<string, string|true> $attrs   Computed attributes.
	 * @param string                     $tag     Tag name.
	 * @param array<string, mixed>       $attr    Tag attributes.
	 * @param string                     $context Context.
	 * @return array<string, string|true>
	 */
	public function attributes( array $attrs, string $tag, array $attr, string $context ): array {
		unset( $context );
		if ( 'img' !== $tag ) {
			return $attrs;
		}
		// WordPress marks the likely LCP image high priority; such an image must never be lazy (core warns about it).
		$lcp      = ( isset( $attr['fetchpriority'] ) && 'high' === $attr['fetchpriority'] ) || 'high' === ( $attrs['fetchpriority'] ?? '' );
		$decision = $this->policy->decide(
			'image',
			array(
				'index' => $this->index++,
				'lcp'   => $lcp,
			)
		);
		if ( $decision->lazy ) {
			if ( ! isset( $attrs['loading'] ) ) {
				$attrs['loading'] = 'lazy';
			}
			return $attrs;
		}
		if ( 'lazy' === ( $attrs['loading'] ?? '' ) ) {
			unset( $attrs['loading'] );
		}
		return $attrs;
	}

	/**
	 * Filter for wp_iframe_tag_add_loading_attr.
	 *
	 * @param string|bool $value   Loading value or false.
	 * @param string      $iframe  Iframe tag.
	 * @param string      $context Context.
	 * @return string|bool
	 */
	public function iframe( $value, string $iframe, string $context ) {
		unset( $iframe, $context );
		return $this->policy->decide( 'iframe' )->lazy ? 'lazy' : false;
	}
}
