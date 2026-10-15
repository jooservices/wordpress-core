<?php
/**
 * Language alternates.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo;

use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * Builds hreflang alternates from a multilingual adapter (filter `joo/seo/alternates`) or the built-in
 * `_joo_lang` / `_joo_translation_of` tagging.
 *
 * @api
 */
final class Hreflang {

	/**
	 * Manifest.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Creates the service.
	 *
	 * @param Manifest $manifest Manifest.
	 */
	public function __construct( Manifest $manifest ) {
		$this->manifest = $manifest;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_filter( 'update_post_metadata', array( $this, 'guardTranslationOf' ), 10, 4 );
	}

	/**
	 * Alternates as language => URL (empty unless two languages exist).
	 *
	 * @param Context $context Context.
	 * @return array<string, string>
	 */
	public function alternates( Context $context ): array {
		$adapter = Hooks::filter( 'joo/seo/alternates', null, $context );
		$map     = is_array( $adapter ) ? $adapter : $this->builtin( $context );
		$clean   = array();
		foreach ( $map as $lang => $url ) {
			if ( is_string( $url ) && '' !== $url ) {
				$clean[ Context::normaliseLanguage( (string) $lang ) ] = $url;
			}
		}
		if ( count( $clean ) < 2 ) {
			return array();
		}
		$default            = Context::normaliseLanguage( (string) $this->manifest->get( 'i18n.default', 'en' ) );
		$xdefault           = $clean[ $default ] ?? reset( $clean );
		$filtered           = Hooks::filter( 'joo/seo/x_default', $xdefault, $clean );
		$clean['x-default'] = is_string( $filtered ) && '' !== $filtered ? $filtered : (string) $xdefault;
		return $clean;
	}

	/**
	 * Built-in grouping by `_joo_translation_of`.
	 *
	 * @param Context $context Context.
	 * @return array<string, string>
	 */
	private function builtin( Context $context ): array {
		$post = $context->post();
		if ( null === $post || $context->isRestricted() ) {
			return array();
		}
		$source = (int) get_post_meta( $post->ID, '_joo_translation_of', true );
		$root   = $source > 0 ? $source : $post->ID;
		$ids    = get_posts(
			array(
				'post_type'        => $post->post_type,
				'post_status'      => 'publish',
				'posts_per_page'   => 20,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'       => array(
					array(
						'key'   => '_joo_translation_of',
						'value' => (string) $root,
					),
				),
			)
		);
		$ids    = array_unique( array_merge( array( $root ), array_map( 'intval', $ids ), array( $post->ID ) ) );
		$map    = array();
		foreach ( $ids as $id ) {
			if ( 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			$lang = (string) get_post_meta( $id, '_joo_lang', true );
			if ( '' === $lang ) {
				continue;
			}
			$link = get_permalink( $id );
			if ( is_string( $link ) ) {
				$map[ Context::normaliseLanguage( $lang ) ] = $link;
			}
		}
		return $map;
	}

	/**
	 * Refuses `_joo_translation_of` values that point to the post itself, to a missing post or to a post that is itself a translation.
	 *
	 * @param mixed  $check    Short-circuit value.
	 * @param int    $post_id  Post id.
	 * @param string $key      Meta key.
	 * @param mixed  $value    New value.
	 * @return mixed
	 */
	public function guardTranslationOf( $check, $post_id, $key, $value ) {
		if ( '_joo_translation_of' !== $key ) {
			return $check;
		}
		$target = (int) $value;
		if ( 0 === $target ) {
			return $check;
		}
		$source = get_post( $target );
		if ( $target === (int) $post_id || null === $source || (int) get_post_meta( $target, '_joo_translation_of', true ) > 0 ) {
			return false;
		}
		if ( get_post_type( (int) $post_id ) !== $source->post_type ) {
			return false;
		}
		$mine   = (string) get_post_meta( (int) $post_id, '_joo_lang', true );
		$theirs = (string) get_post_meta( $target, '_joo_lang', true );
		if ( '' !== $mine && $mine === $theirs ) {
			return false;
		}
		return $check;
	}
}
