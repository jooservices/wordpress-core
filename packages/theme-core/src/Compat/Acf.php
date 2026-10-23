<?php
/**
 * ACF helper.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat;

use JOOservices\ThemeCore\Support\Hooks;

/**
 * Reads an Advanced Custom Fields value when ACF is active; falls back to post meta.
 *
 * @internal
 */
final class Acf {

	/**
	 * Field value.
	 *
	 * @param string   $name   Field name.
	 * @param int|null $postId Post id (current post when null).
	 * @return mixed
	 */
	public static function field( string $name, ?int $postId = null ) {
		$postId = $postId ?? (int) get_the_ID();
		$value  = function_exists( 'get_field' ) ? get_field( $name, $postId ) : get_post_meta( $postId, $name, true );
		return Hooks::filter( 'joo/compat/acf_field', $value, $name, $postId );
	}
}
