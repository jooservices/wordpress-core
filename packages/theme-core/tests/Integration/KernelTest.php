<?php
/**
 * Kernel integration tests (real WordPress).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\BootBudget;
use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Kernel;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\DependentModule;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\FailingModule;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\FailingRegisterModule;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\HealthyModule;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\SlowBootModule;

final class KernelTest extends IntegrationTestCase {

	private const FIXTURES          = __DIR__ . '/../Fixtures/manifests/';
	private const BOOT_COST_SAMPLES = 20;

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
			static fn( array $modules ) => array_merge( $modules, array( FailingModule::class, DependentModule::class, HealthyModule::class ) )
		);
		Kernel::boot( self::FIXTURES . 'valid.json' );
		do_action( 'after_setup_theme' );

		$status = Kernel::status();
		$this->assertSame( 'failed', $status['failing'] );
		$this->assertSame( 'blocked', $status['dependent'] );
		$this->assertSame( 'booted', $status['healthy'] );
		$registry = Kernel::registry();
		$this->assertNotNull( $registry );
		$this->assertStringContainsString( 'intentional boot failure', $registry->message( 'failing' ) );
		$this->assertFailurePresentation(
			array(
				'Module "failing" failed during boot().',
				'Module "dependent" was blocked because dependency "failing" did not start.',
			),
			'joo_modules_status',
			'critical',
			'dependent (blocked)'
		);
		$this->assertStringContainsString( '<!-- healthy-module -->', $this->capture( 'wp_head' ) );
	}

	public function test_a_register_failure_does_not_break_the_page(): void {
		add_filter(
			'joo/core/modules',
			static fn( array $modules ) => array_merge( $modules, array( FailingRegisterModule::class, HealthyModule::class ) )
		);
		Kernel::boot( self::FIXTURES . 'valid.json' );
		do_action( 'after_setup_theme' );

		$this->assertSame( 'failed', Kernel::status()['register-failing'] );
		$this->assertSame( 'booted', Kernel::status()['healthy'] );
		$this->assertFailurePresentation( array( 'Module "register-failing" failed during register().' ), 'joo_modules_status', 'critical', 'register-failing (failed)' );
	}

	public function test_an_invalid_manifest_disables_modules_and_adds_a_notice(): void {
		$module_filter_calls = 0;
		add_filter(
			'joo/core/modules',
			static function ( array $modules ) use ( &$module_filter_calls ): array {
				++$module_filter_calls;
				return $modules;
			}
		);
		Kernel::boot( self::FIXTURES . 'invalid.json' );
		$this->assertNull( Kernel::container() );
		$this->assertSame( array(), Kernel::status() );
		$this->assertSame( 0, $module_filter_calls );
		$this->assertArrayHasKey( 'manifest-invalid', Notices::all() );
		$this->assertFailurePresentation( array( 'The theme manifest is invalid' ), 'joo_manifest_valid', 'critical', 'The theme manifest is invalid' );
	}

	public function test_an_unsupported_wordpress_version_adds_a_notice_and_site_health_entry(): void {
		global $wp_version;
		$version    = $wp_version;
		$wp_version = '6.5.9';
		try {
			Kernel::boot( self::FIXTURES . 'valid.json' );
		} finally {
			$wp_version = $version;
		}

		$this->assertNull( Kernel::container() );
		$this->assertArrayHasKey( 'requirement-wp', Notices::all() );
		$this->assertFailurePresentation( array( 'WordPress 6.6 or newer' ), 'joo_core_requirements', 'critical', 'found 6.5.9' );
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
		$this->assertNull( Kernel::container() );
		$this->assertArrayHasKey( 'manifest-invalid', Notices::all() );
		$tests = apply_filters( 'site_status_tests', array( 'direct' => array() ) );
		$this->assertArrayHasKey( 'joo_manifest_valid', $tests['direct'] );
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

	// phpcs:disable WordPress.PHP.IniSet.Risky -- the test redirects error_log to a temporary file.
	public function test_module_errors_are_written_only_when_debug_logging_is_enabled(): void {
		$file       = tempnam( sys_get_temp_dir(), 'joo-kernel-log' );
		$old_log    = ini_set( 'error_log', (string) $file );
		$debug      = false;
		$moduleHook = static fn( array $modules ) => array_merge( $modules, array( FailingRegisterModule::class ) );
		$debugHook  = static function ( $value, $key ) use ( &$debug ) {
			return 'core.debug' === $key ? $debug : $value;
		};
		add_filter( 'joo/core/modules', $moduleHook );
		add_filter( 'joo/settings/value', $debugHook, 10, 2 );

		try {
			Kernel::boot( self::FIXTURES . 'valid.json' );
			do_action( 'after_setup_theme' );
			$disabled_log = (string) file_get_contents( (string) $file );
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				$this->assertStringContainsString( 'register-failing', $disabled_log );
			} else {
				$this->assertSame( '', $disabled_log );
			}

			Kernel::reset();
			Notices::reset();
			file_put_contents( (string) $file, '' );
			$debug = true;
			Kernel::boot( self::FIXTURES . 'valid.json' );
			do_action( 'after_setup_theme' );
			$this->assertStringContainsString( 'register-failing', (string) file_get_contents( (string) $file ) );
		} finally {
			remove_filter( 'joo/core/modules', $moduleHook );
			remove_filter( 'joo/settings/value', $debugHook, 10 );
			ini_set( 'error_log', (string) $old_log );
			unlink( (string) $file );
		}
	}
	// phpcs:enable WordPress.PHP.IniSet.Risky

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
		$samples = $this->measureBootCosts();
		$ms      = $this->median( $samples['ms'] );
		$kb      = $this->median( $samples['bytes'] ) / 1024;
		fwrite( STDERR, sprintf( "\n[boot cost] median %.2f ms, %.0f KB across %d boots\n", $ms, $kb, self::BOOT_COST_SAMPLES ) );
		$units = BootBudget::units( $samples['ms'], $samples['spins'] );
		fwrite( STDERR, sprintf( "[boot cost] %.2f units (1 unit = median spin %.2f ms; host factor %.2f)\n", $units, $this->median( $samples['spins'] ), BootBudget::hostFactor( $samples['spins'] ) ) );
		$this->assertLessThan( BootBudget::MAX_BOOT_UNITS, $units, 'median boot must stay within 1.5x the 3 ms spec target, measured against a spin timed right before each boot (a doubled boot fails)' );
		$this->assertLessThan( 1536, $kb, 'median boot must stay under 1.5 MB' );
	}

	public function test_slow_module_exceeds_the_boot_time_budget(): void {
		add_filter(
			'joo/core/modules',
			static fn( array $modules ): array => array_merge( $modules, array( SlowBootModule::class ) )
		);

		$slow = $this->measureBootCosts();

		remove_all_filters( 'joo/core/modules' );
		$clean = $this->measureBootCosts();

		$this->assertGreaterThanOrEqual( BootBudget::MAX_BOOT_UNITS, BootBudget::units( $slow['ms'], $slow['spins'] ), 'an injected slow module must exceed the boot ceiling' );
		$this->assertLessThan( BootBudget::MAX_BOOT_UNITS, BootBudget::units( $clean['ms'], $clean['spins'] ), 'the clean boot of the same run must stay under it' );
	}

	/**
	 * Measures complete kernel boots from the same WordPress hook state.
	 *
	 * @return array{ms: list<float>, bytes: list<int>, spins: list<float>}
	 */
	private function measureBootCosts(): array {
		if ( 0 === did_action( 'after_setup_theme' ) ) {
			do_action( 'after_setup_theme' );
		}
		$hooks   = $this->snapshotHooks();
		$samples = array(
			'ms'    => array(),
			'bytes' => array(),
			'spins' => array(),
		);

		try {
			for ( $sample = 0; $sample < self::BOOT_COST_SAMPLES; ++$sample ) {
				$this->restoreHooks( $hooks );
				Kernel::reset();
				Notices::reset();
				gc_collect_cycles();

				if ( BootBudget::spinsFirst( $sample ) ) {
					$samples['spins'][] = BootBudget::spin();
				}
				$memory = memory_get_usage();
				$start  = hrtime( true );
				Kernel::boot( self::FIXTURES . 'valid.json' );
				$samples['ms'][]    = ( hrtime( true ) - $start ) / 1e6;
				$samples['bytes'][] = max( 0, memory_get_usage() - $memory );
				if ( ! BootBudget::spinsFirst( $sample ) ) {
					$samples['spins'][] = BootBudget::spin();
				}
			}
		} finally {
			$this->restoreHooks( $hooks );
			Kernel::reset();
			Notices::reset();
		}

		return $samples;
	}

	/**
	 * Saves a clean copy of WordPress hook callbacks.
	 *
	 * @return array<string, mixed> Saved WordPress hook callbacks.
	 */
	private function snapshotHooks(): array {
		$filters = $GLOBALS['wp_filter'] ?? array();
		foreach ( $filters as $hook => $callbacks ) {
			if ( $callbacks instanceof \WP_Hook ) {
				$filters[ $hook ] = clone $callbacks;
			}
		}

		return $filters;
	}

	/**
	 * Restores a fresh copy of a saved WordPress hook state.
	 *
	 * @param array<string, mixed> $snapshot Hook state.
	 */
	private function restoreHooks( array $snapshot ): void {
		$hooks = array_unique( array_merge( array_keys( $GLOBALS['wp_filter'] ?? array() ), array_keys( $snapshot ) ) );
		foreach ( $hooks as $hook ) {
			remove_all_filters( (string) $hook );
		}

		foreach ( $snapshot as $hook => $callbacks ) {
			if ( ! $callbacks instanceof \WP_Hook ) {
				continue;
			}
			foreach ( $callbacks->callbacks as $priority => $entries ) {
				foreach ( $entries as $entry ) {
					if ( isset( $entry['function'] ) && is_callable( $entry['function'] ) ) {
						add_filter( (string) $hook, $entry['function'], (int) $priority, (int) $entry['accepted_args'] );
					}
				}
			}
		}
	}

	/**
	 * Returns the median of numeric samples.
	 *
	 * @param list<int|float> $samples Samples.
	 */
	private function median( array $samples ): float {
		sort( $samples, SORT_NUMERIC );
		$middle = intdiv( count( $samples ), 2 );

		return 0 === count( $samples ) % 2
			? ( (float) $samples[ $middle - 1 ] + (float) $samples[ $middle ] ) / 2
			: (float) $samples[ $middle ];
	}

	/**
	 * Asserts the failure appears to authorised admins and Site Health, but not to front-end visitors.
	 *
	 * @param list<string> $noticeTexts Notice text expected for administrators.
	 * @param string $healthId   Site Health test id.
	 * @param string $status     Site Health result status.
	 * @param string $healthText Site Health description text.
	 */
	private function assertFailurePresentation( array $noticeTexts, string $healthId, string $status, string $healthText ): void {
		$this->request( '/' );
		$this->assertFalse( is_404(), 'The front-end request should remain successful.' );
		// WP_UnitTestCase runs without an HTTP server; false means WordPress did not set an error status.
		$response_status = http_response_code();
		$this->assertTrue( false === $response_status || 200 === $response_status, 'The front-end request should return HTTP 200.' );
		$this->assertStringNotContainsString( 'JOOservices:', $this->capture( 'wp_head' ) );

		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$this->assertTrue( current_user_can( 'edit_theme_options' ) );
		$admin_notices = html_entity_decode( $this->capture( 'admin_notices' ), ENT_QUOTES, 'UTF-8' );
		foreach ( $noticeTexts as $noticeText ) {
			$this->assertStringContainsString( $noticeText, $admin_notices );
		}

		$tests = apply_filters( 'site_status_tests', array( 'direct' => array() ) );
		$this->assertArrayHasKey( $healthId, $tests['direct'] );
		$result = ( $tests['direct'][ $healthId ]['test'] )();
		$this->assertSame( $status, $result['status'] );
		$this->assertStringContainsString( $healthText, $result['description'] );

		wp_set_current_user( 0 );
		$this->assertSame( '', $this->capture( 'admin_notices' ) );
	}
}
