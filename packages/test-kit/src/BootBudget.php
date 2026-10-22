<?php
/**
 * Load-aware boot budgets for timing tests.
 *
 * @package JOOservices\TestKit
 */

declare(strict_types=1);

namespace JOOservices\TestKit;

/**
 * Scales a fixed time budget by how slow the host is right now.
 *
 * A fixed CPU workload is timed in the same run as the boot samples and compared with its cost on an idle
 * host. A busy host stretches both, so the budget stretches with it; a boot that became slower relative to
 * the host still exceeds the scaled budget. The budget never shrinks below its nominal value.
 */
final class BootBudget {

	public const SPIN_SAMPLES      = 15;
	public const SPIN_ITERATIONS   = 200000;
	public const REFERENCE_SPIN_MS = 5.0;

	/**
	 * Returns the median of numeric samples.
	 *
	 * @param list<int|float> $samples Samples.
	 */
	public static function median( array $samples ): float {
		if ( array() === $samples ) {
			throw new \InvalidArgumentException( 'At least one sample is required.' );
		}
		sort( $samples, SORT_NUMERIC );
		$middle = intdiv( count( $samples ), 2 );

		return 0 === count( $samples ) % 2
			? ( (float) $samples[ $middle - 1 ] + (float) $samples[ $middle ] ) / 2
			: (float) $samples[ $middle ];
	}

	/**
	 * Returns how many times slower than an idle host the current host is (never below 1).
	 *
	 * @param float|null $spin_ms Measured median of the calibration workload; measured now when null.
	 */
	public static function hostFactor( ?float $spin_ms = null ): float {
		$spin_ms ??= self::measureSpin();

		return max( 1.0, $spin_ms / self::REFERENCE_SPIN_MS );
	}

	/**
	 * Scales a nominal budget (ms) by the host factor.
	 *
	 * @param float      $nominal_ms Budget on an idle host.
	 * @param float|null $spin_ms    Measured calibration median; measured now when null.
	 */
	public static function scale( float $nominal_ms, ?float $spin_ms = null ): float {
		return $nominal_ms * self::hostFactor( $spin_ms );
	}

	/**
	 * Times a fixed CPU workload and returns the median in milliseconds.
	 */
	public static function measureSpin(): float {
		$samples = array();
		for ( $i = 0; $i < self::SPIN_SAMPLES; ++$i ) {
			$start = hrtime( true );
			$sum   = 0;
			for ( $n = 0; $n < self::SPIN_ITERATIONS; ++$n ) {
				$sum ^= crc32( (string) $n );
			}
			$elapsed = ( hrtime( true ) - $start ) / 1e6;
			if ( $sum < 0 ) {
				throw new \LogicException( 'Calibration checksum overflow.' );
			}
			$samples[] = $elapsed;
		}

		return self::median( $samples );
	}
}
