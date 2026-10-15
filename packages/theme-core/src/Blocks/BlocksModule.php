<?php
/**
 * Blocks module.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks;

use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Module\AbstractModule;

/**
 * Dynamic block library foundation: registrar, patterns, colour scheme and the editor bundle.
 *
 * @internal
 */
final class BlocksModule extends AbstractModule {

	/**
	 * Module id.
	 */
	public static function id(): string {
		return 'blocks';
	}

	/**
	 * Modules that must boot first.
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array( 'assets', 'content' );
	}

	/**
	 * Binds services.
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->singleton( BlockRegistrar::class, static fn( Container $c ) => new BlockRegistrar( $c ) );
		$container->singleton( PatternLoader::class, static fn( Container $c ) => new PatternLoader( $c->get( Manifest::class ) ) );
		$container->singleton( ThemeScheme::class, static fn( Container $c ) => new ThemeScheme( $c->get( Manifest::class ) ) );
		$container->singleton( EditorBundle::class, static fn( Container $c ) => new EditorBundle( $c->get( BlockRegistrar::class ) ) );
	}

	/**
	 * Hooks.
	 */
	public function boot(): void {
		if ( null === $this->container ) {
			return;
		}
		$c = $this->container;
		$c->get( BlockRegistrar::class )->hook();
		$c->get( PatternLoader::class )->hook();
		$c->get( ThemeScheme::class )->hook();
		$c->get( EditorBundle::class )->hook();
	}
}
