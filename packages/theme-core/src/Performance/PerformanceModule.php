<?php
/**
 * Performance module.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Performance;

use JOOservices\ThemeCore\Assets\AssetRegistry;
use JOOservices\ThemeCore\Assets\Preloader;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Http\CacheHeaders;
use JOOservices\ThemeCore\Module\AbstractModule;

/**
 * Images, fonts, hints, bloat removal, fragment cache and diagnostics.
 *
 * @internal
 */
final class PerformanceModule extends AbstractModule {

	/**
	 * Module id.
	 */
	public static function id(): string {
		return 'perf';
	}

	/**
	 * Modules that must boot first.
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array( 'assets' );
	}

	/**
	 * Binds services.
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->singleton( Images::class, static fn( Container $c ) => new Images( $c->get( Manifest::class ), $c->get( Preloader::class ) ) );
		$container->singleton( Fonts::class, static fn( Container $c ) => new Fonts( $c->get( Manifest::class ), $c->get( Preloader::class ) ) );
		$container->singleton( ResourceHints::class, static fn( Container $c ) => new ResourceHints( $c->get( Settings::class ) ) );
		$container->singleton( Bloat::class, static fn( Container $c ) => new Bloat( $c->get( Settings::class ) ) );
		$container->singleton( FragmentCache::class, static fn() => new FragmentCache() );
		$container->singleton( CacheHeaders::class, static fn() => new CacheHeaders() );
		$container->singleton( Diagnostics::class, static fn( Container $c ) => new Diagnostics( $c->get( Settings::class ), $c->get( AssetRegistry::class ), $c->get( FragmentCache::class ) ) );
	}

	/**
	 * Adds hooks.
	 */
	protected function doBoot(): void {
		if ( null === $this->container ) {
			return;
		}
		$c = $this->container;
		/** @var Images $images */
		$images = $c->get( Images::class );
		$images->hook();
		/** @var Fonts $fonts */
		$fonts = $c->get( Fonts::class );
		$fonts->hook();
		/** @var ResourceHints $hints */
		$hints = $c->get( ResourceHints::class );
		$hints->hook();
		/** @var Bloat $bloat */
		$bloat = $c->get( Bloat::class );
		$bloat->hook();
		/** @var FragmentCache $cache */
		$cache = $c->get( FragmentCache::class );
		$cache->hook();
		/** @var CacheHeaders $headers */
		$headers = $c->get( CacheHeaders::class );
		$headers->hook();
		/** @var Diagnostics $diagnostics */
		$diagnostics = $c->get( Diagnostics::class );
		$diagnostics->hook();
	}
}
