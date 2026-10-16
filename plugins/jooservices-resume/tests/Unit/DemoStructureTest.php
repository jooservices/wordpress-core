<?php
/**
 * Structural diff coverage.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Tests\Unit;

use JOOservices\Resume\Demo\DemoStructure;
use JOOservices\TestKit\UnitTestCase;

final class DemoStructureTest extends UnitTestCase {

	public function test_matching_nested_structures_ignore_associative_key_order(): void {
		$expected = array(
			'counts' => array(
				'posts' => 3,
				'pages' => 2,
			),
			'menus'  => array(
				array(
					'title' => 'About',
					'type'  => 'page',
				),
			),
		);
		$actual   = array(
			'menus'  => array(
				array(
					'type'  => 'page',
					'title' => 'About',
				),
			),
			'counts' => array(
				'pages' => 2,
				'posts' => 3,
			),
		);

		$this->assertSame( array(), DemoStructure::differences( $expected, $actual ) );
	}

	public function test_diff_reports_missing_extra_and_changed_values(): void {
		$expected = array(
			'counts' => array(
				'posts' => 3,
				'pages' => 2,
			),
			'locale' => 'en_US',
		);
		$actual   = array(
			'counts' => array(
				'posts' => 4,
				'extra' => 1,
			),
			'locale' => 'vi',
		);

		$this->assertSame(
			array(
				'counts.extra: unexpected value',
				'counts.pages: missing value',
				'counts.posts: expected 3, found 4',
				'locale: expected "en_US", found "vi"',
			),
			DemoStructure::differences( $expected, $actual )
		);
	}
}
