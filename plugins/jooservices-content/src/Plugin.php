<?php
/**
 * Plugin bootstrap.
 *
 * @package JOOservices\Content
 */

declare(strict_types=1);

namespace JOOservices\Content;

use JOOservices\Content\Meta\Featured;
use JOOservices\Content\Meta\TermMeta;
use JOOservices\Content\Meta\UserMeta;
use JOOservices\Content\PostTypes\Company;

/**
 * Checks the requirements and registers the components. It does not depend on the theme core.
 */
final class Plugin {

	public const VERSION = '0.1.0';
	public const MIN_PHP = '8.1';
	public const MIN_WP  = '6.6';

	/**
	 * Whether the components were registered.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Whether the environment meets the requirements.
	 *
	 * @param string $php PHP version.
	 * @param string $wp  WordPress version.
	 */
	public static function meetsRequirements( string $php = PHP_VERSION, string $wp = '' ): bool {
		$wp = '' === $wp ? (string) get_bloginfo( 'version' ) : $wp;
		return version_compare( $php, self::MIN_PHP, '>=' ) && version_compare( $wp, self::MIN_WP, '>=' );
	}

	/**
	 * Activation: version and a one-time rewrite flush after the CPT exists.
	 */
	public static function activate(): void {
		update_option( 'joo_content_version', self::VERSION );
		update_option( 'joo_content_flush_rewrite', '1' );
	}

	/**
	 * Runs on plugins_loaded.
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		if ( ! self::meetsRequirements() ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>' . esc_html(
						sprintf(
							/* translators: 1: PHP version, 2: WordPress version. */
							__( 'JOOservices Content needs PHP %1$s and WordPress %2$s or newer and stays inactive.', 'jooservices-content' ),
							self::MIN_PHP,
							self::MIN_WP
						)
					) . '</p></div>';
				}
			);
			return;
		}
		self::$booted = true;
		add_action( 'init', array( self::class, 'loadTextDomain' ), 1 );
		add_action( 'init', array( self::class, 'register' ), 5 );
		add_action( 'init', array( self::class, 'maybeFlushRewrites' ), 99 );
	}

	/**
	 * Loads translations.
	 */
	public static function loadTextDomain(): void {
		load_plugin_textdomain( 'jooservices-content', false, dirname( plugin_basename( JOO_CONTENT_FILE ) ) . '/languages' );
	}

	/**
	 * Registers every component.
	 */
	public static function register(): void {
		( new Company() )->hook();
		( new TermMeta() )->hook();
		( new UserMeta() )->hook();
		( new Featured() )->hook();
	}

	/**
	 * Flushes the rewrite rules once after activation.
	 */
	public static function maybeFlushRewrites(): void {
		if ( '1' === get_option( 'joo_content_flush_rewrite' ) ) {
			delete_option( 'joo_content_flush_rewrite' );
			flush_rewrite_rules( false );
		}
	}
}
