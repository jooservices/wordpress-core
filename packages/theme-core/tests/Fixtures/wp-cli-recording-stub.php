<?php
/**
 * Recording WP-CLI API for isolated integration tests.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace {
	if ( ! class_exists( 'WP_CLI', false ) ) {
		final class WP_CLI {

			/**
			 * Captured command calls.
			 *
			 * @var list<array<mixed>>
			 */
			public static array $calls = array();

			public static function add_command( string $name, $callable, array $args = array() ): void {
				self::$calls[] = array( 'add_command', $name, $callable, $args );
			}

			public static function line( string $message ): void {
				self::$calls[] = array( 'line', $message );
			}

			public static function error( string $message, bool $exit = true ): void {
				self::$calls[] = array( 'error', $message, $exit );
				throw new \RuntimeException( $message );
			}

			public static function success( string $message ): void {
				self::$calls[] = array( 'success', $message );
			}

			public static function confirm( string $question, array $args = array() ): void {
				self::$calls[] = array( 'confirm', $question, $args );
			}
		}
	}
}

namespace WP_CLI\Utils {
	if ( ! function_exists( __NAMESPACE__ . '\\format_items' ) ) {
		/**
		 * Captures the rows that a command asks WP-CLI to format.
		 *
		 * @param string                 $format Output format.
		 * @param list<array<string,mixed>> $items Rows.
		 * @param list<string>           $fields Fields to print.
		 */
		function format_items( string $format, array $items, array $fields ): void {
			$GLOBALS['joo_wp_cli_format_items'][] = array( $format, $items, $fields );
		}
	}
}
