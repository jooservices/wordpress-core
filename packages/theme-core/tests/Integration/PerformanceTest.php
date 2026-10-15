<?php
/**
 * Performance module (real WordPress).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\Factories\AttachmentFactory;
use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Assets\Preloader;
use JOOservices\ThemeCore\Http\CacheHeaders;
use JOOservices\ThemeCore\Kernel;
use JOOservices\ThemeCore\Performance\Bloat;
use JOOservices\ThemeCore\Performance\Diagnostics;
use JOOservices\ThemeCore\Performance\Fonts;
use JOOservices\ThemeCore\Performance\FragmentCache;
use JOOservices\ThemeCore\Performance\Images;
use JOOservices\ThemeCore\Performance\ResourceHints;

final class PerformanceTest extends IntegrationTestCase {

	private const MANIFEST = '/workspace/packages/theme-core/tests/Fixtures/manifests/perf.json';

	public function set_up(): void {
		parent::set_up();
		Kernel::reset();
		Notices::reset();
		delete_option( 'joo_cache_version' );
		delete_option( 'joo_cache_tags' );
		$GLOBALS['wp_styles']  = new \WP_Styles();
		$GLOBALS['wp_scripts'] = new \WP_Scripts();
	}

	public function tear_down(): void {
		Kernel::reset();
		parent::tear_down();
	}

	private function boot(): void {
		Kernel::boot( self::MANIFEST );
		do_action( 'after_setup_theme' );
	}

	/**
	 * @template T of object
	 * @param class-string<T> $class Service class.
	 * @return T
	 */
	private function service( string $class ) {
		return Kernel::container()->get( $class );
	}

	public function test_manifest_image_sizes_are_registered_and_presets_resolve(): void {
		$this->boot();
		$images = $this->service( Images::class );
		$this->assertSame( array( 'joo-card', 'joo-card-2x', 'joo-cover', 'joo-cover-2x' ), $images->sizes() );
		$this->assertArrayHasKey( 'joo-card', wp_get_additional_image_sizes() );
		$this->assertSame( '(min-width: 900px) 220px, 100vw', $images->sizesFor( 'card' ) );
		$this->assertSame( '50vw', $images->sizesFor( '50vw' ), 'a literal value passes through' );
	}

	public function test_tag_has_dimensions_srcset_sizes_alt_and_decoding(): void {
		$this->boot();
		$id   = AttachmentFactory::image( 2400, 1700 );
		$html = $this->service( Images::class )->tag( $id, 'joo-card', array( 'sizes' => 'card' ) );
		$this->assertMatchesRegularExpression( '/ width="\d+"/', $html );
		$this->assertMatchesRegularExpression( '/ height="\d+"/', $html );
		$this->assertStringContainsString( ' srcset="', $html );
		$this->assertStringContainsString( '(min-width: 900px) 220px, 100vw', $html, 'the preset is used (WordPress may prefix "auto," for lazy images)' );
		$this->assertStringContainsString( 'decoding="async"', $html );
		$this->assertStringContainsString( 'alt="Generated test image"', $html );
	}

	public function test_decorative_images_get_an_empty_alt(): void {
		$this->boot();
		$id = AttachmentFactory::image( 800, 600 );
		delete_post_meta( $id, '_wp_attachment_image_alt' );
		$html = $this->service( Images::class )->tag( $id, 'joo-card', array( 'decorative' => true ) );
		$this->assertStringContainsString( 'alt=""', $html );
	}

	public function test_the_lcp_image_is_eager_high_priority_and_preloaded(): void {
		$this->boot();
		$id     = AttachmentFactory::image( 3400, 1800 );
		$images = $this->service( Images::class );
		$images->markLcp( $id );
		$html = $images->tag( $id, 'joo-cover', array( 'sizes' => 'cover' ) );
		$this->assertStringContainsString( 'fetchpriority="high"', $html );
		$this->assertStringContainsString( 'loading="eager"', $html );
		$this->assertStringNotContainsString( 'loading="lazy"', $html );
		$preloads = array_filter( $this->service( Preloader::class )->items(), static fn( array $i ) => 'image' === $i['as'] );
		$this->assertCount( 1, $preloads );
		$first = array_values( $preloads )[0];
		$this->assertSame( 'high', $first['attrs']['fetchpriority'] );
		$this->assertArrayHasKey( 'imagesrcset', $first['attrs'] );
		$this->assertSame( '(min-width: 1240px) 900px, 100vw', $first['attrs']['imagesizes'] );
	}

	public function test_non_lcp_images_are_not_forced_eager(): void {
		$this->boot();
		$id   = AttachmentFactory::image( 800, 600 );
		$html = $this->service( Images::class )->tag( $id, 'joo-card', array( 'loading' => 'lazy' ) );
		$this->assertStringNotContainsString( 'fetchpriority="high"', $html );
		$this->assertStringContainsString( 'loading="lazy"', $html );
	}

	public function test_content_images_get_dimensions_and_the_theme_sizes_preset(): void {
		$this->boot();
		$id   = AttachmentFactory::image( 1200, 800 );
		$html = $this->service( Images::class )->contentImage( '<img src="x.png" class="wp-image-' . $id . '" sizes="(max-width: 100px) 100vw, 100px" alt="">', 'the_content', $id );
		$this->assertStringContainsString( 'width="1200"', $html );
		$this->assertStringContainsString( 'height="800"', $html );
		$this->assertStringContainsString( 'sizes="(min-width: 900px) 700px, 100vw"', $html );
	}

	public function test_webp_output_format_is_enabled_only_when_supported(): void {
		$this->boot();
		$formats   = $this->service( Images::class )->outputFormats( array() );
		$supported = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
		if ( $supported ) {
			$this->assertSame( 'image/webp', $formats['image/jpeg'] );
			$this->assertSame( 'image/webp', $formats['image/png'] );
		} else {
			$this->assertSame( array(), $formats );
			$this->markTestIncomplete( 'WebP is not supported by the image editor in this container.' );
		}
	}

	public function test_srcset_is_limited_to_1920_pixels(): void {
		$this->boot();
		$this->assertSame( 1920, apply_filters( 'max_srcset_image_width', 2048 ) );
	}

	public function test_font_css_has_swap_fallback_metrics_and_is_escaped(): void {
		$this->boot();
		$css = $this->service( Fonts::class )->css();
		$this->assertStringContainsString( 'font-display:swap', $css );
		$this->assertStringContainsString( 'font-family:"Demo \\"Sans\\" Fallback"', $css );
		$this->assertStringContainsString( 'size-adjust:105%', $css );
		$this->assertStringContainsString( 'ascent-override:92%', $css );
		$this->assertStringContainsString( "format('woff2')", $css );
		$this->assertStringNotContainsString( '</style', $css );
	}

	public function test_at_most_two_fonts_are_preloaded_with_crossorigin(): void {
		$this->boot();
		$this->service( Fonts::class )->queuePreloads();
		$fonts = array_values( array_filter( $this->service( Preloader::class )->items(), static fn( array $i ) => 'font' === $i['as'] ) );
		$this->assertCount( 2, $fonts );
		$this->assertArrayHasKey( 'crossorigin', $fonts[0]['attrs'] );
		$this->assertSame( 'font/woff2', $fonts[0]['attrs']['type'] );
	}

	public function test_font_style_block_is_printed_in_the_head(): void {
		$this->boot();
		$this->go_to( '/' );
		ob_start();
		do_action( 'wp_head' );
		$head = (string) ob_get_clean();
		$this->assertStringContainsString( '<style id="joo-fonts">@font-face', $head );
	}

	public function test_resource_hints_add_preconnect_and_drop_the_emoji_prefetch(): void {
		$this->boot();
		$hints = $this->service( ResourceHints::class );
		$pre   = $hints->hints( array(), 'preconnect' );
		$this->assertSame( 'https://cdn.example.test', $pre[0]['href'] );
		$dns = $hints->hints( array( 'https://s.w.org/images/core/emoji/', '//fonts.example.test' ), 'dns-prefetch' );
		$this->assertSame( array( '//fonts.example.test' ), $dns );
	}

	public function test_speculation_rules_follow_the_setting(): void {
		$this->boot();
		$hints = $this->service( ResourceHints::class );
		$rules = $hints->rules();
		$this->assertArrayHasKey( 'prefetch', $rules );
		$encoded = (string) wp_json_encode( $rules );
		foreach ( array( '/wp-admin/*', '/wp-login.php', '/cart*', '/checkout*' ) as $excluded ) {
			$this->assertStringContainsString( str_replace( '/', '\/', $excluded ), $encoded );
		}
		$this->assertTrue( $hints->enabled(), 'anonymous visitors get speculation rules' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertFalse( $hints->enabled(), 'logged-in users do not' );
		wp_set_current_user( 0 );
		$config = $hints->coreConfiguration( null );
		$this->assertSame( 'prefetch', $config['mode'] );
	}

	public function test_speculation_off_prints_nothing_and_moderate_prerenders(): void {
		add_filter( 'joo/settings/value', static fn( $v, $key ) => 'perf.speculation' === $key ? 'off' : $v, 10, 2 );
		$this->boot();
		$hints = $this->service( ResourceHints::class );
		$this->assertFalse( $hints->enabled() );
		$this->assertNull( $hints->coreConfiguration( array() ) );
		ob_start();
		$hints->printRules();
		$this->assertSame( '', (string) ob_get_clean() );
		remove_all_filters( 'joo/settings/value' );

		Kernel::reset();
		add_filter( 'joo/settings/value', static fn( $v, $key ) => 'perf.speculation' === $key ? 'moderate' : $v, 10, 2 );
		$this->boot();
		$moderate = $this->service( ResourceHints::class );
		$this->assertArrayHasKey( 'prerender', $moderate->rules() );
		$this->assertSame( 'prerender', $moderate->coreConfiguration( null )['mode'] );
	}

	public function test_bloat_switches_remove_emoji_embeds_and_head_noise_on_the_front_end(): void {
		add_theme_support( 'automatic-feed-links' );
		$this->boot();
		$this->go_to( '/' );
		// Kernel boot already applied the switches (the defaults are on).
		$this->assertFalse( has_action( 'wp_head', 'print_emoji_detection_script' ) );
		$this->assertFalse( has_action( 'wp_print_styles', 'print_emoji_styles' ) );
		$this->assertFalse( has_action( 'wp_head', 'wp_oembed_add_discovery_links' ) );
		$this->assertFalse( has_action( 'wp_head', 'wp_generator' ) );
		$this->assertFalse( has_action( 'wp_head', 'rsd_link' ) );
		ob_start();
		do_action( 'wp_head' );
		$head = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'name="generator"', $head );
		$this->assertStringContainsString( 'application/rss+xml', $head, 'feed discovery links stay' );
		$this->assertTrue( apply_filters( 'should_load_separate_core_block_assets', false ) );
	}

	public function test_a_switch_turned_off_leaves_the_output_alone(): void {
		add_filter( 'joo/settings/value', static fn( $v, $key ) => 'perf.disable_emoji' === $key ? false : $v, 10, 2 );
		$this->boot();
		$this->go_to( '/' );
		$this->assertNotFalse( has_action( 'wp_head', 'print_emoji_detection_script' ) );
	}

	public function test_dashicons_are_dequeued_for_visitors_unless_a_style_needs_them(): void {
		$this->boot();
		$bloat = $this->service( Bloat::class );
		wp_enqueue_style( 'dashicons' );
		$bloat->dashicons();
		$this->assertNotContains( 'dashicons', wp_styles()->queue );

		wp_enqueue_style( 'dashicons' );
		wp_register_style( 'joo-needs-icons', 'https://example.test/s.css', array( 'dashicons' ), '1' );
		wp_enqueue_style( 'joo-needs-icons' );
		$bloat->dashicons();
		$this->assertContains( 'dashicons', wp_styles()->queue );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		wp_styles()->queue = array( 'dashicons' );
		$bloat->dashicons();
		$this->assertContains( 'dashicons', wp_styles()->queue, 'logged-in users keep Dashicons' );
	}

	public function test_fragment_cache_hits_make_no_database_queries(): void {
		$this->boot();
		global $wpdb;
		$cache = $this->service( FragmentCache::class );
		$calls = 0;
		$build = static function () use ( &$calls ) {
			++$calls;
			return array( 'rows' => 3 );
		};
		$this->assertSame( array( 'rows' => 3 ), $cache->remember( 'demo', 60, $build, array( 'posts' ) ) );
		$queries = $wpdb->num_queries;
		$this->assertSame( array( 'rows' => 3 ), $cache->remember( 'demo', 60, $build, array( 'posts' ) ) );
		$this->assertSame( 1, $calls );
		$this->assertSame( $queries, $wpdb->num_queries, 'a cache hit does not query the database' );
		$this->assertSame(
			array(
				'hits'   => 1,
				'misses' => 1,
			),
			$cache->stats()
		);
	}

	public function test_fragment_cache_is_invalidated_by_content_changes(): void {
		$this->boot();
		$cache = $this->service( FragmentCache::class );
		$n     = 0;
		$build = static function () use ( &$n ) {
			return ++$n;
		};
		$this->assertSame( 1, $cache->remember( 'posts-list', 60, $build, array( 'posts' ) ) );
		$this->assertSame( 1, $cache->remember( 'posts-list', 60, $build, array( 'posts' ) ) );
		self::factory()->post->create();
		$this->assertSame( 2, $cache->remember( 'posts-list', 60, $build, array( 'posts' ) ), 'a new post invalidates the posts tag' );

		$this->assertSame( 3, $cache->remember( 'terms-list', 60, $build, array( 'terms' ) ) );
		wp_update_term( self::factory()->term->create(), 'category', array( 'name' => 'Renamed' ) );
		$this->assertSame( 4, $cache->remember( 'terms-list', 60, $build, array( 'terms' ) ), 'a term change invalidates the terms tag' );

		$this->assertSame( 2, $cache->remember( 'posts-list', 60, static fn() => 99, array( 'posts' ) ), 'unrelated tags are unaffected by term changes' );
	}

	public function test_flushing_the_global_version_invalidates_everything(): void {
		$this->boot();
		$cache = $this->service( FragmentCache::class );
		$cache->remember( 'x', 60, static fn() => 'old' );
		update_option( 'joo_cache_version', 5 );
		$this->assertSame( 'new', $cache->remember( 'x', 60, static fn() => 'new' ) );
	}

	public function test_stale_values_are_served_while_one_request_rebuilds(): void {
		$this->boot();
		$cache = $this->service( FragmentCache::class );
		$key   = $cache->key( 'content', 'stale', array() );
		set_transient(
			$key,
			array(
				'value'       => 'stale',
				'fresh_until' => time() - 5,
			),
			100
		);
		$this->assertSame( 'rebuilt', $cache->remember( 'stale', 60, static fn() => 'rebuilt' ), 'the first caller takes the lock and rebuilds' );
		set_transient(
			$key,
			array(
				'value'       => 'stale-again',
				'fresh_until' => time() - 5,
			),
			100
		);
		$this->assertSame( 'stale-again', $cache->remember( 'stale', 60, static fn() => 'must-not-run' ), 'a caller that cannot get the lock serves the stale value' );
	}

	public function test_long_cache_keys_are_hashed(): void {
		$this->boot();
		$key = $this->service( FragmentCache::class )->key( 'content', str_repeat( 'a', 300 ) );
		$this->assertLessThan( 60, strlen( $key ) );
	}

	public function test_rest_responses_get_cache_headers_etag_and_304(): void {
		$this->boot();
		$server  = rest_get_server();
		$headers = $this->service( CacheHeaders::class );
		$request = new \WP_REST_Request( 'GET', '/joo/v1/ping' );
		$first   = $headers->filter( new \WP_REST_Response( array( 'pong' => true ) ), $server, $request );
		$sent    = $first->get_headers();
		$this->assertSame( 'public, max-age=300, stale-while-revalidate=600', $sent['Cache-Control'] );
		$this->assertStringStartsWith( 'W/"', $sent['ETag'] );
		$this->assertSame( 'Accept-Encoding', $sent['Vary'] );

		$conditional = new \WP_REST_Request( 'GET', '/joo/v1/ping' );
		$conditional->set_header( 'If-None-Match', $sent['ETag'] );
		$again = $headers->filter( new \WP_REST_Response( array( 'pong' => true ) ), $server, $conditional );
		$this->assertSame( 304, $again->get_status() );
		$this->assertNull( $again->get_data() );

		$changed = $headers->filter( new \WP_REST_Response( array( 'pong' => false ) ), $server, $conditional );
		$this->assertSame( 200, $changed->get_status(), 'a different body has a different ETag' );
	}

	public function test_cache_headers_ignore_other_namespaces_and_methods(): void {
		$this->boot();
		$server  = rest_get_server();
		$headers = $this->service( CacheHeaders::class );
		$post    = new \WP_REST_Request( 'POST', '/joo/v1/ping' );
		$result  = new \WP_REST_Response( array( 'x' => 1 ) );
		$this->assertArrayNotHasKey( 'ETag', $headers->filter( $result, $server, $post )->get_headers() );
		$other = new \WP_REST_Request( 'GET', '/wp/v2/posts' );
		$this->assertArrayNotHasKey( 'ETag', $headers->filter( new \WP_REST_Response( array() ), $server, $other )->get_headers() );
		$this->assertTrue( $headers->matches( 'W/"a", W/"b"', 'W/"b"' ) );
		$this->assertTrue( $headers->matches( '*', 'W/"x"' ) );
		$this->assertFalse( $headers->matches( 'W/"a"', 'W/"b"' ) );
	}

	public function test_diagnostics_snapshot_and_self_test(): void {
		$this->boot();
		$diagnostics = $this->service( Diagnostics::class );
		$snapshot    = $diagnostics->snapshot();
		foreach ( array( 'queries', 'assets', 'cache', 'opcache', 'object', 'compress' ) as $key ) {
			$this->assertArrayHasKey( $key, $snapshot );
		}
		add_filter(
			'pre_http_request',
			static fn() => array(
				'headers'  => array(),
				'body'     => '<html><script></script><link rel="stylesheet" href="a"><img src="x"></html>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
			)
		);
		$result = $diagnostics->selfTest();
		$this->assertTrue( $result['ok'] );
		$this->assertSame( 200, $result['status'] );
		$this->assertSame( 1, $result['scripts'] );
		$this->assertSame( 1, $result['images'] );
	}

	public function test_diagnostics_admin_bar_is_for_admins_in_debug_only(): void {
		$this->boot();
		$diagnostics = $this->service( Diagnostics::class );
		wp_set_current_user( 0 );
		$this->assertFalse( $diagnostics->visible() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( defined( 'WP_DEBUG' ) && WP_DEBUG, $diagnostics->visible() );
	}
}
