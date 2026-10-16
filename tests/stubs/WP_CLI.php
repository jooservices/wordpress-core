<?php
/**
 * Minimal WP-CLI API shape for PHPStan.
 *
 * @package JOOservices
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * Static-analysis stub for the WP-CLI runtime class.
	 */
	final class WP_CLI {

		/**
		 * Registers a CLI command.
		 *
		 * @param string $name Command name.
		 * @param mixed  $callable Command implementation.
		 * @param array<string, mixed> $args Optional command arguments.
		 */
		public static function add_command( string $name, $callable, array $args = array() ): void {}

		/**
		 * Writes one line.
		 *
		 * @param string $message Message.
		 */
		public static function line( string $message ): void {}

		/**
		 * Writes an error.
		 *
		 * @param string $message Message.
		 * @param bool   $exit Whether to exit.
		 */
		public static function error( string $message, bool $exit = true ): void {}

		/**
		 * Writes a success message.
		 *
		 * @param string $message Message.
		 */
		public static function success( string $message ): void {}

		/**
		 * Writes a log message.
		 *
		 * @param string $message Message.
		 */
		public static function log( string $message ): void {}

		/**
		 * Asks for confirmation.
		 *
		 * @param string $question Prompt.
		 */
		public static function confirm( string $question ): void {}
	}
}
