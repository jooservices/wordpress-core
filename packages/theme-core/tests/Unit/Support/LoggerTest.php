<?php
/**
 * Logger tests.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

// phpcs:disable WordPress.PHP.IniSet.Risky -- the test redirects error_log to a temp file.

namespace JOOservices\ThemeCore\Tests\Unit\Support;

use JOOservices\ThemeCore\Support\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase {

	public function test_entries_are_kept_in_memory_with_levels(): void {
		$logger = new Logger( static fn(): bool => false );
		$logger->debug( 'd' );
		$logger->info( 'i' );
		$logger->warning( 'w' );
		$logger->error( 'e' );
		$this->assertSame( array( 'debug', 'info', 'warning', 'error' ), array_column( $logger->entries(), 'level' ) );
	}

	public function test_only_the_last_fifty_entries_are_kept(): void {
		$logger = new Logger( static fn(): bool => false );
		for ( $i = 1; $i <= 60; $i++ ) {
			$logger->info( "m$i" );
		}
		$entries = $logger->entries();
		$this->assertCount( 50, $entries );
		$this->assertSame( 'm11', $entries[0]['message'] );
		$this->assertSame( 'm60', $entries[49]['message'] );
	}

	public function test_nothing_is_written_to_the_error_log_when_disabled(): void {
		$file = tempnam( sys_get_temp_dir(), 'joo-log' );
		$old  = ini_set( 'error_log', (string) $file );
		( new Logger( static fn(): bool => false ) )->error( 'secret' );
		ini_set( 'error_log', (string) $old );
		$this->assertSame( '', (string) file_get_contents( (string) $file ) );
		unlink( (string) $file );
	}

	public function test_lines_are_written_when_enabled(): void {
		$file = tempnam( sys_get_temp_dir(), 'joo-log' );
		$old  = ini_set( 'error_log', (string) $file );
		( new Logger( static fn(): bool => true ) )->error( 'visible' );
		ini_set( 'error_log', (string) $old );
		$this->assertStringContainsString( '[joo-core] error: visible', (string) file_get_contents( (string) $file ) );
		unlink( (string) $file );
	}
}
