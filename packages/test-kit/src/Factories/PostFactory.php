<?php
/**
 * Post factory with theme presets.
 *
 * @package JOOservices\TestKit
 */

declare(strict_types=1);

namespace JOOservices\TestKit\Factories;

/**
 * Wraps WP_UnitTestCase factories. Example: `PostFactory::create( array( 'title' => 'Hi' ), array( 'featured' => 2, 'lang' => 'vi' ) )`.
 */
final class PostFactory {

	/**
	 * Creates a post.
	 *
	 * Presets: featured (int order), lang (code), translationOf (post id), categories (list of term ids),
	 * tags (list of names), image (array{width, height}), primaryCategory (term id), noindex (bool).
	 *
	 * @param array<string, mixed> $args    wp_insert_post arguments (a short `title` key is accepted).
	 * @param array<string, mixed> $presets Presets.
	 * @return int Post id.
	 */
	public static function create( array $args = array(), array $presets = array() ): int {
		if ( isset( $args['title'] ) ) {
			$args['post_title'] = $args['title'];
			unset( $args['title'] );
		}
		if ( isset( $presets['categories'] ) ) {
			$args['post_category'] = array_map( 'intval', (array) $presets['categories'] );
		}
		$id = (int) ( new \WP_UnitTest_Factory() )->post->create( $args );
		if ( isset( $presets['tags'] ) ) {
			wp_set_post_tags( $id, (array) $presets['tags'] );
		}
		if ( isset( $presets['featured'] ) ) {
			update_post_meta( $id, '_joo_featured', true );
			update_post_meta( $id, '_joo_featured_order', (int) $presets['featured'] );
		}
		if ( isset( $presets['lang'] ) ) {
			update_post_meta( $id, '_joo_lang', (string) $presets['lang'] );
		}
		if ( isset( $presets['translationOf'] ) ) {
			update_post_meta( $id, '_joo_translation_of', (int) $presets['translationOf'] );
		}
		if ( isset( $presets['primaryCategory'] ) ) {
			update_post_meta( $id, '_joo_primary_category', (int) $presets['primaryCategory'] );
		}
		if ( ! empty( $presets['noindex'] ) ) {
			update_post_meta( $id, '_joo_seo_noindex', true );
		}
		if ( isset( $presets['image'] ) ) {
			$size = (array) $presets['image'];
			set_post_thumbnail( $id, AttachmentFactory::image( (int) ( $size['width'] ?? 1200 ), (int) ( $size['height'] ?? 630 ) ) );
		}
		return $id;
	}

	/**
	 * Creates a post in another language linked to an original.
	 *
	 * @param int                  $original Original post id.
	 * @param string               $lang     Language of the translation.
	 * @param array<string, mixed> $args     Post arguments.
	 */
	public static function translationOf( int $original, string $lang, array $args = array() ): int {
		return self::create(
			$args,
			array(
				'lang'          => $lang,
				'translationOf' => $original,
			)
		);
	}
}
