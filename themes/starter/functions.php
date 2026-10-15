<?php
/**
 * Theme bootstrap: loads the JOOservices core bundled in this theme and boots it from the manifest.
 *
 * @package JOOservices
 */

declare(strict_types=1);

$joo_core = __DIR__ . '/core/core.php';
if ( ! is_readable( $joo_core ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( current_user_can( 'edit_theme_options' ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'JOOservices core is missing from this theme. Reinstall the theme package or run "make link-core".', 'starter' ) . '</p></div>';
			}
		}
	);
	return;
}

require_once $joo_core;
\JOOservices\ThemeCore\Kernel::boot( __DIR__ . '/joo-theme.json' );

add_action(
	'after_setup_theme',
	static function (): void {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
		register_nav_menus(
			array(
				'primary' => __( 'Primary menu', 'starter' ),
				'footer'  => __( 'Footer menu', 'starter' ),
			)
		);
	}
);
