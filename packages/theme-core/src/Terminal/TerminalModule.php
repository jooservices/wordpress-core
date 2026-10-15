<?php
/**
 * Terminal module.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Terminal;

use JOOservices\ThemeCore\Assets\AssetRegistry;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Content\WpContentProvider;
use JOOservices\ThemeCore\I18n\Languages;
use JOOservices\ThemeCore\Module\AbstractModule;
use JOOservices\ThemeCore\Performance\FragmentCache;
use JOOservices\ThemeCore\Support\Hooks;
use JOOservices\ThemeCore\Terminal\Rest\FsRoute;

/**
 * File system JSON, its REST route and the lazy terminal bundle.
 *
 * @internal
 */
final class TerminalModule extends AbstractModule {

	/**
	 * Module id.
	 */
	public static function id(): string {
		return 'terminal';
	}

	/**
	 * Modules that must boot first.
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array( 'content', 'i18n', 'assets', 'perf' );
	}

	/**
	 * Binds services.
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->singleton(
			FsBuilder::class,
			static fn( Container $c ) => new FsBuilder( $c->get( Settings::class ), $c->get( FragmentCache::class ), $c->get( Languages::class ), $c->get( WpContentProvider::class ) )
		);
		$container->singleton( FsRoute::class, static fn( Container $c ) => new FsRoute( $c->get( FsBuilder::class ) ) );
	}

	/**
	 * Hooks.
	 */
	protected function doBoot(): void {
		if ( null === $this->container ) {
			return;
		}
		$c = $this->container;
		$c->get( FsRoute::class )->hook();
		/** @var AssetRegistry $assets */
		$assets = $c->get( AssetRegistry::class );
		$assets->module( 'joo-terminal', 'terminal', array( 'lazy' => true ) );
		add_filter(
			'joo/assets/runtime_config',
			static function ( $data ) {
				if ( is_array( $data ) ) {
					$extra                    = Hooks::filter( 'joo/terminal/commands', array() );
					$data['terminalCommands'] = array_values( array_filter( is_array( $extra ) ? $extra : array(), 'is_string' ) );
				}
				return $data;
			}
		);
	}
}
