<?php
/**
 * Shared contract test for modules.
 *
 * @package JOOservices\TestKit
 */

declare(strict_types=1);

namespace JOOservices\TestKit;

use JOOservices\ThemeCore\Kernel;

/**
 * Extend and implement manifest() and modules(); every module is then tested against the contract.
 */
abstract class ModuleContractTest extends IntegrationTestCase {

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
		Kernel::boot( $this->manifest() );
		do_action( 'after_setup_theme' );
		foreach ( Kernel::registry()?->timings() ?? array() as $id => $cost ) {
			$this->assertLessThan( $this->maxBootMs(), $cost['ms'], $id . ' boot time (ms)' );
			$this->assertLessThan( $this->maxBootBytes(), $cost['bytes'], $id . ' boot memory (bytes)' );
		}
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
