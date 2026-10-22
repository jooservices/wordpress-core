<?php
/**
 * Load-aware boot budget tests.
 *
 * @package JOOservices\TestKit
 */

declare(strict_types=1);

namespace JOOservices\TestKit\Tests;

use JOOservices\TestKit\BootBudget;
use PHPUnit\Framework\TestCase;

final class BootBudgetTest extends TestCase {

	private const SAMPLES = 21;

	/**
	 * A stand-in boot of about one unit (the 3 ms spec target); $weight 2 injects a 2x slowdown.
	 */
	private static function boot( int $weight ): callable {
		return static function () use ( $weight ): void {
			for ( $i = 0; $i < $weight; ++$i ) {
				BootBudget::spin();
			}
		};
	}

	public function test_median_handles_odd_and_even_counts(): void {
		$this->assertSame( 4.0, BootBudget::median( array( 8, 2, 4 ) ) );
		$this->assertSame( 3.0, BootBudget::median( array( 7, 1, 5, 1 ) ) );
	}

	public function test_median_rejects_empty_samples(): void {
		$this->expectException( \InvalidArgumentException::class );
		BootBudget::median( array() );
	}

	public function test_the_idle_baseline_comes_from_the_run_itself(): void {
		$this->assertSame( 1.0, BootBudget::hostFactor( array( 10.0, 10.0, 10.0 ) ) );
		$this->assertSame( 2.0, BootBudget::hostFactor( array( 5.0, 10.0, 10.0 ) ) );
		$this->assertSame( 60.0, BootBudget::scale( 30.0, array( 5.0, 10.0, 10.0 ) ) );
	}

	public function test_a_real_boot_stays_under_the_ceiling(): void {
		$run = BootBudget::measure( self::boot( 1 ), self::SAMPLES );
		$this->assertCount( self::SAMPLES, $run['boots'] );
		$this->assertLessThan( BootBudget::MAX_BOOT_UNITS, BootBudget::units( $run['boots'], $run['spins'] ) );
	}

	public function test_a_real_boot_with_an_injected_2x_slowdown_fails_the_ceiling(): void {
		$run = BootBudget::measure( self::boot( 2 ), self::SAMPLES );
		$this->assertGreaterThanOrEqual( BootBudget::MAX_BOOT_UNITS, BootBudget::units( $run['boots'], $run['spins'] ) );
	}

	public function test_a_busy_host_does_not_fail_a_healthy_boot_but_still_fails_a_doubled_one(): void {
		// Busy host: every timing, boots and spins alike, is stretched by the same factor.
		$busy = static fn( array $samples ): array => array_map( static fn( float $ms ): float => $ms * 4.2, $samples );

		$healthy = BootBudget::measure( self::boot( 1 ), self::SAMPLES );
		$doubled = BootBudget::measure( self::boot( 2 ), self::SAMPLES );

		$this->assertLessThan( BootBudget::MAX_BOOT_UNITS, BootBudget::units( $busy( $healthy['boots'] ), $busy( $healthy['spins'] ) ) );
		$this->assertGreaterThanOrEqual( BootBudget::MAX_BOOT_UNITS, BootBudget::units( $busy( $doubled['boots'] ), $busy( $doubled['spins'] ) ) );
	}

	public function test_measure_accepts_a_boot_that_reports_its_own_cost(): void {
		$run = BootBudget::measure( static fn(): float => 7.0, 3 );
		$this->assertSame( array( 7.0, 7.0, 7.0 ), $run['boots'] );
	}
}
