<?php
/**
 * Compatibility layer and adapters (real WordPress, fake third-party APIs).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Compat\AdapterInterface;
use JOOservices\ThemeCore\Compat\AdapterRegistry;
use JOOservices\ThemeCore\Compat\Adapters\AbstractSeoAdapter;
use JOOservices\ThemeCore\Compat\Adapters\AioseoAdapter;
use JOOservices\ThemeCore\Compat\Adapters\CacheAdapter;
use JOOservices\ThemeCore\Compat\Adapters\CacheAdapters;
use JOOservices\ThemeCore\Compat\Adapters\FormsAdapter;
use JOOservices\ThemeCore\Compat\Adapters\JetpackAdapter;
use JOOservices\ThemeCore\Compat\Adapters\RankMathAdapter;
use JOOservices\ThemeCore\Compat\Adapters\SeoPressAdapter;
use JOOservices\ThemeCore\Compat\Adapters\WooCommerceAdapter;
use JOOservices\ThemeCore\Compat\Adapters\YoastAdapter;
use JOOservices\ThemeCore\Compat\Detector;
use JOOservices\ThemeCore\Compat\Forms;
use JOOservices\ThemeCore\Compat\Multilingual;
use JOOservices\ThemeCore\Compat\Multilingual\AlternatesProvider;
use JOOservices\ThemeCore\Compat\Multilingual\BuiltInAdapter;
use JOOservices\ThemeCore\Compat\Multilingual\PolylangAdapter;
use JOOservices\ThemeCore\Compat\Multilingual\Resolver;
use JOOservices\ThemeCore\Compat\Multilingual\TranslatePressAdapter;
use JOOservices\ThemeCore\Compat\Multilingual\WpmlAdapter;
use JOOservices\ThemeCore\Compat\PageBuilder;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Kernel;
use JOOservices\ThemeCore\Seo\Breadcrumbs\TrailBuilder;
use JOOservices\ThemeCore\Seo\Context;
use JOOservices\ThemeCore\Seo\Sitemaps\SitemapTweaks;
use JOOservices\ThemeCore\Support\Logger;

require_once __DIR__ . '/../Fixtures/Plugins/third-party-api.php';

final class CompatTest extends IntegrationTestCase {

	private const MANIFEST = '/workspace/packages/theme-core/tests/Fixtures/manifests/perf.json';

	public function set_up(): void {
		parent::set_up();
		Kernel::reset();
		Notices::reset();
		$GLOBALS['joo_fake'] = array();
		$this->set_permalink_structure( '/%postname%/' );
		create_initial_taxonomies();
		$GLOBALS['wp_rewrite']->flush_rules();
	}

	public function tear_down(): void {
		Kernel::reset();
		$GLOBALS['joo_fake'] = array();
		parent::tear_down();
	}

	private function boot(): void {
		Kernel::boot( self::MANIFEST );
		do_action( 'after_setup_theme' );
	}

	/**
	 * Detector that reports the listed plugin keys as active (through their first constant).
	 *
	 * @param list<string> $keys Plugin keys.
	 */
	private function detectorWith( array $keys ): Detector {
		return new Detector(
			static function ( string $kind, string $name ) use ( $keys ): ?string {
				foreach ( $keys as $key ) {
					$sig = \JOOservices\ThemeCore\Compat\Signatures::all()[ $key ];
					if ( 'constant' === $kind && in_array( $name, $sig['constants'] ?? array(), true ) ) {
						return '1.0';
					}
					if ( 'class' === $kind && in_array( $name, $sig['classes'] ?? array(), true ) ) {
						return '';
					}
				}
				return null;
			}
		);
	}

	// --- SEO adapters -------------------------------------------------------------------------------

	public function test_seo_adapters_read_the_primary_category_and_noindex_from_plugin_meta(): void {
		$cat   = self::factory()->category->create( array( 'name' => 'Chosen' ) );
		$post  = self::factory()->post->create();
		$d     = $this->detectorWith( array( 'yoast', 'rankmath', 'seopress' ) );
		$yoast = new YoastAdapter( $d );
		update_post_meta( $post, '_yoast_wpseo_primary_category', $cat );
		update_post_meta( $post, '_yoast_wpseo_meta-robots-noindex', '1' );
		$this->assertSame( $cat, $yoast->primaryCategory( $post ) );
		$this->assertTrue( $yoast->noindex( $post ) );

		$rank = new RankMathAdapter( $d );
		update_post_meta( $post, 'rank_math_primary_category', $cat );
		update_post_meta( $post, 'rank_math_robots', array( 'noindex', 'nofollow' ) );
		$this->assertSame( $cat, $rank->primaryCategory( $post ) );
		$this->assertTrue( $rank->noindex( $post ) );

		$press = new SeoPressAdapter( $d );
		update_post_meta( $post, '_seopress_robots_primary_cat', $cat );
		update_post_meta( $post, '_seopress_robots_index', 'yes' );
		$this->assertSame( $cat, $press->primaryCategory( $post ) );
		$this->assertTrue( $press->noindex( $post ) );
		$other = self::factory()->post->create();
		$this->assertNull( $press->primaryCategory( $other ) );
		$this->assertNull( $press->noindex( $other ) );
	}

	public function test_aioseo_adapter_is_safe_when_its_api_is_missing(): void {
		$adapter = new AioseoAdapter( $this->detectorWith( array( 'aioseo' ) ) );
		$post    = self::factory()->post->create();
		$this->assertNull( $adapter->primaryCategory( $post ) );
		$this->assertNull( $adapter->noindex( $post ) );
		$this->assertNull( $adapter->noindexMetaClause() );
	}

	public function test_a_throwing_plugin_api_is_swallowed(): void {
		$adapter = new class( $this->detectorWith( array( 'yoast' ) ) ) extends AbstractSeoAdapter {
			public function key(): string {
				return 'yoast';
			}
			protected function readPrimaryCategory( int $postId ): ?int {
				throw new \RuntimeException( 'plugin changed its API' );
			}
			protected function readNoindex( int $postId ): ?bool {
				throw new \RuntimeException( 'plugin changed its API' );
			}
		};
		$this->assertNull( $adapter->primaryCategory( 1 ) );
		$this->assertNull( $adapter->noindex( 1 ) );
	}

	public function test_the_plugin_primary_category_drives_breadcrumbs_and_noindex_posts_leave_the_sitemap(): void {
		$this->boot();
		$a       = self::factory()->category->create( array( 'name' => 'Alpha' ) );
		$b       = self::factory()->category->create( array( 'name' => 'Beta' ) );
		$post    = self::factory()->post->create(
			array(
				'post_title'    => 'T',
				'post_category' => array( $a, $b ),
			)
		);
		$adapter = new YoastAdapter( $this->detectorWith( array( 'yoast' ) ) );
		$adapter->boot();
		update_post_meta( $post, '_yoast_wpseo_primary_category', $b );
		$this->go_to( get_permalink( $post ) );
		$names = array_map( static fn( $c ) => $c->label, Kernel::container()->get( TrailBuilder::class )->build( Context::fromGlobals() ) );
		$this->assertSame( array( 'Home', 'Beta', 'T' ), $names );

		update_post_meta( $post, '_yoast_wpseo_meta-robots-noindex', '1' );
		$args = Kernel::container()->get( SitemapTweaks::class )->postQuery( array() );
		$this->assertContains( $post, $args['post__not_in'] );
	}

	// --- Cache adapters -----------------------------------------------------------------------------

	public function test_exclusions_add_our_tokens_in_each_filter_shape(): void {
		$list = CacheAdapter::exclude( array( 'other.js' ), 'list' );
		$this->assertContains( 'other.js', $list );
		$this->assertContains( 'joo-runtime', $list );
		$this->assertSame( $list, CacheAdapter::exclude( $list, 'list' ), 'idempotent' );
		$this->assertStringContainsString( 'joo-runtime', (string) CacheAdapter::exclude( 'foo, bar', 'csv' ) );
		$this->assertStringStartsWith( 'foo, bar, ', (string) CacheAdapter::exclude( 'foo, bar', 'csv' ) );
		$this->assertFalse( CacheAdapter::exclude( true, 'tag', array( '<tag data-src="/joo-runtime.js">' ) ) );
		$this->assertTrue( CacheAdapter::exclude( true, 'tag', array( '<tag data-src="/other.js">' ) ) );
	}

	public function test_cache_adapters_register_exclusion_filters_only_when_booted(): void {
		$registry = new AdapterRegistry( $this->detectorWith( array( 'wp-rocket' ) ), new Logger( static fn() => true ), CacheAdapters::build( $this->detectorWith( array( 'wp-rocket' ) ) ) );
		$this->assertFalse( has_filter( 'rocket_exclude_js' ) );
		$registry->bootAll();
		$this->assertSame( array( 'wp-rocket' ), $registry->booted() );
		$this->assertContains( 'joo-runtime', apply_filters( 'rocket_exclude_js', array() ) );
		$this->assertContains( 'joo-runtime', apply_filters( 'rocket_delay_js_exclusions', array() ) );
		$this->assertFalse( has_filter( 'litespeed_optimize_js_excludes' ), 'inactive plugins are not touched' );
	}

	public function test_plugins_are_probed_for_lazy_loading_critical_css_and_minification(): void {
		update_option(
			'wp_rocket_settings',
			array(
				'lazyload'  => 1,
				'async_css' => 1,
				'minify_js' => 0,
			)
		);
		$d       = $this->detectorWith( array( 'wp-rocket' ) );
		$adapter = CacheAdapters::build( $d )[1];
		$this->assertSame( 'wp-rocket', $adapter->key() );
		$this->assertTrue( $adapter->providesLazyload() );
		$this->assertTrue( $adapter->providesCriticalCss() );
		$this->assertFalse( $adapter->minifiesJs() );
		update_option( 'wp_rocket_settings', array() );
		$this->assertFalse( $adapter->providesLazyload() );

		update_option( 'litespeed.conf.media-lazy', 1 );
		$ls = CacheAdapters::build( $this->detectorWith( array( 'litespeed-cache' ) ) )[0];
		$this->assertTrue( $ls->providesLazyload() );
		update_option( 'autoptimize_js', 'on' );
		$ao = CacheAdapters::build( $this->detectorWith( array( 'autoptimize' ) ) )[3];
		$this->assertTrue( $ao->minifiesJs() );
		$this->assertFalse( $ao->providesLazyload() );
	}

	public function test_lazy_loading_and_critical_css_yield_to_the_plugin_that_provides_them(): void {
		update_option(
			'wp_rocket_settings',
			array(
				'lazyload'  => 1,
				'async_css' => 1,
			)
		);
		$registry = new AdapterRegistry( $this->detectorWith( array( 'wp-rocket' ) ), new Logger( static fn() => true ), CacheAdapters::build( $this->detectorWith( array( 'wp-rocket' ) ) ) );
		$registry->bootAll();
		$this->assertSame( 'wp-rocket', $registry->lazyProvider() );
		$this->assertTrue( $registry->criticalCssProvided() );
		update_option( 'wp_rocket_settings', array() );
		$this->assertNull( $registry->lazyProvider() );
		$this->assertFalse( $registry->criticalCssProvided() );
	}

	public function test_purge_is_called_when_settings_or_the_cache_version_change(): void {
		$purged = 0;
		$custom = new CacheAdapter(
			$this->detectorWith( array( 'wp-rocket' ) ),
			'wp-rocket',
			array(
				'purge' => static function () use ( &$purged ): void {
					++$purged;
				},
			)
		);
		add_filter( 'joo/compat/adapters', static fn( $list ) => array_merge( (array) $list, array( $custom ) ) );
		$this->boot();
		update_option( 'joo_cache_version', 7 );
		$this->assertGreaterThanOrEqual( 1, $purged );
		$before = $purged;
		$option = Kernel::container()->get( Settings::class )->repository()->optionName();
		update_option( $option, array( 'lazy.eager_count' => 3 ) );
		update_option( $option, array( 'lazy.eager_count' => 4 ) );
		$this->assertGreaterThan( $before, $purged );
	}

	public function test_a_failing_purge_does_not_break_anything(): void {
		$adapter = new CacheAdapter(
			$this->detectorWith( array( 'wp-rocket' ) ),
			'wp-rocket',
			array(
				'purge' => static function (): void {
					throw new \RuntimeException( 'boom' );
				},
			)
		);
		$adapter->purge();
		$this->assertTrue( true );
	}

	public function test_purge_actions_of_real_plugins_are_fired(): void {
		$fired = 0;
		add_action(
			'litespeed_purge_all',
			static function () use ( &$fired ): void {
				++$fired;
			}
		);
		CacheAdapters::build( $this->detectorWith( array( 'litespeed-cache' ) ) )[0]->purge();
		$this->assertSame( 1, $fired );
	}

	public function test_critical_css_is_skipped_when_another_plugin_generates_it(): void {
		$this->boot();
		add_filter( 'joo/perf/skip_critical_css', '__return_true' );
		$this->assertSame( '', Kernel::container()->get( \JOOservices\ThemeCore\Assets\CriticalCss::class )->css() );
	}

	// --- Forms, Jetpack, WooCommerce, builders -----------------------------------------------------

	public function test_contact_form_7_assets_load_only_on_pages_with_a_form(): void {
		$adapter = new FormsAdapter( $this->detectorWith( array( 'cf7' ) ) );
		$adapter->boot();
		$plain = self::factory()->post->create( array( 'post_content' => 'Hello' ) );
		$form  = self::factory()->post->create( array( 'post_content' => 'Write [contact-form-7 id="1"] now' ) );
		$this->go_to( get_permalink( $plain ) );
		$this->assertFalse( apply_filters( 'wpcf7_load_js', true ) );
		$this->assertFalse( apply_filters( 'wpcf7_load_css', true ) );
		$this->go_to( get_permalink( $form ) );
		$this->assertTrue( apply_filters( 'wpcf7_load_js', true ) );
		add_filter( 'joo/compat/forms_conditional', '__return_false' );
		$this->go_to( get_permalink( $plain ) );
		$this->assertTrue( apply_filters( 'wpcf7_load_js', true ), 'conditional loading can be switched off' );
		$this->assertTrue( Forms::contentHasForm( '<!-- wp:wpforms/form-selector {} /-->' ) );
		$this->assertFalse( Forms::contentHasForm( '<p>no form</p>' ) );
	}

	public function test_jetpack_open_graph_is_switched_off_while_core_prints_seo(): void {
		$this->boot();
		$settings = Kernel::container()->get( Settings::class );
		$prints   = true;
		$adapter  = new JetpackAdapter(
			$this->detectorWith( array( 'jetpack' ) ),
			$settings,
			static function () use ( &$prints ): bool {
				return $prints;
			}
		);
		$adapter->boot();
		$this->assertFalse( apply_filters( 'jetpack_enable_open_graph', true ) );
		$prints = false;
		$this->assertTrue( apply_filters( 'jetpack_enable_open_graph', true ), 'Jetpack keeps its tags when core yields' );
		$this->assertFalse( $adapter->providesLazyload() );
	}

	public function test_woocommerce_utility_pages_are_noindex_not_lazy_and_not_prefetched(): void {
		$adapter = new WooCommerceAdapter( $this->detectorWith( array( 'woocommerce' ) ) );
		$adapter->boot();
		$GLOBALS['joo_fake']['woo_page'] = false;
		$this->assertTrue( $adapter->lazy( true, 'fragment' ) );
		$GLOBALS['joo_fake']['woo_page'] = true;
		$this->assertFalse( $adapter->lazy( true, 'fragment' ) );
		$this->assertTrue( $adapter->lazy( true, 'image' ), 'images stay lazy' );
		$this->assertSame(
			array(
				'noindex' => true,
				'follow'  => true,
			),
			$adapter->robots( array( 'index' => true ) )
		);
		$paths = $adapter->speculation( array( '/wp-admin/*' ) );
		$this->assertContains( '/wp-admin/*', $paths );
		$this->assertContains( '/cart/*', $paths );
		$this->assertContains( '/checkout/*', $paths );
		$this->assertContains( '/myaccount/*', $paths );
		$this->assertSame(
			array( 'index' => true ),
			( function () use ( $adapter ) {
				$GLOBALS['joo_fake']['woo_page'] = false;
				return $adapter->robots( array( 'index' => true ) );
			} )()
		);
	}

	public function test_page_builder_pages_are_detected_from_post_meta(): void {
		$post = self::factory()->post->create();
		$this->assertFalse( PageBuilder::isBuilderPage( $post ) );
		foreach ( array(
			'_elementor_edit_mode'   => 'builder',
			'_fl_builder_enabled'    => '1',
			'_et_pb_use_builder'     => 'on',
			'_bricks_page_content_2' => 'x',
		) as $key => $value ) {
			update_post_meta( $post, $key, $value );
			$this->assertTrue( PageBuilder::isBuilderPage( $post ), $key );
			delete_post_meta( $post, $key );
		}
		update_post_meta( $post, '_elementor_edit_mode', 'something-else' );
		$this->assertFalse( PageBuilder::isBuilderPage( $post ) );
	}

	// --- Registry, report and isolation -------------------------------------------------------------

	public function test_a_failing_adapter_is_isolated_and_reported(): void {
		$bad      = new class( $this->detectorWith( array( 'yoast' ) ) ) extends AbstractSeoAdapter {
			public function key(): string {
				return 'yoast';
			}
			public function boot(): void {
				throw new \RuntimeException( 'adapter exploded' );
			}
			protected function readPrimaryCategory( int $postId ): ?int {
				return null;
			}
			protected function readNoindex( int $postId ): ?bool {
				return null;
			}
		};
		$logger   = new Logger( static fn() => true );
		$registry = new AdapterRegistry( $this->detectorWith( array( 'yoast', 'rankmath' ) ), $logger, array( $bad, new RankMathAdapter( $this->detectorWith( array( 'rankmath' ) ) ) ) );
		$registry->bootAll();
		$this->assertSame( array( 'rankmath' ), $registry->booted() );
		$rows = array_column( $registry->report(), 'adapter', 'label' );
		$this->assertSame( 'failed', $rows['Yoast SEO'] );
		$this->assertSame( 'active', $rows['Rank Math'] );
		$this->assertNotEmpty( array_filter( $logger->entries(), static fn( $e ) => str_contains( wp_json_encode( $e ), 'adapter exploded' ) ) );
	}

	public function test_the_compatibility_report_lists_detected_plugins(): void {
		$this->boot();
		$rows = apply_filters( 'joo/compat/report', array() );
		$this->assertIsArray( $rows );
		foreach ( $rows as $row ) {
			$this->assertArrayHasKey( 'label', $row );
			$this->assertContains( $row['adapter'], array( 'active', 'failed', 'none' ) );
		}
		$names = array_column( $rows, 'label' );
		$this->assertNotContains( 'Yoast SEO', $names );
	}

	public function test_adapters_can_be_added_and_replaced_with_the_filter(): void {
		$custom = new class() implements AdapterInterface {
			public int $boots = 0;
			public function key(): string {
				return 'custom';
			}
			public function supports(): bool {
				return true;
			}
			public function boot(): void {
				++$this->boots;
			}
			public function capabilities(): array {
				return array( 'x' );
			}
			public function notice(): string {
				return 'custom';
			}
		};
		add_filter( 'joo/compat/adapters', static fn( $list ) => array_merge( (array) $list, array( $custom ) ) );
		$this->boot();
		$this->assertSame( 1, $custom->boots );
		$this->assertContains( 'custom', Kernel::container()->get( AdapterRegistry::class )->booted() );
	}

	// --- Multilingual -------------------------------------------------------------------------------

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function implementations(): array {
		return array(
			'builtin'        => array( 'builtin' ),
			'polylang'       => array( 'polylang' ),
			'wpml'           => array( 'wpml' ),
			'translatepress' => array( 'translatepress' ),
		);
	}

	private function implementation( string $name ): Multilingual {
		$manifest = new Manifest(
			array(
				'i18n' => array(
					'default'   => 'en',
					'languages' => array( 'en', 'vi' ),
				),
			)
		);
		switch ( $name ) {
			case 'polylang':
				$GLOBALS['joo_fake']['pll_languages'] = array(
					'en' => array(
						'slug'         => 'en',
						'locale'       => 'en_US',
						'name'         => 'English',
						'url'          => 'https://example.test/en/',
						'current_lang' => true,
					),
					'vi' => array(
						'slug'         => 'vi',
						'locale'       => 'vi',
						'name'         => 'Tiếng Việt',
						'url'          => 'https://example.test/vi/',
						'current_lang' => false,
					),
				);
				return new PolylangAdapter( $this->detectorWith( array( 'polylang' ) ) );
			case 'wpml':
				add_filter(
					'wpml_active_languages',
					static fn() => array(
						'en' => array(
							'code'           => 'en',
							'default_locale' => 'en_US',
							'native_name'    => 'English',
							'url'            => 'https://example.test/en/',
							'active'         => 1,
						),
						'vi' => array(
							'code'           => 'vi',
							'default_locale' => 'vi',
							'native_name'    => 'Tiếng Việt',
							'url'            => 'https://example.test/vi/',
							'active'         => 0,
						),
					)
				);
				add_filter( 'wpml_current_language', static fn() => 'en' );
				add_filter( 'wpml_default_language', static fn() => 'en' );
				add_filter( 'wpml_permalink', static fn( $url, $code ) => 'https://example.test/' . $code . '/', 10, 2 );
				return new WpmlAdapter( $this->detectorWith( array( 'wpml' ) ) );
			case 'translatepress':
				update_option(
					'trp_settings',
					array(
						'translation-languages' => array( 'en_US', 'vi' ),
						'default-language'      => 'en_US',
					)
				);
				$GLOBALS['TRP_LANGUAGE'] = 'en_US'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
				return new TranslatePressAdapter( $this->detectorWith( array( 'translatepress' ) ) );
			default:
				return new BuiltInAdapter( $manifest );
		}
	}

	/**
	 * @dataProvider implementations
	 */
	public function test_every_implementation_satisfies_the_multilingual_contract( string $name ): void {
		$impl = $this->implementation( $name );
		$this->assertTrue( $impl->active() );
		$langs = $impl->languages();
		$this->assertGreaterThanOrEqual( 2, count( $langs ) );
		$codes = array();
		foreach ( $langs as $lang ) {
			$this->assertInstanceOf( Multilingual\Lang::class, $lang );
			$this->assertMatchesRegularExpression( '/^[a-z]{2}(-[A-Z]{2})?$/', $lang->code );
			$this->assertNotSame( '', $lang->name );
			$this->assertNotSame( '', $lang->url );
			$codes[] = $lang->code;
		}
		$this->assertNotEmpty( preg_grep( '/^en/', $codes ) );
		$this->assertContains( 'vi', $codes );
		$this->assertContains( $impl->currentLanguage(), $codes );
		$this->assertStringStartsWith( 'en', $impl->defaultLanguage() );
		$this->assertStringStartsWith( 'http', $impl->homeUrl( 'vi' ) );
		$this->assertIsArray( $impl->translations( 1 ) );
		$this->assertIsArray( $impl->termTranslations( 1, 'category' ) );
		$this->assertCount( 1, array_filter( $langs, static fn( $l ) => $l->isCurrent ) );
	}

	public function test_inactive_plugin_adapters_return_nothing_and_never_fatal(): void {
		$none = new Detector( static fn(): ?string => null );
		foreach ( array( new PolylangAdapter( $none ), new WpmlAdapter( $none ), new TranslatePressAdapter( $none ) ) as $impl ) {
			$this->assertFalse( $impl->active() );
			$this->assertSame( array(), $impl->languages() );
		}
	}

	public function test_built_in_translations_group_by_the_source_post(): void {
		$en = self::factory()->post->create();
		$vi = self::factory()->post->create();
		update_post_meta( $en, '_joo_lang', 'en' );
		update_post_meta( $vi, '_joo_lang', 'vi' );
		update_post_meta( $vi, '_joo_translation_of', $en );
		$impl = $this->implementation( 'builtin' );
		$this->assertSame(
			array(
				'en' => $en,
				'vi' => $vi,
			),
			$impl->translations( $vi )
		);
		$this->assertSame(
			array(
				'en' => $en,
				'vi' => $vi,
			),
			$impl->translations( $en )
		);
	}

	public function test_resolver_prefers_an_active_plugin_and_falls_back_to_the_built_in_tagging(): void {
		$builtin = new BuiltInAdapter( new Manifest( array( 'i18n' => array( 'languages' => array( 'en', 'vi' ) ) ) ) );
		$none    = new Detector( static fn(): ?string => null );
		$r1      = new Resolver( array( new PolylangAdapter( $none ), new WpmlAdapter( $none ) ), $builtin );
		$this->assertSame( $builtin, $r1->get() );
		$this->assertFalse( $r1->isPlugin() );
		$wpml = $this->implementation( 'wpml' );
		$r2   = new Resolver( array( new PolylangAdapter( $none ), $wpml ), $builtin );
		$this->assertSame( $wpml, $r2->get() );
		$this->assertTrue( $r2->isPlugin() );
	}

	public function test_a_plugin_supplies_the_hreflang_alternates(): void {
		$builtin  = new BuiltInAdapter( new Manifest() );
		$wpml     = $this->implementation( 'wpml' );
		$provider = new AlternatesProvider( new Resolver( array( $wpml ), $builtin ) );
		$map      = $provider->alternates( null, new Context( 'front' ) );
		$this->assertSame(
			array(
				'en' => 'https://example.test/en/',
				'vi' => 'https://example.test/vi/',
			),
			$map
		);
		$this->assertSame( array( 'x' => 'y' ), $provider->alternates( array( 'x' => 'y' ), new Context( 'front' ) ), 'earlier filters win' );
		$none = new AlternatesProvider( new Resolver( array(), $builtin ) );
		$this->assertNull( $none->alternates( null, new Context( 'front' ) ) );
	}
}
