<?php
/**
 * Period helpers.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Tests\Unit;

use JOOservices\Resume\Model\Period;
use PHPUnit\Framework\TestCase;

final class PeriodTest extends TestCase {

	public function test_months_are_validated_and_indexed(): void {
		$this->assertTrue( Period::isMonth( '2025-06' ) );
		foreach ( array( '2025-13', '25-06', '2025-6', '', 202506, null ) as $bad ) {
			$this->assertFalse( Period::isMonth( $bad ) );
		}
		$this->assertSame( 24300, Period::index( '2025-01' ) );
		$this->assertSame( -1, Period::index( 'nope' ) );
	}

	public function test_duration_counts_both_end_months_and_open_periods(): void {
		$this->assertSame( 40, Period::months( '2022-03', '2025-06', '2030-01' ) );
		$this->assertSame( 1, Period::months( '2025-06', '2025-06', '2030-01' ) );
		$this->assertSame( 4, Period::months( '2025-03', '', '2025-06' ) );
		$this->assertSame( 0, Period::months( '2025-06', '2025-01', '2030-01' ) );
		$this->assertSame( 0, Period::months( '', '2025-01', '2030-01' ) );
		$this->assertSame(
			array(
				'years'  => 3,
				'months' => 4,
			),
			Period::parts( 40 )
		);
		$this->assertSame(
			array(
				'years'  => 0,
				'months' => 0,
			),
			Period::parts( -5 )
		);
	}

	public function test_inside_and_overlap_rules(): void {
		$this->assertTrue( Period::inside( '2022-05', '2024-01', '2022-03', '2025-06' ) );
		$this->assertFalse( Period::inside( '2022-01', '2024-01', '2022-03', '2025-06' ), 'starts before the company' );
		$this->assertFalse( Period::inside( '2022-05', '2026-01', '2022-03', '2025-06' ), 'ends after the company' );
		$this->assertFalse( Period::inside( '2022-05', '', '2022-03', '2025-06' ), 'open role in a closed company' );
		$this->assertTrue( Period::inside( '2022-05', '', '2022-03', '' ), 'open role in the current company' );
		$this->assertFalse( Period::inside( '', '2024-01', '2022-03', '' ), 'a role without a start' );
		$this->assertTrue( Period::overlaps( '2022-03', '2023-03', '2023-03', '2024-01' ), 'a shared month overlaps' );
		$this->assertFalse( Period::overlaps( '2022-03', '2023-02', '2023-03', '2024-01' ) );
		$this->assertTrue( Period::overlaps( '2022-03', '', '2030-01', '2031-01' ), 'an open period reaches the future' );
	}
}
