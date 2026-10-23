<?php
/**
 * Load-aware boot budgets for timing tests.
 *
 * @package JOOservices\TestKit
 */

declare(strict_types=1);

namespace JOOservices\TestKit;

/**
 * Judges boot timings against a CPU workload timed in the same run.
 *
 * A fixed workload (the "spin") is timed right before every boot sample. A busy host stretches boots and
 * spins alike, so the boot-to-spin ratio stays put, while a boot that became slower on its own raises it.
 * The ratio is in "units": one unit is the cost of one spin, tuned to about the 3 ms boot target of the spec
 * on a reference host. The idle baseline is never a constant: it is the fastest spin seen in the run.
 */
final class BootBudget {

	public const SPIN_ITERATIONS = 60000;

	/**
	 * Median-boot ceiling in units. The real Kernel boot measures about 0.3 units, so the ceiling sits at about
	 * 1.8x that: the real boot passes with room for scheduler noise and a doubled boot (about 0.6) fails.
	 */
	public const MAX_BOOT_UNITS = 0.55;

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
	 * Times one run of the fixed CPU workload (or a fraction of it) and returns the milliseconds.
	 *
	 * @param int $iterations Workload size; the default is one unit.
	 */
	public static function spin( int $iterations = self::SPIN_ITERATIONS ): float {
		$start = hrtime( true );
		$sum   = 0;
		for ( $n = 0; $n < $iterations; ++$n ) {
			$sum ^= crc32( (string) $n );
		}
		if ( $sum < 0 ) {
			throw new \LogicException( 'Calibration checksum overflow.' );
		}

		return ( hrtime( true ) - $start ) / 1e6;
	}

	/**
	 * Returns how much slower than its own fastest spin the host ran during the samples (never below 1).
	 *
	 * @param list<float> $spins Spin timings (ms) taken around the boots.
	 */
	public static function hostFactor( array $spins ): float {
		return max( 1.0, self::median( $spins ) / max( min( $spins ), 1e-6 ) );
	}

	/**
	 * Scales a nominal per-item budget (ms) by the host factor of the run.
	 *
	 * @param float       $nominal_ms Budget on an idle host.
	 * @param list<float> $spins      Spin timings (ms) taken around the boots.
	 */
	public static function scale( float $nominal_ms, array $spins ): float {
		return $nominal_ms * self::hostFactor( $spins );
	}

	/**
	 * Returns the typical boot cost in units of the typical spin taken in the same run.
	 *
	 * "Typical" is the median of the fastest third of the samples: a preempted sample on an oversubscribed host
	 * only ever adds time, so the slower samples carry scheduler noise, not boot cost. A boot that really got
	 * slower shifts the whole distribution and still moves this figure.
	 *
	 * @param list<float> $boots Boot timings (ms).
	 * @param list<float> $spins Spin timings (ms) taken right before each boot.
	 */
	public static function units( array $boots, array $spins ): float {
		return self::fastestThirdMedian( $boots ) / max( self::fastestThirdMedian( $spins ), 1e-6 );
	}

	/**
	 * Returns the median of the fastest third of the samples.
	 *
	 * @param list<float> $samples Timings (ms).
	 */
	private static function fastestThirdMedian( array $samples ): float {
		sort( $samples, SORT_NUMERIC );

		return self::median( array_slice( $samples, 0, max( 1, intdiv( count( $samples ) + 2, 3 ) ) ) );
	}

	/**
	 * Tells whether sample $index takes its spin before the boot (even) or after it (odd).
	 *
	 * Alternating the order cancels the scheduler bias against whichever of the two runs second on an
	 * oversubscribed host, where the first one has already used up its time slice.
	 *
	 * @param int $index Sample index.
	 */
	public static function spinsFirst( int $index ): bool {
		return 0 === $index % 2;
	}

	/**
	 * Times a boot callback with a spin next to every sample, alternating the order.
	 *
	 * @param callable $boot    Boot to time; may return its own cost in ms (float) instead of being timed.
	 * @param int      $samples Number of samples.
	 * @return array{boots: list<float>, spins: list<float>}
	 */
	public static function measure( callable $boot, int $samples ): array {
		$result = array(
			'boots' => array(),
			'spins' => array(),
		);
		for ( $i = 0; $i < $samples; ++$i ) {
			$spin_first = self::spinsFirst( $i );
			if ( $spin_first ) {
				$result['spins'][] = self::spin();
			}
			$start             = hrtime( true );
			$own               = $boot();
			$result['boots'][] = is_float( $own ) ? $own : ( hrtime( true ) - $start ) / 1e6;
			if ( ! $spin_first ) {
				$result['spins'][] = self::spin();
			}
		}

		return $result;
	}
}
