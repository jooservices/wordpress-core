<?php
/**
 * WP-CLI registration.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Admin\Cli;

use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Config\Settings;

/**
 * Registers the `wp joo` commands (only loaded when WP-CLI is running).
 *
 * @internal
 */
final class Commands {

	/**
	 * Registers the commands.
	 *
	 * @param Container $container Container.
	 */
	public static function register( Container $container ): void {
		if ( ! class_exists( '\WP_CLI' ) ) {
			return;
		}
		\WP_CLI::add_command( 'joo settings', new SettingsCommand( $container ) );
		\WP_CLI::add_command( 'joo modules', new ModulesCommand( $container ) );
		\WP_CLI::add_command( 'joo doctor', new DoctorCommand( $container ) );
		/** @var Settings $settings */
		$settings = $container->get( Settings::class );
		\WP_CLI::add_command( 'joo cache', new CacheCommand( $settings->repository() ) );
	}
}
