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

	public function test_median_handles_odd_and_even_counts(): void {
		$this->assertSame( 4.0, BootBudget::median( array( 8, 2, 4 ) ) );
		$this->assertSame( 3.0, BootBudget::median( array( 7, 1, 5, 1 ) ) );
	}

	public function test_median_rejects_empty_samples(): void {
		$this->expectException( \InvalidArgumentException::class );
		BootBudget::median( array() );
	}

	public function test_an_idle_host_keeps_the_nominal_budget(): void {
		$this->assertSame( 30.0, BootBudget::scale( 30.0, BootBudget::REFERENCE_SPIN_MS ) );
		$this->assertSame( 30.0, BootBudget::scale( 30.0, 1.0 ) );
	}

	public function test_a_busy_host_stretches_the_budget(): void {
		$this->assertSame( 60.0, BootBudget::scale( 30.0, BootBudget::REFERENCE_SPIN_MS * 2 ) );
	}

	public function test_a_slow_boot_on_a_busy_host_passes_but_a_doubled_one_fails(): void {
		$budget = BootBudget::scale( 30.0, BootBudget::REFERENCE_SPIN_MS * 1.5 );
		$this->assertLessThan( $budget, 42.0 );
		$this->assertGreaterThan( $budget, 2 * 42.0 );
	}

	public function test_measured_spin_is_positive(): void {
		$this->assertGreaterThan( 0.0, BootBudget::measureSpin() );
		$this->assertGreaterThanOrEqual( 1.0, BootBudget::hostFactor() );
	}
}
