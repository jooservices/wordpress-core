<?php
/**
 * SEO module.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo;

use JOOservices\ThemeCore\Admin\EditorPanels;
use JOOservices\ThemeCore\Admin\SiteHealth;
use JOOservices\ThemeCore\Compat\Detector;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Module\AbstractModule;
use JOOservices\ThemeCore\Performance\FragmentCache;
use JOOservices\ThemeCore\Seo\Breadcrumbs\TrailBuilder;
use JOOservices\ThemeCore\Seo\Schema\Graph;
use JOOservices\ThemeCore\Seo\Schema\GraphPrinter;
use JOOservices\ThemeCore\Seo\Schema\Pieces\ArticlePiece;
use JOOservices\ThemeCore\Seo\Schema\Pieces\BreadcrumbPiece;
use JOOservices\ThemeCore\Seo\Schema\Pieces\OrganizationPiece;
use JOOservices\ThemeCore\Seo\Schema\Pieces\PageUrl;
use JOOservices\ThemeCore\Seo\Schema\Pieces\PersonPiece;
use JOOservices\ThemeCore\Seo\Schema\Pieces\PrimaryImagePiece;
use JOOservices\ThemeCore\Seo\Schema\Pieces\WebPagePiece;
use JOOservices\ThemeCore\Seo\Schema\Pieces\WebSitePiece;
use JOOservices\ThemeCore\Seo\Sitemaps\SitemapTweaks;
use JOOservices\ThemeCore\Support\Logger;

/**
 * Titles, descriptions, canonical, robots, Open Graph, Twitter, hreflang and the plugin yield policy.
 *
 * @internal
 */
final class SeoModule extends AbstractModule {

	/**
	 * Module id.
	 */
	public static function id(): string {
		return 'seo';
	}

	/**
	 * Modules that must boot first.
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array( 'admin' );
	}

	/**
	 * Binds services.
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->singleton( Detector::class, static fn() => new Detector() );
		$container->singleton( YieldPolicy::class, static fn( Container $c ) => new YieldPolicy( $c->get( Settings::class ), $c->get( Detector::class ) ) );
		$container->singleton( TitleBuilder::class, static fn( Container $c ) => new TitleBuilder( $c->get( Settings::class ) ) );
		$container->singleton( DescriptionBuilder::class, static fn() => new DescriptionBuilder() );
		$container->singleton( Canonical::class, static fn() => new Canonical() );
		$container->singleton( RobotsBuilder::class, static fn( Container $c ) => new RobotsBuilder( $c->get( Settings::class ) ) );
		$container->singleton( SocialTags::class, static fn( Container $c ) => new SocialTags( $c->get( Settings::class ) ) );
		$container->singleton( Hreflang::class, static fn( Container $c ) => new Hreflang( $c->get( Manifest::class ) ) );
		$container->singleton(
			HeadPrinter::class,
			static fn( Container $c ) => new HeadPrinter(
				$c->get( TitleBuilder::class ),
				$c->get( DescriptionBuilder::class ),
				$c->get( Canonical::class ),
				$c->get( RobotsBuilder::class ),
				$c->get( SocialTags::class ),
				$c->get( Hreflang::class ),
				$c->get( YieldPolicy::class )
			)
		);
		$this->registerSchema( $container );
	}

	/**
	 * Binds the structured data, breadcrumb, sitemap and HTTP services.
	 *
	 * @param Container $container Container.
	 */
	private function registerSchema( Container $container ): void {
		$container->singleton( TrailBuilder::class, static fn() => new TrailBuilder() );
		$container->singleton( PageUrl::class, static fn( Container $c ) => new PageUrl( $c->get( Canonical::class ) ) );
		$container->singleton(
			Graph::class,
			static fn( Container $c ) => new Graph(
				array(
					new WebSitePiece(),
					new OrganizationPiece( $c->get( Manifest::class ) ),
					new PersonPiece( $c->get( Manifest::class ) ),
					new WebPagePiece( $c->get( PageUrl::class ), $c->get( TitleBuilder::class ) ),
					new ArticlePiece( $c->get( PageUrl::class ), $c->get( DescriptionBuilder::class ), $c->get( Manifest::class ) ),
					new PrimaryImagePiece( $c->get( PageUrl::class ), $c->get( SocialTags::class ) ),
					new BreadcrumbPiece( $c->get( PageUrl::class ), $c->get( TrailBuilder::class ), $c->get( Settings::class ) ),
				),
				$c->get( Logger::class )
			)
		);
		$container->singleton( GraphPrinter::class, static fn( Container $c ) => new GraphPrinter( $c->get( Graph::class ), $c->get( YieldPolicy::class ), $c->get( HeadPrinter::class ) ) );
		$container->singleton( SitemapTweaks::class, static fn( Container $c ) => new SitemapTweaks( $c->get( FragmentCache::class ) ) );
		$container->singleton( HttpSemantics::class, static fn( Container $c ) => new HttpSemantics( $c->get( Logger::class ) ) );
	}

