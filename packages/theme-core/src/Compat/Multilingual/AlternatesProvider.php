<?php
/**
 * Hreflang alternates from a multilingual plugin.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Multilingual;

use JOOservices\ThemeCore\Seo\Context;

/**
 * Feeds `joo/seo/alternates` when a plugin owns the languages.
 *
 * @internal
 */
final class AlternatesProvider {

	/**
	 * Resolver.
	 *
	 * @var Resolver
	 */
	private Resolver $resolver;

	/**
	 * Creates the provider.
	 *
	 * @param Resolver $resolver Resolver.
	 */
	public function __construct( Resolver $resolver ) {
		$this->resolver = $resolver;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_filter( 'joo/seo/alternates', array( $this, 'alternates' ), 5, 2 );
	}

	/**
	 * Filter callback: code => URL map, or the incoming value when no plugin is in charge.
	 *
	 * @param mixed   $current Value from earlier filters.
	 * @param Context $context Context.
	 * @return mixed
	 */
	public function alternates( $current, $context = null ) {
		if ( null !== $current || ! $this->resolver->isPlugin() ) {
			return $current;
		}
		$impl = $this->resolver->get();
		$map  = array();
		$post = $context instanceof Context ? $context->post() : null;
		if ( null !== $post ) {
			foreach ( $impl->translations( $post->ID ) as $code => $id ) {
				$link = 'publish' === get_post_status( $id ) ? get_permalink( $id ) : false;
				if ( is_string( $link ) ) {
					$map[ $code ] = $link;
				}
			}
		}
		if ( array() === $map ) {
			foreach ( $impl->languages() as $lang ) {
				if ( '' !== $lang->url ) {
					$map[ $lang->code ] = $lang->url;
				}
			}
		}
		return $map;
	}
}
