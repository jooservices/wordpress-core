<?php
/**
 * Lazy-load module.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Lazy;

use JOOservices\ThemeCore\Admin\EditorPanels;
use JOOservices\ThemeCore\Assets\AssetRegistry;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Lazy\Rest\FragmentController;
use JOOservices\ThemeCore\Module\AbstractModule;
use JOOservices\ThemeCore\Performance\FragmentCache;

/**
 * Policy, images and iframes, embed facades, deferred fragments, sections and comments.
 *
 * @internal
 */
final class LazyModule extends AbstractModule {

	/**
	 * Module id.
	 */
	public static function id(): string {
		return 'lazy';
	}

	/**
	 * Modules that must boot first.
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array( 'assets', 'perf', 'admin' );
	}

	/**
	 * Binds services.
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->singleton( Policy::class, static fn( Container $c ) => new Policy( $c->get( Settings::class ) ) );
		$container->singleton( FragmentLazy::class, static fn( Container $c ) => new FragmentLazy( $c->get( Policy::class ), $c->get( FragmentCache::class ) ) );
		$container->singleton( ImageLazy::class, static fn( Container $c ) => new ImageLazy( $c->get( Policy::class ) ) );
		$container->singleton( EmbedFacade::class, static fn( Container $c ) => new EmbedFacade( $c->get( Policy::class ), $c->get( Settings::class ) ) );
		$container->singleton( SectionLazy::class, static fn( Container $c ) => new SectionLazy( $c->get( Policy::class ), $c->get( Manifest::class ), $c->get( FragmentLazy::class ) ) );
		$container->singleton( FragmentController::class, static fn( Container $c ) => new FragmentController( $c->get( FragmentLazy::class ) ) );
	}

	/**
	 * Adds hooks and assets.
	 */
	public function boot(): void {
		if ( null === $this->container ) {
			return;
		}
		$c = $this->container;

		/** @var EditorPanels $panels */
		$panels = $c->get( EditorPanels::class );
		$panels->panel( 'joo-lazy', __( 'Lazy loading', 'joo-core' ) );
		$panels->add(
			array(
				'key'        => '_joo_lazy_override',
				'type'       => 'string',
				'label'      => __( 'Lazy loading for this post', 'joo-core' ),
				'help'       => __( 'default follows the site settings; off loads everything immediately; on forces lazy loading.', 'joo-core' ),
				'panel'      => 'joo-lazy',
				'enum'       => array( 'default', 'on', 'off' ),
				'default'    => 'default',
				'post_types' => array( 'post', 'page' ),
			)
		);

		/** @var AssetRegistry $assets */
		$assets = $c->get( AssetRegistry::class );
		$assets->module( 'joo-lazy', 'lazy', array( 'lazy' => true ) );
		$assets->module( 'joo-embed', 'embed', array( 'lazy' => true ) );

		/** @var FragmentController $controller */
		$controller = $c->get( FragmentController::class );
		$controller->hook();

		/** @var SectionLazy $sections */
		$sections = $c->get( SectionLazy::class );
		$sections->hook();

		if ( is_admin() ) {
			return;
		}
		/** @var ImageLazy $images */
		$images = $c->get( ImageLazy::class );
		$images->hook();
		/** @var EmbedFacade $embeds */
		$embeds = $c->get( EmbedFacade::class );
		$embeds->hook();
	}
}
