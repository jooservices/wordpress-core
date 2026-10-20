<?php
/**
 * Runtime requirements.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Support;

/**
 * Checks PHP, WordPress and extensions.
 *
 * @internal
 */
final class Requirements {

	/**
	 * Returns problems (empty when everything is fine).
	 *
	 * @param string|null       $php        PHP version (default: runtime).
	 * @param string|null       $wp         WordPress version (default: runtime).
	 * @param list<string>|null $extensions Loaded extensions (default: runtime).
	 * @return list<array{code: string, message: string}>
	 */
	public static function check( ?string $php = null, ?string $wp = null, ?array $extensions = null ): array {
		$php        = $php ?? PHP_VERSION;
		$wp         = $wp ?? self::wordpressVersion();
		$extensions = $extensions ?? get_loaded_extensions();
		$min_php    = defined( 'JOO_CORE_MIN_PHP' ) ? (string) JOO_CORE_MIN_PHP : '8.1';
		$min_wp     = defined( 'JOO_CORE_MIN_WP' ) ? (string) JOO_CORE_MIN_WP : '6.6';
		$problems   = array();

		if ( version_compare( $php, $min_php, '<' ) ) {
			$problems[] = array(
				'code'    => 'php',
				'message' => sprintf(
					/* translators: 1: minimum PHP version, 2: current PHP version. */
					__( 'JOOservices core needs PHP %1$s or newer (found %2$s).', 'joo-core' ),
					$min_php,
					$php
				),
			);
		}
		if ( '' !== $wp && version_compare( $wp, $min_wp, '<' ) ) {
			$problems[] = array(
				'code'    => 'wp',
				'message' => sprintf(
					/* translators: 1: minimum WordPress version, 2: current WordPress version. */
					__( 'JOOservices core needs WordPress %1$s or newer (found %2$s).', 'joo-core' ),
					$min_wp,
					$wp
				),
			);
		}
		foreach ( array( 'json', 'mbstring' ) as $extension ) {
			if ( ! in_array( $extension, array_map( 'strtolower', $extensions ), true ) ) {
				$problems[] = array(
					'code'    => 'ext-' . $extension,
					'message' => sprintf(
						/* translators: %s: PHP extension name. */
						__( 'The PHP extension "%s" is required by JOOservices core.', 'joo-core' ),
						$extension
					),
				);
			}
		}
		return $problems;
	}

	/**
	 * Runtime WordPress version.
	 */
	private static function wordpressVersion(): string {
		global $wp_version;
		return isset( $wp_version ) ? (string) $wp_version : '';
	}
}
