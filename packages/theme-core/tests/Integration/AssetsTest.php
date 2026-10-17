<?php
/**
 * Assets module (real WordPress).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Assets\AssetManifest;
use JOOservices\ThemeCore\Assets\AssetRegistry;
use JOOservices\ThemeCore\Assets\CriticalCss;
use JOOservices\ThemeCore\Assets\LoaderPolicy;
use JOOservices\ThemeCore\Assets\Preloader;
use JOOservices\ThemeCore\Kernel;

final class AssetsTest extends IntegrationTestCase {

	private const MANIFEST = __DIR__ . '/../Fixtures/manifests/valid.json';

	private string $tmpTheme = '';

	public function set_up(): void {
		parent::set_up();
		Kernel::reset();
		Notices::reset();
		// WordPress keeps printed/enqueued state in globals that the test case does not reset.
		$GLOBALS['wp_styles']  = new \WP_Styles();
		$GLOBALS['wp_scripts'] = new \WP_Scripts();
		$this->tmpTheme        = sys_get_temp_dir() . '/joo-theme-' . uniqid();
		mkdir( $this->tmpTheme . '/assets/critical', 0777, true );
	}

	public function tear_down(): void {
		Kernel::reset();
		Notices::reset();
		exec( 'rm -rf ' . escapeshellarg( $this->tmpTheme ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		parent::tear_down();
	}

	private function boot(): void {
		Kernel::boot( self::MANIFEST );
		do_action( 'after_setup_theme' );
	}

	/**
	 * Runs the front-end hooks for a URL and returns head and footer output.
	 */
	private function render( string $url ): string {
		$this->go_to( $url );
		do_action( 'wp_enqueue_scripts' );
		ob_start();
		do_action( 'wp_head' );
		do_action( 'wp_footer' );
		return (string) ob_get_clean();
	}

	private function registry(): AssetRegistry {
		return Kernel::container()->get( AssetRegistry::class );
	}

	public function test_the_runtime_module_and_core_style_are_enqueued_on_every_front_end_page(): void {
		$this->boot();
		$html  = $this->render( '/' );
		$debug = wp_json_encode(
			array(
				'log'      => Kernel::container()->get( \JOOservices\ThemeCore\Support\Logger::class )->entries(),
				'enqueued' => $this->registry()->debugList(),
			)
		);
		$this->assertStringContainsString( 'id="joo-runtime-js-module"', $html, (string) $debug );
		$this->assertMatchesRegularExpression( '#<script[^>]*src="[^"]*/core/resources/build/js/runtime-[A-Za-z0-9]+\.js"[^>]*>#', $html, (string) $debug );
		$this->assertMatchesRegularExpression( '#<link[^>]*id=\'joo-core-css\'[^>]*href=\'[^\']*/core/resources/build/css/core-[A-Za-z0-9]+\.css\'#', $html, (string) $debug );
		preg_match_all( '#[\'"]([^\'"]*/core/resources/build/[^\'"]+)[\'"]#', $html, $m );
		$this->assertNotEmpty( $m[1] );
		foreach ( $m[1] as $url ) {
			$this->assertStringNotContainsString( 'ver=', $url, 'hashed core assets carry no ver query' );
		}
	}

	public function test_runtime_config_is_valid_json_with_features_and_rest_root(): void {
		$this->boot();
		$html = $this->render( '/' );
		$this->assertSame( 1, preg_match( '#<script type="application/json" id="joo-config">(.*?)</script>#s', $html, $m ) );
		$data = json_decode( $m[1], true );
		$this->assertIsArray( $data );
		$this->assertTrue( $data['features']['lazy'] );
		$this->assertStringEndsWith( '/joo/v1/', $data['rest'] );
		$this->assertSame( 2, strlen( $data['lang'] ) );
	}

	public function test_runtime_config_cannot_break_out_of_the_script_tag(): void {
		add_filter( 'joo/assets/runtime_config', static fn( array $data ) => $data + array( 'evil' => '</script><script>alert(1)</script>' ) );
		$this->boot();
		$html = $this->render( '/' );
		$this->assertStringNotContainsString( '</script><script>alert(1)', $html );
		$this->assertSame( 1, preg_match( '#<script type="application/json" id="joo-config">(.*?)</script>#s', $html, $m ) );
		$this->assertSame( '</script><script>alert(1)</script>', json_decode( $m[1], true )['evil'] );
	}

	public function test_context_conditions_and_lazy_modules(): void {
		$this->boot();
		$registry = $this->registry();
		$registry->module( 'joo-only-single', 'runtime', array( 'when' => 'single' ) );
		$registry->module( 'joo-lazy-demo', 'runtime', array( 'lazy' => true ) );
		$post = self::factory()->post->create();
		$home = $this->render( '/' );
		$this->assertStringNotContainsString( 'joo-only-single-js-module', $home );
		$single = $this->render( get_permalink( $post ) );
		$this->assertStringContainsString( 'joo-only-single-js-module', $single );
		$this->assertStringNotContainsString( 'joo-lazy-demo-js-module', $single, 'lazy modules are never enqueued' );
		$this->assertArrayHasKey( 'runtime', $registry->lazyModules() );
	}

	public function test_a_late_style_is_enqueued_after_the_global_styles(): void {
		$this->boot();
		$this->registry()->style( 'joo-late-demo', 'core', array( 'late' => true ) );
		$this->go_to( '/' );
		do_action( 'wp_enqueue_scripts' );
		$this->assertTrue( wp_style_is( 'joo-late-demo', 'enqueued' ) );
		$queue = array_values( $GLOBALS['wp_styles']->queue );
		$this->assertGreaterThan( (int) array_search( 'joo-core', $queue, true ), (int) array_search( 'joo-late-demo', $queue, true ), 'late styles come after the other styles' );
	}

	public function test_an_asset_missing_from_the_build_manifest_is_skipped_not_fatal(): void {
		$this->boot();
		$this->registry()->module( 'joo-ghost', 'does-not-exist' );
		$html = $this->render( '/' );
		$this->assertStringNotContainsString( 'joo-ghost', $html );
		$messages = array_column( Kernel::container()->get( \JOOservices\ThemeCore\Support\Logger::class )->entries(), 'message' );
		$this->assertNotEmpty( preg_grep( '/does-not-exist/', $messages ) );
	}

	public function test_critical_css_is_inlined_and_the_main_style_moves_to_the_footer(): void {
		add_filter( 'stylesheet_directory', fn() => $this->tmpTheme );
		file_put_contents( $this->tmpTheme . '/assets/critical/home.css', 'body{margin:0}' );
		$this->boot();
		$this->go_to( '/' );
		do_action( 'wp_enqueue_scripts' );
		ob_start();
		do_action( 'wp_head' );
		$head = (string) ob_get_clean();
		$this->assertStringContainsString( '<style id="joo-critical">body{margin:0}</style>', $head );
		$this->assertStringNotContainsString( "id='joo-core-css'", $head, 'the stylesheet is not render-blocking in the head' );
		ob_start();
		do_action( 'wp_footer' );
		$footer = (string) ob_get_clean();
		$this->assertStringContainsString( "id='joo-core-css'", $footer );
		$this->assertStringContainsString( 'fetchpriority="low"', $footer );
	}

	public function test_without_critical_css_the_style_stays_in_the_head(): void {
		add_filter( 'stylesheet_directory', fn() => $this->tmpTheme );
		$this->boot();
		$this->go_to( '/' );
		do_action( 'wp_enqueue_scripts' );
		ob_start();
		do_action( 'wp_head' );
		$head = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'id="joo-critical"', $head );
		$this->assertStringContainsString( "id='joo-core-css'", $head );
	}

	public function test_critical_css_with_a_closing_style_tag_is_never_printed(): void {
		add_filter( 'stylesheet_directory', fn() => $this->tmpTheme );
		file_put_contents( $this->tmpTheme . '/assets/critical/home.css', 'a{}</style><script>alert(1)</script>' );
		$this->boot();
		$this->go_to( '/' );
		ob_start();
		Kernel::container()->get( CriticalCss::class )->print();
		$this->assertSame( '', (string) ob_get_clean() );
	}

	public function test_critical_css_context_keys(): void {
		$this->boot();
		$critical = Kernel::container()->get( CriticalCss::class );
		$post     = self::factory()->post->create();
		$page     = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( '/' );
		$this->assertSame( 'home', $critical->context() );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( 'single', $critical->context() );
		$this->go_to( get_permalink( $page ) );
		$this->assertSame( 'page', $critical->context() );
		$this->go_to( '/?s=hello' );
		$this->assertSame( 'search', $critical->context() );
		$this->go_to( '/?p=999999' );
		$this->assertSame( '404', $critical->context() );
	}

	public function test_preloader_limits_deduplicates_and_escapes(): void {
		$this->boot();
		$preloader = Kernel::container()->get( Preloader::class );
		for ( $i = 1; $i <= 6; $i++ ) {
			$preloader->add(
				"https://example.test/a$i.woff2",
				'font',
				array(
					'type'        => 'font/woff2',
					'crossorigin' => '',
				)
			);
		}
		$preloader->add( 'https://example.test/a1.woff2', 'font' );
		$this->assertCount( 4, $preloader->items() );
		ob_start();
		$preloader->print();
		$html = (string) ob_get_clean();
		$this->assertSame( 4, substr_count( $html, 'rel="preload"' ) );
		$this->assertStringContainsString( 'crossorigin=""', $html );
	}

	public function test_preloader_escapes_hostile_values(): void {
		$this->boot();
		$preloader = Kernel::container()->get( Preloader::class );
		$preloader->add(
			'https://example.test/x.png"><script>alert(1)</script>',
			'image',
			array(
				'imagesrcset' => '"><script>',
				'bad attr'    => 'x',
			)
		);
		ob_start();
		$preloader->print();
		$html = (string) ob_get_clean();
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( 'bad attr', $html );
	}

	public function test_jquery_is_removed_when_nothing_depends_on_it_and_kept_when_something_does(): void {
		$this->boot();
		$policy = Kernel::container()->get( LoaderPolicy::class );
		wp_enqueue_script( 'jquery' );
		$policy->applyJqueryPolicy();
		$this->assertNotContains( 'jquery', wp_scripts()->queue, 'auto removes an unneeded jQuery' );

		wp_enqueue_script( 'jquery' );
		wp_register_script( 'joo-test-plugin', 'https://example.test/plugin.js', array( 'jquery' ), '1', true );
		wp_enqueue_script( 'joo-test-plugin' );
		$policy->applyJqueryPolicy();
		$this->assertContains( 'jquery', wp_scripts()->queue, 'auto keeps jQuery for a dependent script' );
		$this->assertTrue( $policy->somethingNeedsJquery() );
	}

	public function test_jquery_keep_and_remove_modes(): void {
		add_filter( 'joo/settings/value', static fn( $value, $key ) => 'perf.jquery_frontend' === $key ? 'keep' : $value, 10, 2 );
		$this->boot();
		wp_enqueue_script( 'jquery' );
		Kernel::container()->get( LoaderPolicy::class )->applyJqueryPolicy();
		$this->assertContains( 'jquery', wp_scripts()->queue );
		remove_all_filters( 'joo/settings/value' );

		Kernel::reset();
		add_filter( 'joo/settings/value', static fn( $value, $key ) => 'perf.jquery_frontend' === $key ? 'remove' : $value, 10, 2 );
		$this->boot();
		wp_register_script( 'joo-needs-jq', 'https://example.test/x.js', array( 'jquery' ), '1', true );
		wp_enqueue_script( 'joo-needs-jq' );
		Kernel::container()->get( LoaderPolicy::class )->applyJqueryPolicy();
		$this->assertNotContains( 'jquery', wp_scripts()->queue, 'remove always dequeues' );
	}

	public function test_version_query_is_stripped_only_for_joo_handles(): void {
		$this->boot();
		$policy = Kernel::container()->get( LoaderPolicy::class );
		$this->assertSame( 'https://x.test/a.css', $policy->stripVersion( 'https://x.test/a.css?ver=1.2', 'joo-core' ) );
		$this->assertSame( 'https://x.test/a.css?ver=1.2', $policy->stripVersion( 'https://x.test/a.css?ver=1.2', 'other-plugin' ) );
	}

	public function test_asset_manifest_reader(): void {
		$manifest = AssetManifest::fromFile( __DIR__ . '/../../resources/build/assets.json', 'https://x.test/build/' );
		$this->assertTrue( $manifest->has( 'runtime' ) );
		$this->assertMatchesRegularExpression( '#^https://x\.test/build/js/runtime-[A-Za-z0-9]+\.js$#', $manifest->url( 'runtime' ) );
		$this->assertSame( '', $manifest->url( 'nope' ) );
		$this->assertNull( $manifest->info( 'nope' ) );
		$this->assertContains( 'core', $manifest->names() );
		$this->assertSame( array(), ( AssetManifest::fromFile( '/nonexistent.json', 'x' ) )->names() );
	}
}
