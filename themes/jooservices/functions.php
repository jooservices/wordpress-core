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
				echo '<div class="notice notice-error"><p>' . esc_html__( 'JOOservices core is missing from this theme. Reinstall the theme package or run "make link-core".', 'jooservices' ) . '</p></div>';
			}
		}
	);
	return;
}

require_once $joo_core;
\JOOservices\ThemeCore\Kernel::boot( __DIR__ . '/joo-theme.json' );
require_once __DIR__ . '/inc/BuyerOptions.php';
\JOOservices\Jooservices\BuyerOptions::hook();

add_action(
	'after_setup_theme',
	static function (): void {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'automatic-feed-links' );
		register_nav_menus(
			array(
				'primary' => __( 'Primary menu', 'jooservices' ),
				'footer'  => __( 'Footer menu', 'jooservices' ),
			)
		);
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	}
);

// Theme styles: the built CSS is registered with the core asset registry (hashed file names, conditional enqueue).
add_action(
	'joo/assets/register',
	static function ( $registry ): void {
		$registry->addManifest( 'theme', \JOOservices\ThemeCore\Assets\AssetManifest::fromFile( __DIR__ . '/assets/build/assets.json', trailingslashit( get_theme_root_uri() ) . basename( __DIR__ ) . '/assets/build' ) );
		$registry->style(
			'jooservices-theme',
			'theme',
			array(
				'manifest' => 'theme',
				'late'     => true,
			)
		);
		$registry->module( 'jooservices-rail', 'rail', array( 'manifest' => 'theme' ) );
	}
);

// Block editor: same look as the front end.
add_action(
	'after_setup_theme',
	static function (): void {
		add_editor_style( 'assets/editor.css' );
	}
);
