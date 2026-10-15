<?php
/**
 * Tests tools/coverage-check.php.
 *
 * @package JOOservices
 */

declare(strict_types=1);

namespace JOOservices\Tests\Tools;

use PHPUnit\Framework\TestCase;

final class CoverageCheckTest extends TestCase {

	private string $clover = '';

	protected function setUp(): void {
		$this->clover = sys_get_temp_dir() . '/joo-clover-' . uniqid() . '.xml';
		file_put_contents(
			$this->clover,
			'<?xml version="1.0"?><coverage><project>'
			. '<file name="/x/packages/theme-core/src/A.php"><line num="1" type="stmt" count="1"/><line num="2" type="stmt" count="1"/><line num="3" type="stmt" count="0"/><line num="4" type="stmt" count="1"/></file>'
			. '<file name="/x/other/B.php"><line num="1" type="stmt" count="0"/></file>'
			. '</project></coverage>'
		);
	}

	protected function tearDown(): void {
		@unlink( $this->clover ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	private function run_tool( string $prefix, string $min ): int {
		$command = sprintf( 'php %s %s %s %s 2>&1', escapeshellarg( dirname( __DIR__, 2 ) . '/tools/coverage-check.php' ), escapeshellarg( $this->clover ), escapeshellarg( $prefix ), escapeshellarg( $min ) );
		exec( $command, $output, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		return $code;
	}

	public function test_passes_when_threshold_is_met(): void {
		$this->assertSame( 0, $this->run_tool( 'packages/theme-core/src', '75' ) );
	}

	public function test_fails_when_threshold_is_raised_above_actual(): void {
		$this->assertSame( 1, $this->run_tool( 'packages/theme-core/src', '80' ) );
	}

	public function test_reports_usage_error_for_unknown_prefix(): void {
		$this->assertSame( 2, $this->run_tool( 'nothing/here', '10' ) );
	}
}
