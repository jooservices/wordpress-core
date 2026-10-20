<?php
/**
 * Capability guard for WP-CLI commands.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Admin\Cli;

use JOOservices\ThemeCore\Admin\SettingsPage;

/**
 * Ensures WP-CLI commands are run by a user who can manage theme settings.
 *
 * @internal
 */
final class CapabilityGuard {

	/**
	 * Checks the current user's capability and reports a CLI error when denied.
	 */
	public static function check(): bool {
		if ( current_user_can( SettingsPage::CAPABILITY ) ) {
			return true;
		}

		if ( class_exists( '\WP_CLI' ) ) {
			\WP_CLI::error( __( 'You do not have permission to manage these settings.', 'joo-core' ) );
		}

		return false;
	}
}
