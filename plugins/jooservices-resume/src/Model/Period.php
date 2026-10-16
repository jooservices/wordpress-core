<?php
/**
 * Year-month periods of a company or a role.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Model;

/**
 * Pure helpers over `YYYY-MM` strings (no WordPress calls, unit-tested).
 */
final class Period {

	/**
	 * Whether the value is a `YYYY-MM` month.
	 *
	 * @param mixed $value Value.
	 */
	public static function isMonth( $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $value );
	}

	/**
	 * Months since year 0 (a comparable integer), or -1 for an invalid month.
	 *
	 * @param string $month `YYYY-MM`.
	 */
	public static function index( string $month ): int {
		return self::isMonth( $month ) ? (int) substr( $month, 0, 4 ) * 12 + (int) substr( $month, 5, 2 ) - 1 : -1;
	}

	/**
	 * Length in whole months, both end months included. An empty end means "until now".
	 *
	 * @param string $from `YYYY-MM`.
	 * @param string $to   `YYYY-MM` or empty (current).
	 * @param string $now  Current month `YYYY-MM`.
	 */
	public static function months( string $from, string $to, string $now ): int {
		$start = self::index( $from );
		$end   = self::index( '' === $to ? $now : $to );
		if ( $start < 0 || $end < 0 || $end < $start ) {
			return 0;
		}
		return $end - $start + 1;
	}

	/**
	 * Years and months of a month count.
	 *
	 * @param int $months Months.
	 * @return array{years: int, months: int}
	 */
	public static function parts( int $months ): array {
		$months = max( 0, $months );
		return array(
			'years'  => intdiv( $months, 12 ),
			'months' => $months % 12,
		);
	}

	/**
	 * Whether the inner period lies inside the outer one. An empty outer end is open; an empty inner end needs an open outer end.
	 *
	 * @param string $innerFrom Inner start.
	 * @param string $innerTo   Inner end (empty = current).
	 * @param string $outerFrom Outer start.
	 * @param string $outerTo   Outer end (empty = current).
	 */
	public static function inside( string $innerFrom, string $innerTo, string $outerFrom, string $outerTo ): bool {
		if ( '' !== $outerFrom && ( '' === $innerFrom || strcmp( $innerFrom, $outerFrom ) < 0 ) ) {
			return false;
		}
		if ( '' === $outerTo ) {
			return true;
		}
		return '' !== $innerTo && strcmp( $innerTo, $outerTo ) <= 0;
	}

	/**
	 * Whether two periods share at least one month (an empty end is open).
	 *
	 * @param string $aFrom A start.
	 * @param string $aTo   A end.
	 * @param string $bFrom B start.
	 * @param string $bTo   B end.
	 */
	public static function overlaps( string $aFrom, string $aTo, string $bFrom, string $bTo ): bool {
		$endA = '' === $aTo ? '9999-12' : $aTo;
		$endB = '' === $bTo ? '9999-12' : $bTo;
		return strcmp( $aFrom, $endB ) <= 0 && strcmp( $bFrom, $endA ) <= 0;
	}
}
