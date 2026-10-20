<?php
/**
 * Hooks helper tests.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Support;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use JOOservices\TestKit\UnitTestCase;
use JOOservices\ThemeCore\Support\Hooks;

final class HooksTest extends UnitTestCase {

	public function test_valid_names(): void {
		$this->assertTrue( Hooks::isValidName( 'joo/seo/title' ) );
		$this->assertTrue( Hooks::isValidName( 'joo/blocks/data/profile-card' ) );
		$this->assertFalse( Hooks::isValidName( 'joo_seo_title' ) );
		$this->assertFalse( Hooks::isValidName( 'other/seo/title' ) );
		$this->assertFalse( Hooks::isValidName( 'joo/seo' ) );
	}

	public function test_filter_passes_arguments_through(): void {
		Filters\expectApplied( 'joo/seo/title' )->once()->with( 'a', 'extra' )->andReturn( 'b' );
		$this->assertSame( 'b', Hooks::filter( 'joo/seo/title', 'a', 'extra' ) );
	}

	public function test_action_runs(): void {
		Actions\expectDone( 'joo/core/ready' )->once()->with( 'x' );
		Hooks::action( 'joo/core/ready', 'x' );
		$this->addToAssertionCount( 1 );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_invalid_name_throws_in_debug(): void {
		define( 'WP_DEBUG', true );
		$this->expectException( \InvalidArgumentException::class );
		Hooks::filter( 'bad_name', 'x' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_deprecated_triggers_only_when_something_is_hooked(): void {
		define( 'WP_DEBUG', true );
		Functions\when( 'has_filter' )->justReturn( true );
		Functions\expect( '_deprecated_hook' )->once()->with( 'joo/old/hook', '0.2.0', 'joo/new/hook' );
		Hooks::deprecated( 'joo/old/hook', '0.2.0', 'joo/new/hook' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_deprecated_is_silent_when_nothing_is_hooked(): void {
		define( 'WP_DEBUG', true );
		Functions\when( 'has_filter' )->justReturn( false );
		Functions\expect( '_deprecated_hook' )->never();
		Hooks::deprecated( 'joo/old/hook', '0.2.0' );
		$this->addToAssertionCount( 1 );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_deprecated_reports_a_hook_once_per_request(): void {
		define( 'WP_DEBUG', true );
		Functions\when( 'has_filter' )->justReturn( true );
		Functions\expect( '_deprecated_hook' )->once()->with( 'joo/unit/repeated_hook', '0.2.0', 'joo/new/hook' );

		Hooks::deprecated( 'joo/unit/repeated_hook', '0.2.0', 'joo/new/hook' );
		Hooks::deprecated( 'joo/unit/repeated_hook', '0.2.0', 'joo/new/hook' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_deprecated_does_not_report_a_hook_outside_debug_mode(): void {
		define( 'WP_DEBUG', false );
		Functions\expect( 'has_filter' )->never();
		Functions\expect( '_deprecated_hook' )->never();

		Hooks::deprecated( 'joo/unit/non_debug_hook', '0.2.0', 'joo/new/hook' );
		$this->addToAssertionCount( 1 );
	}
}
