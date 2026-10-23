<?php
/**
 * Shared contract test for modules.
 *
 * @package JOOservices\TestKit
 */

declare(strict_types=1);

namespace JOOservices\TestKit;

use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Kernel;

/**
 * Extend and implement manifest() and modules(); every module is then tested against the contract.
 */
abstract class ModuleContractTest extends IntegrationTestCase {

	private const BOOT_BUDGET_SAMPLES = 20;

	/**
	 * Manifest file used to boot the kernel.
	 */
	abstract protected function manifest(): string;

	/**
	 * Module classes under test.
	 *
	 * @return list<string>
	 */
	abstract protected function modules(): array;

	/**
	 * Data provider: one entry per module.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function moduleProvider(): array {
		$out = array();
		foreach ( Kernel::BUILTIN_MODULES as $class ) {
			if ( class_exists( $class ) ) {
				$out[ $class::id() ] = array( $class );
			}
		}
		return $out;
	}

	/**
	 * Setting keys of the schema.
	 *
	 * @return list<string>
	 */
	protected function schemaKeys(): array {
		$schema = json_decode( (string) file_get_contents( JOO_CORE_DIR . '/schema/settings.schema.json' ), true );
		return array_keys( $schema['settings'] );
	}

	/**
	 * Hook patterns of the naming registry.
	 *
	 * @return list<string>
	 */
	protected function registryHooks(): array {
		$registry = json_decode( (string) file_get_contents( dirname( JOO_CORE_DIR, 2 ) . '/docs/spec/naming-registry.json' ), true );
		return $registry['hooks'];
	}

	public function set_up(): void {
		parent::set_up();
		Kernel::reset();
	}

	public function tear_down(): void {
		Kernel::reset();
		parent::tear_down();
	}

	public function test_ids_are_unique_and_dependencies_are_resolvable(): void {
		$this->assertSame( array(), ModuleContract::dependencyViolations( $this->modules() ) );
	}

	/**
	 * @dataProvider moduleProvider
	 */
	public function test_static_rules( string $class ): void {
		$this->assertSame( array(), ModuleContract::staticViolations( $class, $this->schemaKeys(), $this->registryHooks() ), $class );
	}

	/**
	 * @dataProvider moduleProvider
	 */
	public function test_register_has_no_side_effects( string $class ): void {
		$this->assertSame( array(), ModuleContract::registerViolations( $class ), $class );
	}

	/**
	 * @dataProvider moduleProvider
	 */
	public function test_boot_is_idempotent( string $class ): void {
		Kernel::boot( $this->manifest() );
		do_action( 'after_setup_theme' );
		$module = Kernel::registry()?->module( $class::id() );
		$this->assertNotNull( $module, $class );
		$this->assertSame( array(), ModuleContract::bootIdempotenceViolations( $module ), $class );
	}

	/**
	 * @dataProvider moduleProvider
	 */
	public function test_the_rest_boots_without_this_module( string $class ): void {
		$this->assertSame( array(), ModuleContract::disabledViolations( $this->manifest(), $class::id() ), $class );
	}

	public function test_boot_stays_within_the_budget(): void {
		if ( 0 === did_action( 'after_setup_theme' ) ) {
			do_action( 'after_setup_theme' );
		}
		$hooks   = $this->snapshotHooks();
		$timings = array();
		$spins   = array();
		$totals  = array();

		try {
			for ( $sample = 0; $sample < self::BOOT_BUDGET_SAMPLES; ++$sample ) {
				$this->restoreHooks( $hooks );
				Kernel::reset();
				Notices::reset();
				gc_collect_cycles();

				if ( BootBudget::spinsFirst( $sample ) ) {
					$spins[] = BootBudget::spin();
				}
				Kernel::boot( $this->manifest() );
				if ( ! BootBudget::spinsFirst( $sample ) ) {
					$spins[] = BootBudget::spin();
				}

				$totals[ $sample ] = 0.0;
				foreach ( Kernel::registry()?->timings() ?? array() as $id => $cost ) {
					$totals[ $sample ]        += $cost['ms'];
					$timings[ $id ]['ms'][]    = $cost['ms'];
					$timings[ $id ]['bytes'][] = $cost['bytes'];
				}
			}
		} finally {
			$this->restoreHooks( $hooks );
			Kernel::reset();
			Notices::reset();
		}

		// The whole boot is compared with a spin timed right before each sample, so a doubled boot fails on a busy host too.
		$this->assertLessThan( BootBudget::MAX_BOOT_UNITS, BootBudget::units( $totals, $spins ), 'median whole-boot cost (units of the same-run spin)' );

		$budget = BootBudget::scale( $this->maxBootMs(), $spins );
		foreach ( $timings as $id => $cost ) {
			$this->assertSame( self::BOOT_BUDGET_SAMPLES, count( $cost['ms'] ), $id . ' boot samples' );
			$this->assertLessThan( $budget, $this->median( $cost['ms'] ), $id . ' median boot time (ms)' );
			$this->assertLessThan( $this->maxBootBytes(), $this->median( $cost['bytes'] ), $id . ' median boot memory (bytes)' );
		}
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
	 * Boot time budget per module in milliseconds.
	 */
	protected function maxBootMs(): float {
		return 25.0;
	}

	/**
	 * Boot memory budget per module in bytes.
	 */
	protected function maxBootBytes(): int {
		return 1024 * 1024;
	}
}
