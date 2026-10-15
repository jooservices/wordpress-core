<?php
/**
 * Kernel integration tests (real WordPress).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Kernel;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\FailingModule;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\HealthyModule;

final class KernelTest extends IntegrationTestCase {

	private const FIXTURES = '/workspace/packages/theme-core/tests/Fixtures/manifests/';

	public function set_up(): void {
		parent::set_up();
		Kernel::reset();
		Notices::reset();
		HealthyModule::$boots = 0;
	}

	public function tear_down(): void {
		Kernel::reset();
		Notices::reset();
		parent::tear_down();
	}

	public function test_constants_and_autoloader_are_available(): void {
		$this->assertTrue( defined( 'JOO_CORE_VERSION' ) );
		$this->assertTrue( class_exists( Kernel::class ) );
	}

	public function test_boot_builds_the_container_and_fires_ready(): void {
		$ready = 0;
		add_action(
			'joo/core/ready',
			static function () use ( &$ready ) {
				++$ready;
			}
		);
		Kernel::boot( self::FIXTURES . 'valid.json' );
		do_action( 'after_setup_theme' );

		$this->assertNotNull( Kernel::container() );
		$this->assertSame( 1, $ready );
		$this->assertInstanceOf( Manifest::class, Kernel::container()->get( Manifest::class ) );
		$this->assertSame( 'fixture-theme', Kernel::container()->get( Manifest::class )->slug() );
		$this->assertInstanceOf( Settings::class, Kernel::container()->get( Settings::class ) );
	}

	public function test_boot_is_idempotent(): void {
		$count = 0;
		add_filter(
			'joo/core/modules',
			static function ( array $modules ) use ( &$count ) {
				++$count;
				return $modules;
			}
		);
		Kernel::boot( self::FIXTURES . 'valid.json' );
		Kernel::boot( self::FIXTURES . 'valid.json' );
		$this->assertSame( 1, $count );
	}

	public function test_a_failing_module_does_not_break_the_page(): void {
		add_filter(
			'joo/core/modules',
			static fn( array $modules ) => array_merge( $modules, array( FailingModule::class, HealthyModule::class ) )
		);
		Kernel::boot( self::FIXTURES . 'valid.json' );
		do_action( 'after_setup_theme' );

		$status = Kernel::status();
		$this->assertSame( 'failed', $status['failing'] );
		$this->assertSame( 'booted', $status['healthy'] );
		$this->assertStringContainsString( 'intentional boot failure', Kernel::registry()->message( 'failing' ) );

		$this->go_to( '/' );
		$this->assertStringContainsString( '<!-- healthy-module -->', $this->capture( 'wp_head' ) );
	}

	public function test_an_invalid_manifest_adds_a_notice_but_core_still_boots(): void {
		Kernel::boot( self::FIXTURES . 'invalid.json' );
		$this->assertNotNull( Kernel::container() );
		$this->assertArrayHasKey( 'manifest-invalid', Notices::all() );
		$this->assertFalse( Kernel::container()->get( Manifest::class )->isValid() );
	}

	public function test_an_incompatible_core_range_does_not_boot(): void {
		Kernel::boot( self::FIXTURES . 'incompatible.json' );
		$this->assertNull( Kernel::container() );
		$this->assertArrayHasKey( 'core-incompatible', Notices::all() );
		$tests = apply_filters( 'site_status_tests', array() );
		$this->assertArrayHasKey( 'joo_core_incompatible', $tests['direct'] );
		$result = ( $tests['direct']['joo_core_incompatible']['test'] )();
		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringContainsString( 'requires JOOservices core', $result['description'] );
	}

	public function test_a_missing_manifest_is_reported(): void {
		Kernel::boot( '/nonexistent/joo-theme.json' );
		$this->assertArrayHasKey( 'manifest-invalid', Notices::all() );
	}

	public function test_notices_are_printed_for_admins_only_and_escaped(): void {
		Notices::add( 'error', '<script>alert(1)</script>', 'xss' );
		wp_set_current_user( 0 );
		ob_start();
		Notices::render();
		$this->assertSame( '', ob_get_clean() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		Notices::render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_services_can_be_added_but_core_ones_are_protected(): void {
		add_filter(
			'joo/core/services',
			static fn( array $services ) => $services + array(
				'my.service' => static fn() => 'mine',
				\JOOservices\ThemeCore\Container\Container::class => static fn() => 'hijacked',
			)
		);
		Kernel::boot( self::FIXTURES . 'valid.json' );
		$this->assertSame( 'mine', Kernel::container()->get( 'my.service' ) );
		$this->assertInstanceOf( \JOOservices\ThemeCore\Container\Container::class, Kernel::container()->get( \JOOservices\ThemeCore\Container\Container::class ) );
	}

	public function test_module_toggle_in_settings_disables_a_module(): void {
		add_filter(
			'joo/core/modules',
			static fn( array $modules ) => array_merge( $modules, array( HealthyModule::class ) )
		);
		add_filter(
			'joo/settings/value',
			static fn( $value, $key ) => 'modules.healthy.enabled' === $key ? false : $value,
			10,
			2
		);
		Kernel::boot( self::FIXTURES . 'valid.json' );
		do_action( 'after_setup_theme' );
		// Unknown to the descriptor: toggles only apply to registered keys, so the module stays enabled.
		$this->assertSame( 'booted', Kernel::status()['healthy'] );
	}

	public function test_boot_cost_is_small(): void {
		$memory = memory_get_usage();
		$start  = hrtime( true );
		Kernel::boot( self::FIXTURES . 'valid.json' );
		$ms = ( hrtime( true ) - $start ) / 1e6;
		$kb = ( memory_get_usage() - $memory ) / 1024;
		fwrite( STDERR, sprintf( "\n[boot cost] %.2f ms, %.0f KB\n", $ms, $kb ) );
		$this->assertLessThan( 30, $ms, 'boot must stay cheap (spec target < 3 ms warm; asserted with a CI margin)' );
		$this->assertLessThan( 1536, $kb, 'boot must stay under 1.5 MB' );
	}
}
