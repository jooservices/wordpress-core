<?php
/**
 * Assets module.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Assets;

use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Module\AbstractModule;
use JOOservices\ThemeCore\Support\Logger;
use JOOservices\ThemeCore\Support\Paths;

/**
 * Registers the registry, the runtime and the loading policy. Always enabled.
 *
 * @internal
 */
final class AssetsModule extends AbstractModule {

	/**
	 * Module id.
	 */
	public static function id(): string {
		return 'assets';
	}

	/**
	 * Binds services.
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->singleton( AssetRegistry::class, static fn( Container $c ) => new AssetRegistry( $c->get( Logger::class ) ) );
		$container->singleton( CriticalCss::class, static fn() => new CriticalCss() );
		$container->singleton( Preloader::class, static fn( Container $c ) => new Preloader( $c->get( Logger::class ) ) );
		$container->singleton( RuntimeConfig::class, static fn( Container $c ) => new RuntimeConfig( $c->get( Settings::class ), $c->get( AssetRegistry::class ) ) );
		$container->singleton( LoaderPolicy::class, static fn( Container $c ) => new LoaderPolicy( $c->get( Settings::class ) ) );
	}

	/**
	 * Adds the front-end hooks.
	 */
	public function boot(): void {
		if ( null === $this->container ) {
			return;
		}
		/** @var AssetRegistry $registry */
		$registry = $this->container->get( AssetRegistry::class );
		/** @var CriticalCss $critical */
		$critical = $this->container->get( CriticalCss::class );

		$registry->addManifest( 'core', AssetManifest::fromFile( Paths::file( 'resources/build/assets.json' ), Paths::url( 'resources/build' ) ) );
		$registry->setCriticalProbe( array( $critical, 'available' ) );
		$registry->module( 'joo-runtime', 'runtime' );
		$registry->style( 'joo-core', 'core', array( 'defer' => true ) );

		add_action(
			'wp',
			function () use ( $registry, $critical ): void {
				if ( is_admin() || is_feed() || is_embed() ) {
					return;
				}
				$registry->hook();
				$critical->hook();
				if ( null === $this->container ) {
					return;
				}
				/** @var Preloader $preloader */
				$preloader = $this->container->get( Preloader::class );
				$preloader->hook();
				/** @var RuntimeConfig $config */
				$config = $this->container->get( RuntimeConfig::class );
				$config->hook();
				/** @var LoaderPolicy $policy */
				$policy = $this->container->get( LoaderPolicy::class );
				$policy->hook();
			}
		);
	}
}
