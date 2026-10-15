<?php
/**
 * Requirements tests.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Support;

use JOOservices\ThemeCore\Support\Requirements;
use PHPUnit\Framework\TestCase;

final class RequirementsTest extends TestCase {

	/**
	 * @return list<string>
	 */
	private function codes( array $problems ): array {
		return array_column( $problems, 'code' );
	}

	public function test_a_supported_environment_has_no_problems(): void {
		$this->assertSame( array(), Requirements::check( '8.3.0', '6.6.0', array( 'json', 'mbstring' ) ) );
	}

	public function test_old_php_is_reported(): void {
		$this->assertSame( array( 'php' ), $this->codes( Requirements::check( '8.0.30', '6.6.0', array( 'json', 'mbstring' ) ) ) );
	}

	public function test_old_wordpress_is_reported(): void {
		$this->assertSame( array( 'wp' ), $this->codes( Requirements::check( '8.3.0', '6.5.9', array( 'json', 'mbstring' ) ) ) );
	}

	public function test_missing_extensions_are_reported(): void {
		$this->assertSame( array( 'ext-json', 'ext-mbstring' ), $this->codes( Requirements::check( '8.3.0', '6.6.0', array() ) ) );
	}

	public function test_unknown_wordpress_version_is_not_a_problem(): void {
		$this->assertSame( array(), Requirements::check( '8.3.0', '', array( 'json', 'mbstring' ) ) );
	}

	public function test_messages_name_the_found_version(): void {
		$problems = Requirements::check( '7.4.1', '6.6.0', array( 'json', 'mbstring' ) );
		$this->assertStringContainsString( '7.4.1', $problems[0]['message'] );
	}

	public function test_runtime_defaults_pass_on_this_machine(): void {
		$this->assertNotContains( 'php', $this->codes( Requirements::check( null, '6.6.0' ) ) );
	}
}
