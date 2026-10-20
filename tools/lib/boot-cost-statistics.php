<?php
/**
 * Statistics used by the core boot-cost measurement.
 *
 * @package JOOservices\Tools
 */

declare(strict_types=1);

namespace JOOservices\Tools;

use InvalidArgumentException;

/**
 * Summarizes boot-cost samples.
 */
final class BootCostStatistics {

	/**
	 * Returns the median of numeric samples.
	 *
	 * @param list<int|float> $samples Samples.
	 */
	public static function median( array $samples ): float {
		self::assertSamples( $samples );
		sort( $samples, SORT_NUMERIC );
		$middle = intdiv( count( $samples ), 2 );

		return 0 === count( $samples ) % 2
			? ( (float) $samples[ $middle - 1 ] + (float) $samples[ $middle ] ) / 2
			: (float) $samples[ $middle ];
	}

	/**
	 * Returns the largest peak-memory sample in bytes.
	 *
	 * @param list<int> $samples Peak-memory samples.
	 */
	public static function peak( array $samples ): int {
		self::assertSamples( $samples );

		return max( $samples );
	}

	/**
	 * Requires at least one sample.
	 *
	 * @param list<int|float> $samples Samples.
	 */
	private static function assertSamples( array $samples ): void {
		if ( array() === $samples ) {
			throw new InvalidArgumentException( 'At least one boot-cost sample is required.' );
		}
	}
}