	/**
	 * Adds hooks, editor fields and the Site Health test.
	 */
	public function boot(): void {
		if ( null === $this->container ) {
			return;
		}
		$c = $this->container;
		/** @var Settings $settings */
		$settings = $c->get( Settings::class );

		$this->editorFields( $c->get( EditorPanels::class ) );
		/** @var Hreflang $hreflang */
		$hreflang = $c->get( Hreflang::class );
		$hreflang->hook();

		/** @var SiteHealth $health */
		$health = $c->get( SiteHealth::class );
		$health->addTest( 'joo_seo', __( 'SEO output', 'joo-core' ), array( $this, 'healthTest' ) );

		if ( ! (bool) $settings->get( 'seo.enabled', true ) || ! (bool) $settings->get( 'modules.seo.enabled', true ) ) {
			return;
		}
		/** @var HeadPrinter $printer */
		$printer = $c->get( HeadPrinter::class );
		$printer->hook();
		/** @var GraphPrinter $graph */
		$graph = $c->get( GraphPrinter::class );
		$graph->hook();
		/** @var SitemapTweaks $sitemaps */
		$sitemaps = $c->get( SitemapTweaks::class );
		$sitemaps->hook();
		/** @var HttpSemantics $http */
		$http = $c->get( HttpSemantics::class );
		$http->hook();
	}

	/**
	 * Registers the per-post SEO fields.
	 *
	 * @param EditorPanels $panels Panels service.
	 */
	private function editorFields( EditorPanels $panels ): void {
		$types = array( 'post', 'page' );
		$panels->panel( 'joo-seo', __( 'SEO', 'joo-core' ) );
		$panels->add(
			array(
				'key'        => '_joo_seo_title',
				'type'       => 'string',
				'label'      => __( 'SEO title', 'joo-core' ),
				'help'       => __( 'Up to 70 characters. Empty uses the title template.', 'joo-core' ),
				'panel'      => 'joo-seo',
				'ui'         => 'text',
				'maxLength'  => 120,
				'sanitize'   => 'text',
				'post_types' => $types,
			)
		);
		$panels->add(
			array(
				'key'        => '_joo_seo_description',
				'type'       => 'string',
				'label'      => __( 'Meta description', 'joo-core' ),
				'help'       => __( 'Aim for 120 to 160 characters. Empty uses the excerpt.', 'joo-core' ),
				'panel'      => 'joo-seo',
				'ui'         => 'textarea',
				'maxLength'  => 300,
				'sanitize'   => 'text',
				'post_types' => $types,
			)
		);
		$panels->add(
			array(
				'key'        => '_joo_seo_noindex',
				'type'       => 'boolean',
				'label'      => __( 'Hide from search engines', 'joo-core' ),
				'panel'      => 'joo-seo',
				'ui'         => 'toggle',
				'post_types' => $types,
			)
		);
		$panels->add(
			array(
				'key'        => '_joo_seo_canonical',
				'type'       => 'string',
				'label'      => __( 'Canonical URL', 'joo-core' ),
				'help'       => __( 'Only when this content also exists elsewhere on this site.', 'joo-core' ),
				'panel'      => 'joo-seo',
				'ui'         => 'text',
				'maxLength'  => 300,
				'sanitize'   => 'text',
				'post_types' => $types,
			)
		);
		$panels->add(
			array(
				'key'        => '_joo_seo_og_image',
				'type'       => 'integer',
				'label'      => __( 'Social image (attachment id)', 'joo-core' ),
				'panel'      => 'joo-seo',
				'ui'         => 'number',
				'minimum'    => 0,
				'post_types' => $types,
			)
		);
		$panels->panel( 'joo-language', __( 'Language', 'joo-core' ) );
		$panels->add(
			array(
				'key'        => '_joo_lang',
				'type'       => 'string',
				'label'      => __( 'Content language', 'joo-core' ),
				'help'       => __( 'Language code such as en or vi. Used for hreflang and the html lang attribute.', 'joo-core' ),
				'panel'      => 'joo-language',
				'ui'         => 'text',
				'maxLength'  => 8,
				'sanitize'   => 'text',
				'post_types' => $types,
			)
		);
		$panels->add(
			array(
				'key'        => '_joo_translation_of',
				'type'       => 'integer',
				'label'      => __( 'Translation of (post id)', 'joo-core' ),
				'help'       => __( 'The original post this content translates. Empty when this is the original.', 'joo-core' ),
				'panel'      => 'joo-language',
				'ui'         => 'number',
				'minimum'    => 0,
				'post_types' => $types,
			)
		);
	}

	/**
	 * Site Health test: SEO plugin state.
	 *
	 * @return array<string, mixed>
	 */
	public function healthTest(): array {
		if ( null === $this->container ) {
			return array();
		}
		/** @var Detector $detector */
		$detector = $this->container->get( Detector::class );
		/** @var YieldPolicy $policy */
		$policy  = $this->container->get( YieldPolicy::class );
		$plugins = $detector->seoPlugins();
		$status  = count( $plugins ) > 1 ? 'recommended' : 'good';
		$label   = count( $plugins ) > 1 ? __( 'More than one SEO plugin is active', 'joo-core' ) : __( 'SEO output is consistent', 'joo-core' );
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'SEO', 'joo-core' ),
				'color' => 'good' === $status ? 'blue' : 'orange',
			),
			'description' => '<p>' . esc_html( $policy->describe() ) . '</p>',
			'actions'     => '',
			'test'        => 'joo_seo',
		);
	}
}
