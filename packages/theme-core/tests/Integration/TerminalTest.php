<?php
/**
 * Terminal file system, route and blocks (real WordPress).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Blocks\Blocks\NotFound;
use JOOservices\ThemeCore\Blocks\BlockRegistrar;
use JOOservices\ThemeCore\Kernel;
use JOOservices\ThemeCore\Terminal\FsBuilder;

final class TerminalTest extends IntegrationTestCase {

	private const FIXTURES = '/workspace/packages/theme-core/tests/Fixtures/manifests/';

	public function set_up(): void {
		parent::set_up();
		Kernel::reset();
		Notices::reset();
		$GLOBALS['wp_rest_server'] = null;
		$this->set_permalink_structure( '/%postname%/' );
		create_initial_taxonomies();
		$GLOBALS['wp_rewrite']->flush_rules();
		wp_cache_flush();
		update_option( 'blogname', 'Joo Blog' );
	}

	public function tear_down(): void {
		foreach ( array( 'joo/terminal-dock', 'joo/not-found' ) as $name ) {
			if ( \WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}
		unset( $_SERVER['REQUEST_URI'] );
		Kernel::reset();
		$GLOBALS['wp_rest_server'] = null;
		parent::tear_down();
	}

	private function boot( string $manifest = 'perf.json', array $settings = array() ): FsBuilder {
		if ( array() !== $settings ) {
			add_filter( 'joo/settings/value', static fn( $v, $key ) => array_key_exists( $key, $settings ) ? $settings[ $key ] : $v, 10, 2 );
		}
		Kernel::boot( self::FIXTURES . $manifest );
		do_action( 'after_setup_theme' );
		Kernel::container()->get( BlockRegistrar::class )->register();
		return Kernel::container()->get( FsBuilder::class );
	}

	/**
	 * @param array<string, mixed> $data FS JSON.
	 * @return array<string, array<string, mixed>> Nodes by path.
	 */
	private function byPath( array $data ): array {
		$out = array();
		foreach ( $data['nodes'] as $node ) {
			$out[ $node['p'] ] = $node;
		}
		return $out;
	}

	public function test_categories_are_directories_posts_are_files_and_extra_categories_are_links(): void {
		$builder = $this->boot();
		$rd      = self::factory()->category->create(
			array(
				'name' => 'Research & Development',
				'slug' => 'research-and-development',
			)
		);
		$lab     = self::factory()->category->create(
			array(
				'name'   => 'Homelab',
				'slug'   => 'homelab',
				'parent' => $rd,
			)
		);
		$news    = self::factory()->category->create(
			array(
				'name' => 'News',
				'slug' => 'news',
			)
		);
		self::factory()->category->create(
			array(
				'name' => 'Empty',
				'slug' => 'empty',
			)
		);
		$post = self::factory()->post->create(
			array(
				'post_title'    => 'Rack build',
				'post_name'     => 'rack-build',
				'post_category' => array( $lab, $news ),
				'post_excerpt'  => 'Servers at home.',
			)
		);
		update_post_meta( $post, '_joo_primary_category', $lab );
		wp_set_post_tags( $post, array( 'docker', 'homelab' ) );
		$data  = $builder->build();
		$nodes = $this->byPath( $data );
		$this->assertSame( 1, $data['v'] );
		$this->assertSame( 'd', $nodes['/research-and-development']['t'] );
		$this->assertSame( 'Research & Development', $nodes['/research-and-development']['cat'] );
		$this->assertSame( 'd', $nodes['/research-and-development/homelab']['t'] );
		$this->assertArrayNotHasKey( '/empty', $nodes, 'empty categories are not directories' );
		$file = $nodes['/research-and-development/homelab/rack-build.md'];
		$this->assertSame( 'f', $file['t'] );
		$this->assertSame( 'Rack build', $file['title'] );
		$this->assertSame( 'Servers at home.', $file['ex'] );
		$this->assertSame( array( 'docker', 'homelab' ), $file['tg'] );
		$this->assertSame( get_permalink( $post ), $file['url'] );
		$this->assertSame( 'l', $nodes['/news/rack-build.md']['t'] );
		$this->assertSame( '/research-and-development/homelab/rack-build.md', $nodes['/news/rack-build.md']['to'] );
		$this->assertSame( 'l', $nodes['/tags/docker/rack-build.md']['t'] );
		$this->assertSame( 'docker', $nodes['/tags/docker']['tag'] );
		$this->assertSame( 'd', $nodes['/tags']['t'] );
	}

	public function test_pages_authors_readme_and_config_exist_and_site_info_is_present(): void {
		$builder = $this->boot();
		wp_update_user(
			array(
				'ID'           => 1,
				'display_name' => 'Viet Vu',
				'description'  => 'Engineer.',
			)
		);
		self::factory()->post->create( array( 'post_author' => 1 ) );
		self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'about',
				'post_title'   => 'About',
				'post_content' => 'About me text',
				'post_excerpt' => '',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'     => 'page',
				'post_name'     => 'secret',
				'post_title'    => 'Secret',
				'post_password' => 'x',
			)
		);
		$child_parent = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'parent',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'child',
				'post_parent' => $child_parent,
			)
		);
		$data  = $builder->build();
		$nodes = $this->byPath( $data );
		$this->assertSame( 'f', $nodes['/pages/about.md']['t'] );
		$this->assertSame( 'About me text', $nodes['/pages/about.md']['ex'] );
		$this->assertArrayNotHasKey( '/pages/secret.md', $nodes );
		$this->assertArrayNotHasKey( '/pages/child.md', $nodes, 'only top-level pages' );
		$author = array_values( array_filter( $nodes, static fn( $n ) => str_starts_with( (string) $n['p'], '/authors/' ) ) );
		$this->assertCount( 1, $author );
		$this->assertStringContainsString( 'Viet Vu', $author[0]['text'] );
		$this->assertArrayHasKey( '/README.md', $nodes );
		$this->assertTrue( $nodes['/.config']['hidden'] );
		$this->assertSame( 'viet', $data['site']['user'] );
		$this->assertSame( 'Joo Blog', $data['site']['name'] );
		$this->assertSame( home_url( '/' ), $data['site']['home'] );
		$this->assertNotSame( '', $data['site']['host'] );
	}

	public function test_only_published_public_content_appears_and_titles_are_plain_text(): void {
		$builder = $this->boot();
		$cat     = self::factory()->category->create(
			array(
				'name' => 'Pub',
				'slug' => 'pub',
			)
		);
		self::factory()->post->create(
			array(
				'post_title'    => 'Public one',
				'post_category' => array( $cat ),
			)
		);
		self::factory()->post->create(
			array(
				'post_title'    => 'Draft one',
				'post_status'   => 'draft',
				'post_category' => array( $cat ),
			)
		);
		self::factory()->post->create(
			array(
				'post_title'    => 'Private one',
				'post_status'   => 'private',
				'post_category' => array( $cat ),
			)
		);
		self::factory()->post->create(
			array(
				'post_title'    => 'Protected one',
				'post_password' => 'pw',
				'post_category' => array( $cat ),
			)
		);
		self::factory()->post->create(
			array(
				'post_title'    => 'Evil <img src=x onerror=alert(1)>',
				'post_category' => array( $cat ),
				'post_excerpt'  => '<script>alert(1)</script> hello',
			)
		);
		$json = (string) wp_json_encode( $builder->build() );
		$this->assertStringContainsString( 'Public one', $json );
		foreach ( array( 'Draft one', 'Private one', 'Protected one' ) as $hidden ) {
			$this->assertStringNotContainsString( $hidden, $json, $hidden );
		}
		$this->assertStringNotContainsString( '<img', $json );
		$this->assertStringNotContainsString( '<script', $json );
	}

	public function test_same_slug_in_one_directory_gets_the_id_appended_and_the_post_limit_applies(): void {
		$builder = $this->boot( 'perf.json', array( 'terminal.max_posts' => 10 ) );
		$cat     = self::factory()->category->create(
			array(
				'name' => 'Same',
				'slug' => 'same',
			)
		);
		$a       = self::factory()->post->create(
			array(
				'post_name'     => 'dup',
				'post_category' => array( $cat ),
			)
		);
		global $wpdb;
		$b = self::factory()->post->create(
			array(
				'post_name'     => 'other',
				'post_category' => array( $cat ),
			)
		);
		$wpdb->update( $wpdb->posts, array( 'post_name' => 'dup' ), array( 'ID' => $b ) );
		clean_post_cache( $b );
		$nodes = $this->byPath( $builder->build() );
		$this->assertArrayHasKey( '/same/dup.md', $nodes );
		$suffixed = array_filter( array_keys( $nodes ), static fn( $p ) => 1 === preg_match( '#^/same/dup-\d+\.md$#', (string) $p ) );
		$this->assertCount( 1, $suffixed, 'the second file with the same slug gets its id appended' );
		$this->assertContains( (int) preg_replace( '/\D/', '', (string) reset( $suffixed ) ), array( $a, $b ) );
		foreach ( range( 1, 14 ) as $i ) {
			self::factory()->post->create( array( 'post_category' => array( $cat ) ) );
		}
		$count = count( array_filter( $builder->build()['nodes'], static fn( $n ) => 'f' === $n['t'] && isset( $n['id'] ) ) );
		$this->assertSame( 10, $count, 'terminal.max_posts limits the tree (newest first)' );
	}

	public function test_language_filter_returns_that_language_and_untagged_posts(): void {
		$builder = $this->boot( 'multilang.json' );
		$cat     = self::factory()->category->create(
			array(
				'name' => 'L',
				'slug' => 'l',
			)
		);
		$en      = self::factory()->post->create(
			array(
				'post_title'    => 'English',
				'post_name'     => 'english',
				'post_category' => array( $cat ),
			)
		);
		$vi      = self::factory()->post->create(
			array(
				'post_title'    => 'Tiếng Việt',
				'post_name'     => 'tieng-viet',
				'post_category' => array( $cat ),
			)
		);
		$any     = self::factory()->post->create(
			array(
				'post_title'    => 'Untagged',
				'post_name'     => 'untagged',
				'post_category' => array( $cat ),
			)
		);
		update_post_meta( $en, '_joo_lang', 'en' );
		update_post_meta( $vi, '_joo_lang', 'vi' );
		wp_cache_flush();
		$vn = $this->byPath( $builder->build( 'vi' ) );
		$this->assertArrayHasKey( '/l/tieng-viet.md', $vn );
		$this->assertArrayHasKey( '/l/untagged.md', $vn );
		$this->assertArrayNotHasKey( '/l/english.md', $vn );
		$all = $this->byPath( $builder->build( '' ) );
		$this->assertArrayHasKey( '/l/english.md', $all );
		$this->assertArrayHasKey( '/l/tieng-viet.md', $all );
		$this->assertSame( 'vi', $builder->build( 'vi' )['lang'] );
		unset( $any );
	}

	public function test_oversized_trees_are_truncated_oldest_first(): void {
		$builder = $this->boot( 'perf.json', array( 'terminal.max_posts' => 2000 ) );
		$cat     = self::factory()->category->create(
			array(
				'name' => 'Big',
				'slug' => 'big',
			)
		);
		$text    = str_repeat( 'x', 190 );
		$ids     = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$ids[] = self::factory()->post->create(
				array(
					'post_name'     => 'p' . $i,
					'post_category' => array( $cat ),
					'post_excerpt'  => $text,
					'post_date'     => '2020-01-0' . ( $i + 1 ) . ' 00:00:00',
				)
			);
		}
		$reflection = new \ReflectionClass( FsBuilder::class );
		$method     = $reflection->getMethod( 'capSize' );
		$method->setAccessible( true );
		$data = array(
			'v'     => 1,
			'nodes' => array(),
		);
		for ( $i = 0; $i < 3000; $i++ ) {
			$data['nodes'][] = array(
				'p'     => '/big/p' . $i . '.md',
				't'     => 'f',
				'id'    => $i,
				'title' => 'T' . $i,
				'ex'    => $text,
				'd'     => sprintf( '2020-%02d-%02d', 1 + intdiv( $i, 28 ) % 12, 1 + $i % 28 ),
				'tg'    => array(),
			);
		}
		$data['nodes'][] = array(
			'p'  => '/big/link.md',
			't'  => 'l',
			'to' => '/big/p0.md',
		);
		$capped          = $method->invoke( $builder, $data );
		$this->assertTrue( $capped['truncated'] );
		$this->assertLessThanOrEqual( FsBuilder::MAX_BYTES, strlen( (string) wp_json_encode( $capped ) ) );
		$this->assertLessThan( 3001, count( $capped['nodes'] ) );
		$this->assertSame( 4, count( $ids ) );
	}

	public function test_the_rest_route_serves_the_tree_with_cache_headers_and_validates_lang(): void {
		$this->boot();
		$cat = self::factory()->category->create(
			array(
				'name' => 'Route',
				'slug' => 'route',
			)
		);
		self::factory()->post->create(
			array(
				'post_name'     => 'in-route',
				'post_category' => array( $cat ),
			)
		);
		do_action( 'rest_api_init' );
		$server   = rest_get_server();
		$request  = new \WP_REST_Request( 'GET', '/joo/v1/terminal/fs' );
		$response = apply_filters( 'rest_post_dispatch', $server->dispatch( $request ), $server, $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( '/route/in-route.md', $this->byPath( $response->get_data() ) );
		$this->assertStringContainsString( 'max-age=300', $response->get_headers()['Cache-Control'] );
		$bad = new \WP_REST_Request( 'GET', '/joo/v1/terminal/fs' );
		$bad->set_query_params( array( 'lang' => 'x;drop' ) );
		$this->assertSame( 400, rest_do_request( $bad )->get_status() );
		$ok = new \WP_REST_Request( 'GET', '/joo/v1/terminal/fs' );
		$ok->set_query_params( array( 'lang' => 'vi' ) );
		$this->assertSame( 200, rest_do_request( $ok )->get_status() );
	}

	public function test_the_tree_is_cached_and_invalidated_by_new_posts(): void {
		$builder = $this->boot();
		$cat     = self::factory()->category->create(
			array(
				'name' => 'Cache',
				'slug' => 'cache',
			)
		);
		self::factory()->post->create(
			array(
				'post_name'     => 'first',
				'post_category' => array( $cat ),
			)
		);
		$first = $builder->build();
		$this->assertSame( $first['generated'], $builder->build()['generated'], 'second call comes from the cache' );
		self::factory()->post->create(
			array(
				'post_name'     => 'second',
				'post_category' => array( $cat ),
			)
		);
		$this->assertArrayHasKey( '/cache/second.md', $this->byPath( $builder->build() ) );
	}

	public function test_the_dock_block_renders_a_hidden_tab_and_empty_panel_and_follows_the_settings(): void {
		$this->boot();
		$html = do_blocks( '<!-- wp:joo/terminal-dock /-->' );
		$this->assertStringContainsString( '<joo-terminal', $html );
		$this->assertStringContainsString( 'data-state="closed"', $html );
		$this->assertMatchesRegularExpression( '/<button type="button" class="joo-term__tab" aria-expanded="false" aria-controls="joo-term-panel"[^>]* hidden>/', $html );
		$this->assertMatchesRegularExpression( '/<div class="joo-term__panel" id="joo-term-panel"[^>]* hidden><\/div>/', $html );
		$this->assertStringNotContainsString( 'data-open-default', $html );
		$lazy = Kernel::container()->get( \JOOservices\ThemeCore\Assets\AssetRegistry::class )->lazyModules();
		$this->assertArrayHasKey( 'terminal', $lazy, 'the terminal bundle is exposed to the runtime, never enqueued' );
	}

	public function test_the_dock_can_be_disabled_open_by_default_and_hidden_on_404(): void {
		$this->boot( 'perf.json', array( 'terminal.enabled' => false ) );
		$this->assertSame( '', trim( do_blocks( '<!-- wp:joo/terminal-dock /-->' ) ) );
		Kernel::reset();
		remove_all_filters( 'joo/settings/value' );
		$this->boot( 'perf.json', array( 'terminal.open_by_default' => true ) );
		$this->assertStringContainsString( 'data-open-default="1"', do_blocks( '<!-- wp:joo/terminal-dock /-->' ) );
		$this->go_to( '/definitely-missing/' );
		$this->assertTrue( is_404() );
		$this->assertSame( '', trim( do_blocks( '<!-- wp:joo/terminal-dock /-->' ) ), 'terminal.hide_on_404 defaults to true' );
	}

	public function test_the_extra_command_modules_filter_reaches_the_runtime_config(): void {
		add_filter( 'joo/terminal/commands', static fn() => array( 'https://example.test/cmd.js', 42 ) );
		$this->boot();
		$data = Kernel::container()->get( \JOOservices\ThemeCore\Assets\RuntimeConfig::class )->data();
		$this->assertSame( array( 'https://example.test/cmd.js' ), $data['terminalCommands'] );
	}

	public function test_the_404_block_echoes_the_path_escaped_and_offers_suggestions(): void {
		$this->boot();
		self::factory()->post->create(
			array(
				'post_title' => 'Prompt caching',
				'post_name'  => 'prompt-caching',
			)
		);
		self::factory()->post->create(
			array(
				'post_title' => 'Unrelated thing',
				'post_name'  => 'zzz-other',
			)
		);
		wp_cache_flush();
		$this->go_to( '/definitely-missing/' );
		$_SERVER['REQUEST_URI'] = '/blog/prompt-cachng/"><script>alert(1)</script>?utm=1';
		$html                   = do_blocks( '<!-- wp:joo/not-found /-->' );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', preg_replace( '#<script type="application/json".*?</script>#s', '', $html ) );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringNotContainsString( 'utm=1', $html, 'the query string is dropped' );
		$this->assertStringContainsString( '<h1 class="joo-404__title">404', $html );
		$this->assertSame( 1, substr_count( $html, '<h1' ) );
		$this->assertStringContainsString( 'HTTP/2 404', $html );
		$this->assertStringContainsString( 'x-robots-tag: noindex', $html );
		$this->assertStringContainsString( 'role="search"', $html );
		$this->assertStringContainsString( 'Prompt caching', $html );
		$this->assertStringContainsString( '<script type="application/json" class="joo-404__index">', $html );
	}

	public function test_404_suggestion_scoring_and_path_limits(): void {
		$index = array(
			array(
				'slug'  => 'wordpress-mcp-v1',
				'title' => 'WordPress MCP',
				'url'   => 'https://x.test/a/',
			),
			array(
				'slug'  => 'prompt-caching',
				'title' => 'Prompt caching',
				'url'   => 'https://x.test/b/',
			),
			array(
				'slug'  => 'shell-habits',
				'title' => 'Shell habits',
				'url'   => 'https://x.test/c/',
			),
			array(
				'slug'  => 'block-theme',
				'title' => 'Block theme',
				'url'   => 'https://x.test/d/',
			),
		);
		$this->assertSame( 'WordPress MCP', NotFound::suggest( '/blog/wordpress-mpc-v1', $index )[0]['title'] );
		$this->assertCount( 3, NotFound::suggest( '/zzzzzzzzzzzz', $index ), 'with no good match the newest three are offered' );
		$this->assertSame( 0.0, NotFound::score( '/', 'abc' ) );
		$_SERVER['REQUEST_URI'] = '/' . str_repeat( 'a', 500 );
		$this->assertLessThanOrEqual( 200, mb_strlen( NotFound::requestedPath() ) );
		$_SERVER['REQUEST_URI'] = "/bad\x00path\r\n";
		$this->assertSame( 0, preg_match( '/[\x00-\x1F\x7F]/', NotFound::requestedPath() ), 'no control characters survive' );
	}
}
