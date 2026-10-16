<?php
/**
 * Plugin Name: JOOservices Resume
 * Plugin URI: https://jooservices.com
 * Description: Manage company history, author profiles, topics and featured posts for JOOservices themes.
 * Version: 0.1.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: JOOservices Ltd
 * Copyright: Copyright (c) 2026 JOOservices Ltd.
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: jooservices-resume
 * Domain Path: /languages
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'JOO_RESUME_FILE', __FILE__ );
define( 'JOO_RESUME_DIR', plugin_dir_path( __FILE__ ) );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'JOOservices\\Resume\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$file = JOO_RESUME_DIR . 'src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( \JOOservices\Resume\Plugin::class, 'activate' ) );
add_action( 'plugins_loaded', array( \JOOservices\Resume\Plugin::class, 'boot' ) );
