<?php
/**
 * Namespaced hook helpers.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Support;

/**
 * Every core hook is named joo/{module}/{event}; calling them through this class keeps names consistent and
 * lets the documentation generator find them.
 *
 * @api
 */
final class Hooks {

	/**
	 * Deprecated hooks already reported during this request.
	 *
	 * @var array<string, true>
	 */
	private static array $deprecatedHooks = array();

	/**
	 * Hook name pattern.
	 */
	private const NAME_PATTERN = '#^joo/[a-z0-9_]+(/[a-z0-9_-]+)+$#';

	/**
	 * Applies a filter.
	 *
	 * @param non-empty-string $hook  Hook name (joo/{module}/{event}).
	 * @param mixed  $value Filtered value.
	 * @param mixed  ...$args Extra arguments.
	 * @return mixed
	 */
	public static function filter( string $hook, $value, ...$args ) {
		self::assertName( $hook );
		return apply_filters( $hook, $value, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
	}

	/**
	 * Runs an action.
	 *
	 * @param non-empty-string $hook Hook name.
	 * @param mixed  ...$args Arguments.
	 */
	public static function action( string $hook, ...$args ): void {
		self::assertName( $hook );
		do_action( $hook, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
	}

	/**
	 * Logs a deprecation notice once per request in debug mode when a callback is attached.
	 *
	 * @param string $hook        Hook name.
	 * @param string $since       Version in which it was deprecated.
	 * @param string $replacement Replacement hook.
	 */
	public static function deprecated( string $hook, string $since, string $replacement = '' ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || isset( self::$deprecatedHooks[ $hook ] ) || ! has_filter( $hook ) ) {
			return;
		}

		self::$deprecatedHooks[ $hook ] = true;
		_deprecated_hook( $hook, $since, $replacement ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Whether a name follows the convention.
	 *
	 * @param string $hook Hook name.
	 */
	public static function isValidName( string $hook ): bool {
		return 1 === preg_match( self::NAME_PATTERN, $hook );
	}

	/**
	 * Throws for badly named hooks while debugging.
	 *
	 * @param string $hook Hook name.
	 * @throws \InvalidArgumentException In debug mode only.
	 */
	private static function assertName( string $hook ): void {
		if ( ! self::isValidName( $hook ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid core hook name "%s": use joo/{module}/{event}.', $hook ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}
}
