<?php
/**
 * core.php bootstrap tests (separate processes: the file defines constants).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Bootstrap;

use PHPUnit\Framework\TestCase;

final class CoreBootstrapTest extends TestCase {

	private function corePhp(): string {
		return dirname( __DIR__, 3 ) . '/core.php';
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_constants_are_defined_and_the_autoloader_works(): void {
		require $this->corePhp();
		$this->assertTrue( defined( 'JOO_CORE_VERSION' ) );
		$this->assertSame( '8.1', JOO_CORE_MIN_PHP );
		$this->assertFalse( defined( 'JOO_CORE_DUPLICATE' ) );
		$this->assertTrue( class_exists( \JOOservices\ThemeCore\Support\Version::class ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_including_twice_marks_a_duplicate_and_does_not_redeclare(): void {
		require $this->corePhp();
		ob_start();
		require $this->corePhp();
		$output = ob_get_clean();
		$this->assertSame( '', $output );
		$this->assertTrue( defined( 'JOO_CORE_DUPLICATE' ) );
		$this->assertSame( dirname( $this->corePhp() ), JOO_CORE_DUPLICATE );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_including_core_php_produces_no_output(): void {
		ob_start();
		require $this->corePhp();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_core_php_is_parseable_by_php_7_4_syntax_rules(): void {
		// Static guard: no PHP 8-only constructs in the bootstrap file (typed properties, match, enums, readonly, nullsafe, named args, union types).
		$source = (string) file_get_contents( $this->corePhp() );
		foreach ( array( 'match (', 'enum ', 'readonly ', '?->', '): never', 'declare(strict_types' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $source, "core.php must not use '$needle'" );
		}
		$this->assertDoesNotMatchRegularExpression( '/function\s*\([^)]*\b(int|string|bool|array)\|/', $source, 'no union types' );
	}
}
