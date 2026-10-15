<?php
/**
 * Compatibility module.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat;

use JOOservices\ThemeCore\Compat\Adapters\AioseoAdapter;
use JOOservices\ThemeCore\Compat\Adapters\CacheAdapters;
use JOOservices\ThemeCore\Compat\Adapters\FlagAdapter;
use JOOservices\ThemeCore\Compat\Adapters\FormsAdapter;
use JOOservices\ThemeCore\Compat\Adapters\JetpackAdapter;
use JOOservices\ThemeCore\Compat\Adapters\RankMathAdapter;
use JOOservices\ThemeCore\Compat\Adapters\SeoPressAdapter;
use JOOservices\ThemeCore\Compat\Adapters\WooCommerceAdapter;
use JOOservices\ThemeCore\Compat\Adapters\YoastAdapter;
use JOOservices\ThemeCore\Compat\Multilingual\AlternatesProvider;
use JOOservices\ThemeCore\Compat\Multilingual\BuiltInAdapter;
use JOOservices\ThemeCore\Compat\Multilingual\PolylangAdapter;
use JOOservices\ThemeCore\Compat\Multilingual\Resolver;
use JOOservices\ThemeCore\Compat\Multilingual\TranslatePressAdapter;
use JOOservices\ThemeCore\Compat\Multilingual\WpmlAdapter;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Module\AbstractModule;
use JOOservices\ThemeCore\Seo\YieldPolicy;
use JOOservices\ThemeCore\Support\Logger;

/**
 * Plugin detection, adapters and the multilingual abstraction.
 *
 * @internal
 */
final class CompatModule extends AbstractModule {

	/**
	 * Module id.
	 */
	public static function id(): string {
		return 'compat';
	}

	/**
	 * Binds services.
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->singleton( Detector::class, static fn() => new Detector() );
		$container->singleton( BuiltInAdapter::class, static fn( Container $c ) => new BuiltInAdapter( $c->get( Manifest::class ) ) );
		$container->singleton(
			Resolver::class,
			static fn( Container $c ) => new Resolver(
				array( new PolylangAdapter( $c->get( Detector::class ) ), new WpmlAdapter( $c->get( Detector::class ) ), new TranslatePressAdapter( $c->get( Detector::class ) ) ),
				$c->get( BuiltInAdapter::class )
			)
		);
		$container->singleton(
			AdapterRegistry::class,
			static function ( Container $c ): AdapterRegistry {
				$d = $c->get( Detector::class );
				/** @var Settings $settings */
				$settings = $c->get( Settings::class );
				$adapters = array_merge(
					array( new YoastAdapter( $d ), new RankMathAdapter( $d ), new AioseoAdapter( $d ), new SeoPressAdapter( $d ) ),
					CacheAdapters::build( $d ),
					array(
						new FormsAdapter( $d ),
						new WooCommerceAdapter( $d ),
						new JetpackAdapter(
							$d,
							$settings,
							static fn(): bool => $c->has( YieldPolicy::class ) && ! $c->get( YieldPolicy::class )->shouldYield()
						),
						new FlagAdapter( $d, 'site-kit', __( 'No changes needed: Site Kit tags are left alone and are not counted in the script budgets.', 'joo-core' ) ),
						new FlagAdapter( $d, 'tablepress', __( 'No changes needed: tables use the theme tokens through the table block styles.', 'joo-core' ) ),
						new FlagAdapter( $d, 'acf', __( 'Use Compat\\Acf::field() in templates; it is guarded against ACF being inactive.', 'joo-core' ) ),
						new FlagAdapter( $d, 'akismet', __( 'No changes needed: the comment form posts normally, also when comments are lazy-loaded.', 'joo-core' ) ),
						new FlagAdapter( $d, 'wordfence', __( 'If the firewall blocks the REST route joo/v1, allow-list /wp-json/joo/v1/.', 'joo-core' ) ),
						new FlagAdapter( $d, 'wpforms', __( 'WPForms loads its assets only where a form is present.', 'joo-core' ) ),
						new FlagAdapter( $d, 'fluentform', __( 'Fluent Forms loads its assets only where a form is present.', 'joo-core' ) ),
						new FlagAdapter( $d, 'classic-editor', __( 'Supported: the SEO and language fields have classic meta boxes.', 'joo-core' ) ),
						new FlagAdapter( $d, 'elementor', __( 'Tier 3: use the Canvas page template; core content filters skip builder pages.', 'joo-core' ) ),
						new FlagAdapter( $d, 'beaver-builder', __( 'Tier 3: use the Canvas page template; core content filters skip builder pages.', 'joo-core' ) ),
						new FlagAdapter( $d, 'divi', __( 'Tier 3: use the Canvas page template; core content filters skip builder pages.', 'joo-core' ) ),
						new FlagAdapter( $d, 'bricks', __( 'Tier 3: use the Canvas page template; core content filters skip builder pages.', 'joo-core' ) ),
					)
				);
				return new AdapterRegistry( $d, $c->get( Logger::class ), $adapters );
			}
		);
	}

	/**
	 * Boots the adapters and connects them to the core hooks.
	 */
	public function boot(): void {
		if ( null === $this->container ) {
			return;
		}
		$c = $this->container;
		/** @var AdapterRegistry $registry */
		$registry = $c->get( AdapterRegistry::class );
		$registry->bootAll();

		add_filter( 'joo/lazy/provider', static fn( $current ) => null !== $current ? $current : $registry->lazyProvider() );
		add_filter( 'joo/perf/skip_critical_css', static fn( $skip ) => (bool) $skip || $registry->criticalCssProvided() );
		add_filter(
			'joo/compat/report',
			static fn( $rows ) => array_merge( is_array( $rows ) ? $rows : array(), $registry->report() )
		);

		/** @var Settings $settings */
		$settings = $c->get( Settings::class );
		$purge    = static function () use ( $registry ): void {
			$registry->purgeAll();
		};
		add_action( 'update_option_' . $settings->repository()->optionName(), $purge );
		add_action( 'add_option_' . $settings->repository()->optionName(), $purge );
		add_action( 'update_option_joo_cache_version', $purge );
		add_action( 'add_option_joo_cache_version', $purge );

		/** @var AlternatesProvider $alternates */
		$alternates = new AlternatesProvider( $c->get( Resolver::class ) );
		$alternates->hook();
	}
}
