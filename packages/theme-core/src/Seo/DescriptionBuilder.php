<?php
/**
 * Meta description builder.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo;

use JOOservices\ThemeCore\Support\Hooks;

/**
 * Override, excerpt, content, term description, author bio, tagline: first usable wins, never empty.
 *
 * @internal
 */
final class DescriptionBuilder {

	private const TARGET = 155;
	private const MAX    = 300;

	/**
	 * Builds the description.
	 *
	 * @param Context $context Context.
	 */
	public function build( Context $context ): string {
		$text = $this->candidate( $context );
		if ( '' === $text ) {
			$text = Text::plain( get_bloginfo( 'description' ) );
		}
		if ( '' === $text ) {
			$text = Text::plain( get_bloginfo( 'name' ) );
		}
		$text = Text::truncate( $text, self::MAX );
		$text = (string) Hooks::filter( 'joo/seo/description', $text, $context );
		return $text;
	}

	/**
	 * First usable candidate for a context.
	 *
	 * @param Context $context Context.
	 */
	private function candidate( Context $context ): string {
		$object = $context->object();
		if ( $object instanceof \WP_Post ) {
			if ( $context->isRestricted() ) {
				return '';
			}
			$override = Text::plain( (string) get_post_meta( $object->ID, '_joo_seo_description', true ) );
			if ( '' !== $override ) {
				return $override;
			}
			$excerpt = Text::plain( $object->post_excerpt );
			if ( '' !== $excerpt ) {
				return Text::summary( $excerpt, self::TARGET );
			}
			return Text::summary( Text::plain( $object->post_content ), self::TARGET );
		}
		if ( $object instanceof \WP_Term ) {
			$short = Text::plain( (string) get_term_meta( $object->term_id, 'joo_short_description', true ) );
			return '' !== $short ? $short : Text::summary( Text::plain( $object->description ), self::TARGET );
		}
		if ( $object instanceof \WP_User ) {
			return Text::summary( Text::plain( (string) get_the_author_meta( 'description', $object->ID ) ), self::TARGET );
		}
		return '';
	}
}
