<?php
/**
 * Admin module: settings page, editor panels, WP-CLI, Site Health.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Admin;

use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Module\AbstractModule;

/**
 * Always enabled.
 *
 * @internal
 */
final class AdminModule extends AbstractModule {

	/**
	 * Module id.
	 */
	public static function id(): string {
		return 'admin';
	}

	/**
	 * Binds admin services.
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->singleton( EditorPanels::class, static fn() => new EditorPanels() );
		$container->singleton( SettingsPage::class, static fn( Container $c ) => new SettingsPage( $c->get( \JOOservices\ThemeCore\Config\Settings::class ), $c->get( \JOOservices\ThemeCore\Config\Manifest::class ) ) );
		$container->singleton( SiteHealth::class, static fn( Container $c ) => new SiteHealth( $c ) );
	}

	/**
	 * Adds hooks.
	 */
	public function boot(): void {
		if ( null === $this->container ) {
			return;
		}
		/** @var EditorPanels $panels */
		$panels = $this->container->get( EditorPanels::class );
		$panels->hook();

		/** @var SiteHealth $health */
		$health = $this->container->get( SiteHealth::class );
		$health->hook();

		if ( is_admin() ) {
			/** @var SettingsPage $page */
			$page = $this->container->get( SettingsPage::class );
			$page->hook();
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Cli\Commands::register( $this->container );
		}
	}
}
