<?php
/**
 * Proves the unit test base class works.
 *
 * @package JOOservices\TestKit
 */

declare(strict_types=1);

namespace JOOservices\TestKit\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use JOOservices\TestKit\UnitTestCase;

final class UnitTestCaseTest extends UnitTestCase {

	public function test_wordpress_functions_can_be_mocked(): void {
		Functions\when( 'get_option' )->justReturn( 'x' );
		$this->assertSame( 'x', get_option( 'anything' ) );
	}

	public function test_escape_stub_escapes_html(): void {
		$this->assertSame( '&lt;b&gt;&quot;', esc_html( '<b>"' ) );
	}

	public function test_filters_and_actions_are_recorded(): void {
		Filters\expectApplied( 'joo/test' )->once()->with( 'a' )->andReturn( 'a!' );
		Actions\expectAdded( 'init' )->once();

		add_action( 'init', '__return_true' );
		$this->assertSame( 'a!', apply_filters( 'joo/test', 'a' ) );
	}
}
