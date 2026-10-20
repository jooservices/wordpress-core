<?php
/**
 * Portfolio fixture contract (real WordPress).
 *
 * @package JOOservices
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Blocks\BlockRegistrar;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Kernel;

final class PortfolioFixtureTest extends IntegrationTestCase {

	private const DIR      = __DIR__ . '/../..';
	private const MANIFEST = self::DIR . '/joo-theme.json';

	private int $owner = 0;

	public function set_up(): void {
		parent::set_up();
		Kernel::reset();
		Notices::reset();
		$this->set_permalink_structure( '/%postname%/' );
		create_initial_taxonomies();
		$GLOBALS['wp_rewrite']->flush_rules();
		update_option( 'blogname', 'Portfolio Fixture' );
		update_option( 'blogdescription', 'A profile-led portfolio.' );
		$this->owner = 1;
		wp_update_user(
			array(
				'ID'           => $this->owner,
				'role'         => 'administrator',
				'display_name' => 'Portfolio Owner',
				'description'  => 'Designer and developer.',
			)
		);
		Kernel::boot( self::MANIFEST );
		do_action( 'after_setup_theme' );
	}

	public function tear_down(): void {
		Kernel::reset();
		parent::tear_down();
	}

	/**
	 * Expands this fixture's template parts for direct block rendering.
	 *
	 * @param string $name Template name.
	 */
	private function template( string $name ): string {
		$path   = self::DIR . '/templates/' . $name . '.html';
		$markup = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return (string) preg_replace_callback(
			'/<!-- wp:template-part \{"slug":"([a-z0-9-]+)"\} \/-->/',
			static fn( array $match ): string => (string) file_get_contents( self::DIR . '/parts/' . $match[1] . '.html' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$markup
		);
	}

	/**
	 * Renders a fixture template for a URL and fails on PHP notices.
	 *
	 * @param string $url      URL.
	 * @param string $template Template name.
	 */
	private function render( string $url, string $template ): string {
		$notices = array();
		set_error_handler(
			static function ( int $severity, string $message ) use ( &$notices ): bool {
				if ( in_array( $severity, array( E_NOTICE, E_USER_NOTICE ), true ) ) {
					$notices[] = $message;
					return true;
				}
				return false;
			},
			E_NOTICE | E_USER_NOTICE
		);
		try {
			$this->go_to( $url );
			if ( is_singular() ) {
				the_post();
			}
			$html = do_blocks( $this->template( $template ) );
		} finally {
			restore_error_handler();
		}
		$this->assertSame( array(), $notices, $template . ' emitted PHP notices for ' . $url );
		return $html;
	}

	/**
	 * Boots SEO output and returns its decoded JSON-LD graph.
	 *
	 * @param string $url URL.
	 * @return array<string, mixed>
	 */
	private function graph( string $url ): array {
		$this->go_to( $url );
		do_action( 'wp' );
		$html = $this->capture( 'wp_head' );
		$this->assertSame( 1, preg_match( '#<script type="application/ld\+json" class="joo-schema">(.*?)</script>#s', $html, $match ) );
		$this->assertArrayHasKey( 1, $match );
		$document = json_decode( $match[1], true );
		$this->assertIsArray( $document );
		return $document;
	}

	/**
	 * Returns graph nodes indexed by their Schema.org type.
	 *
	 * @param array<string, mixed> $document Graph document.
	 * @return array<string, array<string, mixed>>
	 */
	private function byType( array $document ): array {
		$nodes = array();
		foreach ( $document['@graph'] as $node ) {
			$nodes[ (string) $node['@type'] ] = $node;
		}
		return $nodes;
	}

	public function test_portfolio_manifest_emits_a_profile_page_graph(): void {
		self::factory()->post->create( array( 'post_author' => $this->owner ) );
		$this->assertSame( 'portfolio', Kernel::container()->get( Manifest::class )->get( 'seo.siteType' ) );

		$types = $this->byType( $this->graph( get_author_posts_url( $this->owner ) ) );
		$this->assertArrayHasKey( 'WebSite', $types );
		$this->assertArrayHasKey( 'ProfilePage', $types );
		$this->assertArrayHasKey( 'Person', $types );
		$this->assertSame( array( '@id' => home_url( '/#person' ) ), $types['ProfilePage']['mainEntity'] );
		$this->assertSame( 'Portfolio Owner', $types['Person']['name'] );
		$this->assertArrayNotHasKey( 'BlogPosting', $types );
	}

	public function test_taxonomy_blocks_and_disabled_modules_stay_off(): void {
		$manifest = Kernel::container()->get( Manifest::class );
		$disabled = (array) $manifest->get( 'blocks.disabled' );
		$this->assertContains( 'joo/category-tree', $disabled );
		$this->assertContains( 'joo/top-topics', $disabled );
		$this->assertContains( 'joo/tag-list', $disabled );
		$this->assertSame( 'disabled', Kernel::status()['terminal'] );
		$this->assertSame( 'disabled', Kernel::status()['i18n'] );
		$this->assertSame( 'booted', Kernel::status()['seo'] );
		$this->assertSame( 'booted', Kernel::status()['blocks'] );

		Kernel::container()->get( BlockRegistrar::class )->register();
		$registry = \WP_Block_Type_Registry::get_instance();
		foreach ( array( 'joo/category-tree', 'joo/top-topics', 'joo/tag-list', 'joo/terminal-dock' ) as $name ) {
			$this->assertFalse( $registry->is_registered( $name ), $name . ' should be disabled by the portfolio manifest' );
		}
		foreach ( array( 'joo/profile-card', 'joo/companies' ) as $name ) {
			$this->assertTrue( $registry->is_registered( $name ), $name . ' should remain available to the portfolio fixture' );
		}

		do_action( 'rest_api_init', rest_get_server() );
		$this->assertArrayNotHasKey( '/joo/v1/terminal/fs', rest_get_server()->get_routes() );
	}

	public function test_six_public_templates_render_without_php_notices_or_blog_home_content(): void {
		$category = self::factory()->category->create( array( 'name' => 'Portfolio Work' ) );
		$post     = self::factory()->post->create(
			array(
				'post_title'    => 'Portfolio Piece',
				'post_author'   => $this->owner,
				'post_content'  => '<p>Portfolio detail content.</p>',
				'post_category' => array( $category ),
			)
		);
		wp_set_post_tags( $post, array( 'portfolio-work' ) );

		$home = $this->render( '/', 'home' );
		$this->assertStringContainsString( '<h1 class="wp-block-heading">Portfolio</h1>', $home );
		$this->assertStringNotContainsString( 'wp-block-query', $home );
		$this->assertStringNotContainsString( 'Latest posts', $home );
		$this->assertStringContainsString( 'Portfolio Piece', $this->render( get_permalink( $post ), 'single' ) );
		$this->assertNotEmpty( $this->render( get_category_link( $category ), 'archive' ) );
		$tag = get_term_by( 'slug', 'portfolio-work', 'post_tag' );
		$this->assertInstanceOf( \WP_Term::class, $tag );
		$this->assertNotEmpty( $this->render( get_tag_link( $tag ), 'archive' ) );
		$this->assertNotEmpty( $this->render( '/?s=portfolio', 'search' ) );
		$this->assertNotEmpty( $this->render( '/definitely-missing/', '404' ) );
	}

	public function test_canvas_template_renders_builder_content_without_theme_chrome(): void {
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Builder page',
				'post_content' => '<!-- wp:paragraph --><p>Builder canvas content.</p><!-- /wp:paragraph -->',
			)
		);
		update_post_meta( $page, '_wp_page_template', 'page-canvas.html' );
		$this->assertSame( 'page-canvas.html', get_page_template_slug( $page ) );
		$this->assertTrue( (bool) Kernel::container()->get( Manifest::class )->get( 'blocks.canvas' ) );

		$html = $this->render( get_permalink( $page ), 'page-canvas' );
		$this->assertStringContainsString( 'Builder canvas content.', $html );
		$this->assertStringNotContainsString( 'joo-header', $html );
		$this->assertStringNotContainsString( 'joo-footer', $html );
	}
}
