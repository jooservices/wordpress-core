<?php
/**
 * Admin notices.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Admin;

/**
 * Collects notices and prints them for users who can edit theme options. Never prints on the front end.
 *
 * @internal
 */
final class Notices {

	/**
	 * Collected notices.
	 *
	 * @var array<string, array{level: string, message: string}>
	 */
	private static array $notices = array();

	/**
	 * Whether the admin_notices hook is registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Adds a notice (same id replaces the previous one).
	 *
	 * @param string      $level   error|warning|info|success.
	 * @param string      $message Plain text message.
	 * @param string|null $id      Optional stable id.
	 */
	public static function add( string $level, string $message, ?string $id = null ): void {
		self::$notices[ $id ?? md5( $level . $message ) ] = array(
			'level'   => in_array( $level, array( 'error', 'warning', 'info', 'success' ), true ) ? $level : 'info',
			'message' => $message,
		);
		self::register();
	}

	/**
	 * All notices.
	 *
	 * @return array<string, array{level: string, message: string}>
	 */
	public static function all(): array {
		return self::$notices;
	}

	/**
	 * Clears notices (tests).
	 */
	public static function reset(): void {
		self::$notices = array();
	}

	/**
	 * Hooks the printer once.
	 */
	public static function register(): void {
		if ( self::$registered || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$registered = true;
		add_action( 'admin_notices', array( self::class, 'render' ) );
	}

	/**
	 * Prints the notices for authorised users.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		foreach ( self::$notices as $notice ) {
			printf(
				'<div class="notice notice-%1$s"><p><strong>JOOservices:</strong> %2$s</p></div>',
				esc_attr( $notice['level'] ),
				esc_html( $notice['message'] )
			);
		}
	}
}
