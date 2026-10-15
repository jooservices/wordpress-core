<?php
/**
 * Locations of core files inside the theme.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Support;

/**
 * Core is copied (or symlinked) into the theme as <theme>/core; URLs are built from the theme URL so they
 * work with symlinks and child themes.
 *
 * @internal
 */
final class Paths {

	/**
	 * Absolute path of a file inside core.
	 *
	 * @param string $relative Path relative to the core root.
	 */
	public static function file( string $relative ): string {
		return rtrim( defined( 'JOO_CORE_DIR' ) ? (string) JOO_CORE_DIR : dirname( __DIR__, 2 ), '/' ) . '/' . ltrim( $relative, '/' );
	}

	/**
	 * Public URL of a file inside core.
	 *
	 * @param string $relative Path relative to the core root.
	 */
	public static function url( string $relative ): string {
		return trailingslashit( get_template_directory_uri() ) . 'core/' . ltrim( $relative, '/' );
	}

	/**
	 * Cache-busting version for a file inside core (its modification time).
	 *
	 * @param string $relative Path relative to the core root.
	 */
	public static function version( string $relative ): string {
		$file = self::file( $relative );
		return is_file( $file ) ? (string) filemtime( $file ) : Version::core();
	}
}
