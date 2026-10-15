<?php
/**
 * Page builder detection per post.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat;

/**
 * Pages built with Elementor, Beaver Builder, Divi or Bricks keep their own markup: content filters skip them.
 *
 * @api
 */
final class PageBuilder {

	/**
	 * Post meta keys that mark a builder page: key => expected value (null = any non-empty).
	 */
	private const META = array(
		'_elementor_edit_mode'   => 'builder',
		'_fl_builder_enabled'    => '1',
		'_et_pb_use_builder'     => 'on',
		'_bricks_page_content_2' => null,
	);

	/**
	 * Whether a post was built with a page builder.
	 *
	 * @param int $postId Post id.
	 */
	public static function isBuilderPage( int $postId ): bool {
		foreach ( self::META as $key => $expected ) {
			$value = get_post_meta( $postId, $key, true );
			if ( null === $expected ? ! empty( $value ) : (string) $value === $expected ) {
				return true;
			}
		}
		return false;
	}
}
