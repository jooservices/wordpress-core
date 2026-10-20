<?php
/**
 * Fixture that models a deprecated public hook.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Hooks;

use JOOservices\ThemeCore\Support\Hooks;

final class DeprecatedHook {

	public const NAME        = 'joo/fixture/deprecated';
	public const SINCE       = '0.2.0';
	public const REPLACEMENT = 'joo/fixture/replacement';

	/**
	 * Registers the fixture replacement callback.
	 */
	public static function register(): void {
		add_filter( self::NAME, array( self::class, 'replacement' ) );
	}

	/**
	 * Models a legacy front-end filter that reports its deprecation before applying callbacks.
	 *
	 * @param string $value Filter input.
	 * @return string Filtered value.
	 */
	public static function apply( string $value ): string {
		Hooks::deprecated( self::NAME, self::SINCE, self::REPLACEMENT );
		return (string) apply_filters( self::NAME, $value );
	}

	/**
	 * Reports the deprecation while WordPress processes front-end content.
	 *
	 * @param string $content Post content.
	 * @return string Processed content.
	 */
	public static function filterContent( string $content ): string {
		return self::apply( $content );
	}

	/**
	 * Replacement behavior used to verify that deprecation reporting preserves filter results.
	 *
	 * @param string $value Filter input.
	 * @return string Filtered value.
	 */
	public static function replacement( string $value ): string {
		return $value . ' [fixture replacement]';
	}
}
