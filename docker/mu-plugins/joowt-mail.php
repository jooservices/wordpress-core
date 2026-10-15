<?php
/**
 * Plugin Name: JOOWT local mail
 * Description: Routes wp_mail to Mailpit in the local Docker stack. Not part of any theme.
 *
 * @package JOOservices\Docker
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action(
	'phpmailer_init',
	static function ( $phpmailer ): void {
		$phpmailer->isSMTP();
		$phpmailer->Host     = getenv( 'MAIL_HOST' ) ?: 'mailpit'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Port     = (int) ( getenv( 'MAIL_PORT_SMTP' ) ?: 1025 ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPAuth = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
);
