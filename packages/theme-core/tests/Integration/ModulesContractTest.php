<?php
/**
 * Every core module against the shared contract.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\ModuleContract;
use JOOservices\TestKit\ModuleContractTest;
use JOOservices\ThemeCore\Kernel;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\BadRegisterModule;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\DependentModule;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\FailingModule;

final class ModulesContractTest extends ModuleContractTest {

	protected function manifest(): string {
		return __DIR__ . '/../Fixtures/manifests/perf.json';
	}

	protected function modules(): array {
		return array_values( array_filter( Kernel::BUILTIN_MODULES, 'class_exists' ) );
	}

	public function test_a_module_that_adds_hooks_and_writes_options_in_register_is_caught(): void {
		require_once __DIR__ . '/../Fixtures/Modules/BadRegisterModule.php';
		$violations = ModuleContract::registerViolations( BadRegisterModule::class );
		$this->assertContains( 'register() added callbacks to "init"', $violations );
		$this->assertContains( 'register() wrote options', $violations );
		delete_option( 'joo_bad_register_option' );
	}

	public function test_static_rules_catch_unknown_settings_and_hooks(): void {
		require_once __DIR__ . '/../Fixtures/Modules/BadRegisterModule.php';
		// The fixture is clean for settings and hooks, so only its documented id passes: prove the rules do fire on bad input.
		$violations = ModuleContract::staticViolations( 'Nope\\Missing', array(), array() );
		$this->assertSame( array( 'Nope\\Missing is not a module class' ), $violations );
		$this->assertSame( array(), ModuleContract::staticViolations( BadRegisterModule::class, $this->schemaKeys(), $this->registryHooks() ) );
	}

	public function test_dependency_check_reports_unknown_dependencies_and_duplicates(): void {
		require_once __DIR__ . '/../Fixtures/Modules/ModCycleX.php';
		require_once __DIR__ . '/../Fixtures/Modules/ModCycleY.php';
		$violations = ModuleContract::dependencyViolations( array( \JOOservices\ThemeCore\Tests\Fixtures\Modules\ModCycleX::class, \JOOservices\ThemeCore\Tests\Fixtures\Modules\ModCycleY::class ) );
		$this->assertNotEmpty( $violations );
		$duplicates = ModuleContract::dependencyViolations( array( BadRegisterModule::class, BadRegisterModule::class ) );
		$this->assertNotEmpty( $duplicates );
	}

	public function test_a_failed_module_blocks_its_dependent_and_site_health_reports_it(): void {
		add_filter(
			'joo/core/modules',
			static fn( array $modules ) => array_merge( $modules, array( FailingModule::class, DependentModule::class ) )
		);
		Kernel::boot( $this->manifest() );
		do_action( 'after_setup_theme' );

		$this->assertSame( 'failed', Kernel::status()['failing'] );
		$this->assertSame( 'blocked', Kernel::status()['dependent'] );
		$tests  = apply_filters( 'site_status_tests', array( 'direct' => array() ) );
		$result = ( $tests['direct']['joo_modules_status']['test'] )();
		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringContainsString( 'dependent (blocked)', $result['description'] );
	}
}
