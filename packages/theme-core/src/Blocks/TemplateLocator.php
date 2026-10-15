<?php
/**
 * Block template lookup.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks;

/**
 * child theme parts/joo/{slug}.php, parent theme parts/joo/{slug}.php, then the block folder's render.php.
 *
 * @api
 */
final class TemplateLocator {

	/**
	 * Finds the template for a block slug.
	 *
	 * @param string $slug     Block slug (profile-card).
	 * @param string $blockDir Folder of the block (holds the default render.php).
	 * @return string Absolute path, or an empty string when nothing was found.
	 */
	public static function locate( string $slug, string $blockDir ): string {
		if ( 1 !== preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
			return '';
		}
		$candidates = array(
			trailingslashit( get_stylesheet_directory() ) . 'parts/joo/' . $slug . '.php',
			trailingslashit( get_template_directory() ) . 'parts/joo/' . $slug . '.php',
			trailingslashit( $blockDir ) . 'render.php',
		);
		foreach ( $candidates as $file ) {
			if ( is_readable( $file ) ) {
				return $file;
			}
		}
		return '';
	}
}
