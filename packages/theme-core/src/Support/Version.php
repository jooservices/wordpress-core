<?php
/**
 * Semantic version helpers.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Support;

/**
 * Parses and compares Composer-style version ranges (^, ~, >=, >, <=, <, =, x/* wildcards, space = AND, || = OR).
 *
 * @internal
 */
final class Version {

	/**
	 * Core version.
	 */
	public static function core(): string {
		return defined( 'JOO_CORE_VERSION' ) ? (string) JOO_CORE_VERSION : '0.0.0';
	}

	/**
	 * Whether a version satisfies a range.
	 *
	 * @param string $version Version such as 1.2.3.
	 * @param string $range   Range such as ^1.2 || >=2.0 <3.0.
	 */
	public static function satisfies( string $version, string $range ): bool {
		$current = self::parse( $version );
		if ( null === $current ) {
			return false;
		}
		foreach ( explode( '||', $range ) as $alternative ) {
			$tokens = preg_split( '/\s+/', trim( $alternative ), -1, PREG_SPLIT_NO_EMPTY );
			if ( false === $tokens || array() === $tokens ) {
				continue;
			}
			$all = true;
			foreach ( $tokens as $token ) {
				if ( ! self::matches( $current, $token ) ) {
					$all = false;
					break;
				}
			}
			if ( $all ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Parses major.minor.patch with optional missing parts (null) and wildcards.
	 *
	 * @param string $version Version text.
	 * @return array{0: int, 1: int|null, 2: int|null}|null
	 */
	private static function parse( string $version ): ?array {
		if ( 1 !== preg_match( '/^v?(\d+)(?:\.(\d+|[x*]))?(?:\.(\d+|[x*]))?(?:[-+].*)?$/i', trim( $version ), $m ) ) {
			return null;
		}
		$part = static fn( $value ) => ( null === $value || '' === $value || 'x' === strtolower( (string) $value ) || '*' === $value ) ? null : (int) $value;
		return array( (int) $m[1], $part( $m[2] ?? null ), $part( $m[3] ?? null ) );
	}

	/**
	 * Matches one comparator token.
	 *
	 * @param array{0: int, 1: int|null, 2: int|null} $current Parsed current version.
	 * @param string                                  $token   Comparator token.
	 */
	private static function matches( array $current, string $token ): bool {
		if ( '*' === $token || 'x' === strtolower( $token ) ) {
			return true;
		}
		if ( 1 !== preg_match( '/^(\^|~|>=|<=|>|<|=)?(.+)$/', $token, $m ) ) {
			return false;
		}
		$operator = '' === $m[1] ? '=' : $m[1];
		$target   = self::parse( $m[2] );
		if ( null === $target ) {
			return false;
		}
		$cur = self::tuple( $current );
		$min = self::tuple( $target );
		switch ( $operator ) {
			case '^':
				return self::cmp( $cur, $min ) >= 0 && self::cmp( $cur, self::caretCeiling( $target ) ) < 0;
			case '~':
				return self::cmp( $cur, $min ) >= 0 && self::cmp( $cur, self::tildeCeiling( $target ) ) < 0;
			case '>=':
				return self::cmp( $cur, $min ) >= 0;
			case '<=':
				return self::cmp( $cur, self::upperInclusive( $target ) ) < 0;
			case '>':
				return self::cmp( $cur, self::upperInclusive( $target ) ) >= 0;
			case '<':
				return self::cmp( $cur, $min ) < 0;
			default:
				// Exact or wildcard: 1.2.x matches any 1.2.n.
				return self::cmp( $cur, $min ) >= 0 && self::cmp( $cur, self::wildcardCeiling( $target ) ) < 0;
		}
	}

	/**
	 * Parsed version to a comparable triple with zeros for missing parts.
	 *
	 * @param array{0: int, 1: int|null, 2: int|null} $v Parsed version.
	 * @return array{0: int, 1: int, 2: int}
	 */
	private static function tuple( array $v ): array {
		return array( $v[0], $v[1] ?? 0, $v[2] ?? 0 );
	}

	/**
	 * Compares two triples.
	 *
	 * @param array{0: int, 1: int, 2: int} $a First.
	 * @param array{0: int, 1: int, 2: int} $b Second.
	 */
	private static function cmp( array $a, array $b ): int {
		return $a <=> $b;
	}

	/**
	 * First version excluded by a caret range (^1.2 => 2.0.0, ^0.1 => 0.2.0, ^0.0.3 => 0.0.4).
	 *
	 * @param array{0: int, 1: int|null, 2: int|null} $v Parsed version.
	 * @return array{0: int, 1: int, 2: int}
	 */
	private static function caretCeiling( array $v ): array {
		if ( $v[0] > 0 ) {
			return array( $v[0] + 1, 0, 0 );
		}
		if ( null === $v[1] ) {
			return array( 1, 0, 0 );
		}
		if ( $v[1] > 0 || null === $v[2] ) {
			return array( 0, $v[1] + 1, 0 );
		}
		return array( 0, 0, $v[2] + 1 );
	}

	/**
	 * First version excluded by a tilde range (~1.2.3 => 1.3.0, ~1 => 2.0.0).
	 *
	 * @param array{0: int, 1: int|null, 2: int|null} $v Parsed version.
	 * @return array{0: int, 1: int, 2: int}
	 */
	private static function tildeCeiling( array $v ): array {
		if ( null === $v[1] ) {
			return array( $v[0] + 1, 0, 0 );
		}
		return array( $v[0], $v[1] + 1, 0 );
	}

	/**
	 * First version excluded by an exact or wildcard match.
	 *
	 * @param array{0: int, 1: int|null, 2: int|null} $v Parsed version.
	 * @return array{0: int, 1: int, 2: int}
	 */
	private static function wildcardCeiling( array $v ): array {
		if ( null === $v[1] ) {
			return array( $v[0] + 1, 0, 0 );
		}
		if ( null === $v[2] ) {
			return array( $v[0], $v[1] + 1, 0 );
		}
		return array( $v[0], $v[1], $v[2] + 1 );
	}

	/**
	 * First version after everything matching a partial version (used by > and <=).
	 *
	 * @param array{0: int, 1: int|null, 2: int|null} $v Parsed version.
	 * @return array{0: int, 1: int, 2: int}
	 */
	private static function upperInclusive( array $v ): array {
		return self::wildcardCeiling( $v );
	}
}
