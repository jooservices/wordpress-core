<?php
/**
 * Module registry tests.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Module;

use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Module\ModuleRegistry;
use JOOservices\ThemeCore\Support\Logger;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\BadIdModule;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\ModA;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\ModB;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\ModC;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\ModCycleX;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\ModCycleY;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\ModD;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\ModE;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\ModF;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\ModOrphan;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\RecordingModule;
use PHPUnit\Framework\TestCase;

final class ModuleRegistryTest extends TestCase {

	protected function setUp(): void {
		RecordingModule::reset();
	}

	private function registry( array $classes ): ModuleRegistry {
		$registry = new ModuleRegistry( new Logger( static fn(): bool => false ) );
		foreach ( $classes as $class ) {
			$registry->add( $class );
		}
		return $registry;
	}

	public function test_dependencies_are_ordered_topologically(): void {
		$r     = $this->registry( array( ModF::class, ModE::class, ModD::class, ModC::class, ModB::class, ModA::class ) );
		$order = $r->resolve( array( 'a', 'b', 'c', 'd', 'e', 'f' ) );
		foreach ( array(
			'b' => 'a',
			'c' => 'b',
			'd' => 'c',
			'e' => 'b',
			'f' => 'e',
		) as $module => $dependency ) {
			$this->assertLessThan( array_search( $module, $order, true ), array_search( $dependency, $order, true ), "$dependency must come before $module" );
		}
		$this->assertCount( 6, $order );
	}

	public function test_only_enabled_modules_are_selected(): void {
		$r = $this->registry( array( ModA::class, ModB::class ) );
		$this->assertSame( array( 'a' ), $r->resolve( array( 'a' ) ) );
		$this->assertSame( ModuleRegistry::STATUS_DISABLED, $r->status()['b'] );
	}

	public function test_missing_dependency_blocks_the_module(): void {
		$r = $this->registry( array( ModA::class, ModOrphan::class ) );
		$this->assertSame( array( 'a' ), $r->resolve( array( 'a', 'orphan' ) ) );
		$this->assertSame( ModuleRegistry::STATUS_BLOCKED, $r->status()['orphan'] );
		$this->assertStringContainsString( 'missing', $r->message( 'orphan' ) );
	}

	public function test_cycles_are_detected(): void {
		$r = $this->registry( array( ModCycleX::class, ModCycleY::class ) );
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/cycle/' );
		$r->resolve( array( 'x', 'y' ) );
	}

	public function test_lifecycle_runs_register_then_boot_in_order(): void {
		$r = $this->registry( array( ModB::class, ModA::class ) );
		$r->resolve( array( 'a', 'b' ) );
		$r->registerAll( new Container() );
		$this->assertSame( array( 'register:a', 'register:b' ), RecordingModule::$events );
		$r->bootAll();
		$this->assertSame( array( 'register:a', 'register:b', 'boot:a', 'boot:b' ), RecordingModule::$events );
		$this->assertEquals(
			array(
				'a' => 'booted',
				'b' => 'booted',
			),
			$r->status()
		);
	}

	public function test_a_failing_register_is_isolated_and_blocks_dependents(): void {
		RecordingModule::$fail = array( 'register:a' => true );
		$r                     = $this->registry( array( ModA::class, ModB::class, ModE::class ) );
		$r->resolve( array( 'a', 'b', 'e' ) );
		$r->registerAll( new Container() );
		$r->bootAll();
		$this->assertSame( ModuleRegistry::STATUS_FAILED, $r->status()['a'] );
		$this->assertSame( ModuleRegistry::STATUS_BLOCKED, $r->status()['b'] );
		$this->assertSame( ModuleRegistry::STATUS_BLOCKED, $r->status()['e'] );
		$this->assertStringContainsString( 'register failed', $r->message( 'a' ) );
	}

	public function test_a_failing_boot_does_not_stop_independent_modules(): void {
		RecordingModule::$fail = array( 'boot:b' => true );
		$r                     = $this->registry( array( ModA::class, ModB::class, ModC::class ) );
		$r->resolve( array( 'a', 'b', 'c' ) );
		$r->registerAll( new Container() );
		$r->bootAll();
		$this->assertSame( ModuleRegistry::STATUS_BOOTED, $r->status()['a'] );
		$this->assertSame( ModuleRegistry::STATUS_FAILED, $r->status()['b'] );
		$this->assertSame( ModuleRegistry::STATUS_BLOCKED, $r->status()['c'] );
	}

	public function test_invalid_module_id_is_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->registry( array( BadIdModule::class ) );
	}

	public function test_duplicate_id_is_rejected(): void {
		$r = $this->registry( array( ModA::class ) );
		$this->expectException( \InvalidArgumentException::class );
		$r->add( ModA::class );
	}

	public function test_non_module_class_is_rejected(): void {
		$r = new ModuleRegistry( new Logger( static fn(): bool => false ) );
		$this->expectException( \InvalidArgumentException::class );
		$r->add( \stdClass::class );
	}

	public function test_ids_lists_added_modules(): void {
		$this->assertSame( array( 'a', 'b' ), $this->registry( array( ModA::class, ModB::class ) )->ids() );
	}
}
