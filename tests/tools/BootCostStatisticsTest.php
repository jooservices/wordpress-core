<?php
/**
 * Boot-cost measurement statistics tests.
 *
 * @package JOOservices\Tools\Tests
 */

declare(strict_types=1);

use JOOservices\Tools\BootCostStatistics;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/tools/lib/boot-cost-statistics.php';

final class BootCostStatisticsTest extends TestCase {

	public function test_median_sorts_odd_samples(): void {
		$this->assertSame( 4.0, BootCostStatistics::median( array( 8, 2, 4 ) ) );
	}

	public function test_median_averages_the_two_middle_even_samples(): void {
		$this->assertSame( 3.0, BootCostStatistics::median( array( 7, 1, 5, 1 ) ) );
	}

	public function test_peak_returns_the_largest_memory_sample(): void {
		$this->assertSame( 2048, BootCostStatistics::peak( array( 1024, 2048, 1536 ) ) );
	}

	public function test_empty_samples_are_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		BootCostStatistics::median( array() );
	}
}
