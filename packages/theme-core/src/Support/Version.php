<?php
/**
 * Version helpers.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Support;

/**
 * Placeholder replaced by the real implementation in story C2.
 *
 * @internal
 */
final class Version {

	/**
	 * Core version string.
	 */
	public static function core(): string {
		return defined( 'JOO_CORE_VERSION' ) ? (string) JOO_CORE_VERSION : '0.0.0';
	}
}
