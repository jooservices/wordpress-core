<?php
/**
 * Plugin signatures.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat;

/**
 * Static detection data: key => name, constants (first one holds the version), classes, functions, plugin file, capabilities, tier.
 *
 * @internal
 */
final class Signatures {

	/**
	 * All known plugins.
	 *
	 * @return array<string, array{name: string, constants?: list<string>, classes?: list<string>, functions?: list<string>, file?: string, provides: list<string>, tier: int}>
	 */
	public static function all(): array {
		return array(
			'yoast'           => array(
				'name'      => 'Yoast SEO',
				'constants' => array( 'WPSEO_VERSION' ),
				'file'      => 'wordpress-seo/wp-seo.php',
				'provides'  => array( 'seo' ),
				'tier'      => 1,
			),
			'rankmath'        => array(
				'name'      => 'Rank Math',
				'constants' => array( 'RANK_MATH_VERSION' ),
				'file'      => 'seo-by-rank-math/rank-math.php',
				'provides'  => array( 'seo' ),
				'tier'      => 1,
			),
			'aioseo'          => array(
				'name'      => 'All in One SEO',
				'constants' => array( 'AIOSEO_VERSION' ),
				'file'      => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
				'provides'  => array( 'seo' ),
				'tier'      => 1,
			),
			'seopress'        => array(
				'name'      => 'SEOPress',
				'constants' => array( 'SEOPRESS_VERSION' ),
				'file'      => 'wp-seopress/seopress.php',
				'provides'  => array( 'seo' ),
				'tier'      => 1,
			),
			'tsf'             => array(
				'name'      => 'The SEO Framework',
				'constants' => array( 'THE_SEO_FRAMEWORK_VERSION' ),
				'file'      => 'autodescription/autodescription.php',
				'provides'  => array( 'seo' ),
				'tier'      => 2,
			),
			'slim-seo'        => array(
				'name'      => 'Slim SEO',
				'constants' => array( 'SLIM_SEO_VERSION' ),
				'file'      => 'slim-seo/slim-seo.php',
				'provides'  => array( 'seo' ),
				'tier'      => 2,
			),
			'litespeed-cache' => array(
				'name'     => 'LiteSpeed Cache',
				'classes'  => array( 'LiteSpeed\\Core' ),
				'file'     => 'litespeed-cache/litespeed-cache.php',
				'provides' => array( 'cache', 'lazyload', 'critical-css', 'minify' ),
				'tier'     => 1,
			),
			'wp-rocket'       => array(
				'name'      => 'WP Rocket',
				'constants' => array( 'WP_ROCKET_VERSION' ),
				'file'      => 'wp-rocket/wp-rocket.php',
				'provides'  => array( 'cache', 'lazyload', 'critical-css', 'minify' ),
				'tier'      => 1,
			),
			'w3-total-cache'  => array(
				'name'      => 'W3 Total Cache',
				'constants' => array( 'W3TC_VERSION' ),
				'file'      => 'w3-total-cache/w3-total-cache.php',
				'provides'  => array( 'cache', 'minify', 'lazyload' ),
				'tier'      => 1,
			),
			'autoptimize'     => array(
				'name'      => 'Autoptimize',
				'constants' => array( 'AUTOPTIMIZE_PLUGIN_VERSION' ),
				'file'      => 'autoptimize/autoptimize.php',
				'provides'  => array( 'minify', 'critical-css', 'lazyload' ),
				'tier'      => 1,
			),
			'polylang'        => array(
				'name'      => 'Polylang',
				'constants' => array( 'POLYLANG_VERSION' ),
				'file'      => 'polylang/polylang.php',
				'provides'  => array( 'multilingual' ),
				'tier'      => 1,
			),
			'wpml'            => array(
				'name'      => 'WPML',
				'constants' => array( 'ICL_SITEPRESS_VERSION' ),
				'file'      => 'sitepress-multilingual-cms/sitepress.php',
				'provides'  => array( 'multilingual' ),
				'tier'      => 1,
			),
			'translatepress'  => array(
				'name'     => 'TranslatePress',
				'classes'  => array( 'TRP_Translate_Press' ),
				'file'     => 'translatepress-multilingual/index.php',
				'provides' => array( 'multilingual' ),
				'tier'     => 1,
			),
			'cf7'             => array(
				'name'      => 'Contact Form 7',
				'constants' => array( 'WPCF7_VERSION' ),
				'file'      => 'contact-form-7/wp-contact-form-7.php',
				'provides'  => array( 'forms' ),
				'tier'      => 1,
			),
			'wpforms'         => array(
				'name'      => 'WPForms',
				'constants' => array( 'WPFORMS_VERSION' ),
				'file'      => 'wpforms-lite/wpforms.php',
				'provides'  => array( 'forms' ),
				'tier'      => 1,
			),
			'fluentform'      => array(
				'name'      => 'Fluent Forms',
				'constants' => array( 'FLUENTFORM_VERSION' ),
				'file'      => 'fluentform/fluentform.php',
				'provides'  => array( 'forms' ),
				'tier'      => 1,
			),
			'jetpack'         => array(
				'name'      => 'Jetpack',
				'constants' => array( 'JETPACK__VERSION' ),
				'file'      => 'jetpack/jetpack.php',
				'provides'  => array( 'social', 'lazyload' ),
				'tier'      => 1,
			),
			'site-kit'        => array(
				'name'      => 'Site Kit by Google',
				'constants' => array( 'GOOGLESITEKIT_VERSION' ),
				'file'      => 'google-site-kit/google-site-kit.php',
				'provides'  => array( 'analytics' ),
				'tier'      => 1,
			),
			'smush'           => array(
				'name'      => 'Smush',
				'constants' => array( 'WP_SMUSH_VERSION' ),
				'file'      => 'wp-smushit/wp-smush.php',
				'provides'  => array( 'images', 'lazyload' ),
				'tier'      => 1,
			),
			'shortpixel'      => array(
				'name'      => 'ShortPixel Image Optimizer',
				'constants' => array( 'SHORTPIXEL_IMAGE_OPTIMISER_VERSION' ),
				'file'      => 'shortpixel-image-optimiser/wp-shortpixel.php',
				'provides'  => array( 'images', 'lazyload' ),
				'tier'      => 1,
			),
			'ewww'            => array(
				'name'      => 'EWWW Image Optimizer',
				'constants' => array( 'EWWW_IMAGE_OPTIMIZER_VERSION' ),
				'file'      => 'ewww-image-optimizer/ewww-image-optimizer.php',
				'provides'  => array( 'images', 'lazyload' ),
				'tier'      => 1,
			),
			'tablepress'      => array(
				'name'      => 'TablePress',
				'constants' => array( 'TABLEPRESS_ABSPATH' ),
				'classes'   => array( 'TablePress' ),
				'file'      => 'tablepress/tablepress.php',
				'provides'  => array( 'tables' ),
				'tier'      => 1,
			),
			'acf'             => array(
				'name'      => 'Advanced Custom Fields',
				'constants' => array( 'ACF_VERSION' ),
				'classes'   => array( 'ACF' ),
				'file'      => 'advanced-custom-fields/acf.php',
				'provides'  => array( 'fields' ),
				'tier'      => 1,
			),
			'akismet'         => array(
				'name'      => 'Akismet',
				'constants' => array( 'AKISMET_VERSION' ),
				'file'      => 'akismet/akismet.php',
				'provides'  => array( 'spam' ),
				'tier'      => 1,
			),
			'wordfence'       => array(
				'name'      => 'Wordfence',
				'constants' => array( 'WORDFENCE_VERSION' ),
				'file'      => 'wordfence/wordfence.php',
				'provides'  => array( 'security' ),
				'tier'      => 1,
			),
			'woocommerce'     => array(
				'name'      => 'WooCommerce',
				'constants' => array( 'WC_VERSION' ),
				'classes'   => array( 'WooCommerce' ),
				'file'      => 'woocommerce/woocommerce.php',
				'provides'  => array( 'shop' ),
				'tier'      => 3,
			),
			'elementor'       => array(
				'name'      => 'Elementor',
				'constants' => array( 'ELEMENTOR_VERSION' ),
				'file'      => 'elementor/elementor.php',
				'provides'  => array( 'page-builder' ),
				'tier'      => 3,
			),
			'beaver-builder'  => array(
				'name'      => 'Beaver Builder',
				'constants' => array( 'FL_BUILDER_VERSION' ),
				'file'      => 'beaver-builder-lite-version/fl-builder.php',
				'provides'  => array( 'page-builder' ),
				'tier'      => 3,
			),
			'divi'            => array(
				'name'      => 'Divi Builder',
				'constants' => array( 'ET_BUILDER_VERSION' ),
				'provides'  => array( 'page-builder' ),
				'tier'      => 3,
			),
			'bricks'          => array(
				'name'      => 'Bricks',
				'constants' => array( 'BRICKS_VERSION' ),
				'provides'  => array( 'page-builder' ),
				'tier'      => 3,
			),
			'classic-editor'  => array(
				'name'     => 'Classic Editor',
				'classes'  => array( 'Classic_Editor' ),
				'file'     => 'classic-editor/classic-editor.php',
				'provides' => array( 'editor' ),
				'tier'     => 2,
			),
		);
	}
}
