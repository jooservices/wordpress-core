<?php
/**
 * Form plugin helpers.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat;

/**
 * Detects forms in content so their assets can be loaded only where needed.
 *
 * @internal
 */
final class Forms {

	/**
	 * Shortcodes and block names that render a form.
	 */
	private const SIGNALS = array(
		'[contact-form-7',
		'wp:contact-form-7/',
		'[wpforms',
		'wp:wpforms/',
		'[fluentform',

		'wp:fluentform/',
	);

	/**
	 * Whether content contains a form.
	 *
	 * @param string $content Post content.
	 */
	public static function contentHasForm( string $content ): bool {
		foreach ( self::SIGNALS as $signal ) {
			if ( false !== strpos( $content, $signal ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether the current request needs form assets.
	 */
	public static function pageNeedsForm(): bool {
		$post = get_post();
		if ( $post instanceof \WP_Post && self::contentHasForm( $post->post_content ) ) {
			return true;
		}
		return (bool) \JOOservices\ThemeCore\Support\Hooks::filter( 'joo/compat/forms_needed', false );
	}
}
