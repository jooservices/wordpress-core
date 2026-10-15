<?php
/**
 * Content module.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Content;

use JOOservices\ThemeCore\Admin\EditorPanels;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Content\Rest\ContentRoutes;
use JOOservices\ThemeCore\Module\AbstractModule;
use JOOservices\ThemeCore\Performance\FragmentCache;
use JOOservices\ThemeCore\Support\ReadingTime;

/**
 * Chooses the content provider and registers the public content routes.
 *
 * @internal
 */
final class ContentModule extends AbstractModule {

	/**
	 * Module id.
	 */
	public static function id(): string {
		return 'content';
	}

	/**
	 * Modules that must boot first.
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array( 'perf', 'admin' );
	}

	/**
	 * Binds services. The provider is chosen on first use, after plugins registered their post types.
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->singleton(
			WpContentProvider::class,
			static fn( Container $c ) => new WpContentProvider( $c->get( Settings::class ), $c->get( Manifest::class ), $c->get( FragmentCache::class ) )
		);
		$container->singleton(
			ContentProviderInterface::class,
			static function ( Container $c ): ContentProviderInterface {
				$wanted = (string) $c->get( Manifest::class )->get( 'content.provider', 'wp' );
				return self::wantsWpProvider( $wanted, post_type_exists( 'joo_company' ), (bool) did_action( 'init' ), defined( 'JOO_CONTENT_FILE' ) ) ? $c->get( WpContentProvider::class ) : new NullProvider();
			}
		);
		$container->singleton( ContentRoutes::class, static fn( Container $c ) => new ContentRoutes( $c->get( ContentProviderInterface::class ), $c->get( Settings::class ) ) );
	}

	/**
	 * Whether the WordPress provider is used.
	 *
	 * The container resolves the provider while the theme boots, before `init` registers the companion post types, so a
	 * loaded companion plugin counts as available even when its post type does not exist yet.
	 *
	 * @param string $wanted        Configured provider (manifest `content.provider`).
	 * @param bool   $typeExists    Whether the company post type is registered.
	 * @param bool   $initDone      Whether `init` has run.
	 * @param bool   $pluginLoaded  Whether the companion plugin file is loaded.
	 */
	public static function wantsWpProvider( string $wanted, bool $typeExists, bool $initDone, bool $pluginLoaded ): bool {
		if ( 'wp' !== $wanted ) {
			return false;
		}
		return $typeExists || ( ! $initDone && $pluginLoaded );
	}

	/**
	 * Hooks and editor panel.
	 */
	protected function doBoot(): void {
		if ( null === $this->container ) {
			return;
		}
		$c = $this->container;
		/** @var ContentRoutes $routes */
		$routes = $c->get( ContentRoutes::class );
		$routes->hook();

		// Reading time is computed when a post is saved (and lazily for older posts).
		add_action(
			'save_post',
			static function ( $postId, $post ): void {
				if ( $post instanceof \WP_Post && 'post' === $post->post_type && ! wp_is_post_revision( (int) $postId ) && 'auto-draft' !== $post->post_status ) {
					ReadingTime::forPost( (int) $postId );
				}
			},
			10,
			2
		);

		add_filter(
			'joo/profile/owner_id',
			static function ( $id ) use ( $c ) {
				return (int) $id > 0 ? (int) $id : $c->get( WpContentProvider::class )->ownerId();
			}
		);

		/** @var EditorPanels $panels */
		$panels = $c->get( EditorPanels::class );
		$panels->panel( 'joo-featured', __( 'Featured', 'joo-core' ) );
		$panels->add(
			array(
				'key'        => '_joo_featured',
				'type'       => 'boolean',
				'label'      => __( 'Featured post', 'joo-core' ),
				'help'       => __( 'Shown in the featured slider on the home page.', 'joo-core' ),
				'panel'      => 'joo-featured',
				'ui'         => 'toggle',
				'post_types' => array( 'post' ),
			)
		);
		$panels->add(
			array(
				'key'        => '_joo_featured_order',
				'type'       => 'integer',
				'label'      => __( 'Featured order', 'joo-core' ),
				'help'       => __( 'Lower numbers come first.', 'joo-core' ),
				'panel'      => 'joo-featured',
				'ui'         => 'number',
				'minimum'    => 0,
				'maximum'    => 999,
				'post_types' => array( 'post' ),
			)
		);
	}
}
