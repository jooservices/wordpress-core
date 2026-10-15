<?php
/**
 * Core logger.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Support;

/**
 * Keeps the last entries in memory and writes to the PHP error log only when logging is enabled.
 *
 * @internal
 */
final class Logger {

	private const MAX_ENTRIES = 50;

	/**
	 * Entries kept for Site Health.
	 *
	 * @var list<array{level: string, message: string, time: int}>
	 */
	private array $entries = array();

	/**
	 * Returns whether lines are written to the error log.
	 *
	 * @var callable
	 */
	private $enabled;

	/**
	 * Creates the logger.
	 *
	 * @param callable|null $enabled Returns true when the log should be written. Default: WP_DEBUG_LOG.
	 */
	public function __construct( ?callable $enabled = null ) {
		$this->enabled = $enabled ?? static fn(): bool => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
	}

	/**
	 * Debug line.
	 *
	 * @param string $message Message.
	 */
	public function debug( string $message ): void {
		$this->log( 'debug', $message );
	}

	/**
	 * Info line.
	 *
	 * @param string $message Message.
	 */
	public function info( string $message ): void {
		$this->log( 'info', $message );
	}

	/**
	 * Warning line.
	 *
	 * @param string $message Message.
	 */
	public function warning( string $message ): void {
		$this->log( 'warning', $message );
	}

	/**
	 * Error line.
	 *
	 * @param string $message Message.
	 */
	public function error( string $message ): void {
		$this->log( 'error', $message );
	}

	/**
	 * Records a line.
	 *
	 * @param string $level   Level.
	 * @param string $message Message.
	 */
	public function log( string $level, string $message ): void {
		$this->entries[] = array(
			'level'   => $level,
			'message' => $message,
			'time'    => time(),
		);
		if ( count( $this->entries ) > self::MAX_ENTRIES ) {
			array_shift( $this->entries );
		}
		if ( ( $this->enabled )() ) {
			error_log( sprintf( '[joo-core] %s: %s', $level, $message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Entries kept in memory (newest last).
	 *
	 * @return list<array{level: string, message: string, time: int}>
	 */
	public function entries(): array {
		return $this->entries;
	}
}
