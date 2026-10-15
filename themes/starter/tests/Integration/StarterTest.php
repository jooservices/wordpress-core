<?php
/**
 * Starter theme on the core (real WordPress, theme loaded through JOO_TEST_THEME=starter).
 *
 * @package JOOservices
 */

declare(strict_types=1);

namespace JOOservices\Starter\Tests\Integration;

use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Kernel;

final class StarterTest extends IntegrationTestCase {

	private const DIR = '/workspace/themes/starter';

	public function set_up(): void {
		parent::set_up();
		if ( null === Kernel::container() || 'starter' !== Kernel::container()->get( Manifest::class )->slug() ) {
			$this->markTestSkipped( 'Run with JOO_TEST_THEME=starter (make test-integration TEST_THEME=starter).' );
		}
	}

	public function test_theme_php_stays_within_the_line_budget(): void {
		$lines = count( file( self::DIR . '/functions.php' ) );
		foreach ( glob( self::DIR . '/inc/*.php' ) ?: array() as $file ) {
			$lines += count( file( $file ) );
		}
		$this->assertLessThanOrEqual( 50, $lines - $this->commentLines(), 'functions.php + inc/ non-comment lines' );
	}

	private function commentLines(): int {
		$count = 0;
		foreach ( array_merge( array( self::DIR . '/functions.php' ), glob( self::DIR . '/inc/*.php' ) ?: array() ) as $file ) {
			foreach ( file( $file ) as $line ) {
				$t = trim( $line );
				if ( '' === $t || str_starts_with( $t, '*' ) || str_starts_with( $t, '/**' ) || str_starts_with( $t, '//' ) ) {
					++$count;
				}
			}
		}
		return $count;
	}

	public function test_the_manifest_is_valid_and_every_module_booted(): void {
		$manifest = Kernel::container()->get( Manifest::class );
		$this->assertTrue( $manifest->isValid(), implode( '; ', $manifest->errors() ) );
		$this->assertSame( 'starter', $manifest->slug() );
		foreach ( Kernel::registry()->status() as $id => $status ) {
			$this->assertSame( 'booted', $status, 'module ' . $id );
		}
	}

	/**
	 * @return list<string>
	 */
	private function blockNames( string $markup ): array {
		$names = array();
		$walk  = static function ( array $blocks ) use ( &$walk, &$names ): void {
			foreach ( $blocks as $block ) {
				if ( ! empty( $block['blockName'] ) ) {
					$names[] = (string) $block['blockName'];
				}
				$walk( $block['innerBlocks'] ?? array() );
			}
		};
		$walk( parse_blocks( $markup ) );
		return array_values( array_unique( $names ) );
	}

	public function test_templates_and_parts_only_use_wordpress_and_core_blocks(): void {
		$files = array_merge( glob( self::DIR . '/templates/*.html' ) ?: array(), glob( self::DIR . '/parts/*.html' ) ?: array() );
		$this->assertGreaterThanOrEqual( 10, count( $files ) );
		foreach ( $files as $file ) {
			foreach ( $this->blockNames( (string) file_get_contents( $file ) ) as $name ) {
				$this->assertTrue( str_starts_with( $name, 'core/' ) || str_starts_with( $name, 'joo/' ), basename( $file ) . ' uses ' . $name );
				$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( $name ), basename( $file ) . ': ' . $name . ' is not registered' );
			}
		}
		foreach ( array( 'index', 'home', 'single', 'page', 'archive', 'search', '404' ) as $template ) {
			$this->assertFileExists( self::DIR . '/templates/' . $template . '.html' );
		}
		foreach ( array( 'header', 'footer', 'post-card' ) as $part ) {
			$this->assertFileExists( self::DIR . '/parts/' . $part . '.html' );
		}
	}

	/**
	 * Template markup with template parts inlined.
	 */
	private function expand( string $name ): string {
		$markup = (string) file_get_contents( self::DIR . '/templates/' . $name . '.html' );
		return (string) preg_replace_callback(
			'/<!-- wp:template-part \{"slug":"([a-z-]+)"\} \/-->/',
			static fn( array $m ) => (string) file_get_contents( self::DIR . '/parts/' . $m[1] . '.html' ),
			$markup
		);
	}

	public function test_single_template_renders_the_core_blocks_without_notices(): void {
		$cat  = self::factory()->category->create( array( 'name' => 'Starter cat' ) );
		$post = self::factory()->post->create(
			array(
				'post_title'    => 'Starter post',
				'post_author'   => 1,
				'post_category' => array( $cat ),
				'post_content'  => '<!-- wp:heading --><h2 class="wp-block-heading">One</h2><!-- /wp:heading --><!-- wp:heading --><h2 class="wp-block-heading">Two</h2><!-- /wp:heading --><!-- wp:heading --><h2 class="wp-block-heading">Three</h2><!-- /wp:heading -->',
			)
		);
		$this->go_to( get_permalink( $post ) );
		the_post();
		$html = do_blocks( $this->expand( 'single' ) );
		$this->assertStringContainsString( 'joo-breadcrumbs', $html );
		$this->assertStringContainsString( 'joo-meta', $html );
		$this->assertStringContainsString( 'joo-toc', $html );
		$this->assertStringContainsString( 'joo-progress', $html );
		$this->assertStringContainsString( 'joo-author', $html );
	}

	public function test_home_archive_search_and_404_render(): void {
		$cat = self::factory()->category->create( array( 'name' => 'Home cat' ) );
		self::factory()->post->create_many( 3, array( 'post_category' => array( $cat ) ) );
		$this->go_to( '/' );
		$this->assertStringContainsString( 'joo-theme-toggle', do_blocks( $this->expand( 'home' ) ) );
		$this->go_to( get_category_link( $cat ) );
		$this->assertStringContainsString( 'joo-breadcrumbs', do_blocks( $this->expand( 'archive' ) ) );
		$this->go_to( '/?s=hello' );
		$this->assertIsString( do_blocks( $this->expand( 'search' ) ) );
		$this->go_to( '/definitely-missing/' );
		$html = do_blocks( $this->expand( '404' ) );
		$this->assertStringContainsString( '404', $html );
		$this->assertStringContainsString( 'joo-404', $html );
	}

	public function test_the_style_variations_define_the_same_palette_slugs(): void {
		$slugs = static function ( string $file ): array {
			$json = json_decode( (string) file_get_contents( $file ), true );
			return array_column( $json['settings']['color']['palette'], 'slug' );
		};
		$base  = $slugs( self::DIR . '/theme.json' );
		$this->assertSame( $base, $slugs( self::DIR . '/styles/dark.json' ) );
		$this->assertSame( $base, $slugs( self::DIR . '/styles/light.json' ) );
	}

	public function test_the_hero_pattern_header_is_complete(): void {
		$data = get_file_data(
			self::DIR . '/patterns/hero.php',
			array(
				'title'      => 'Title',
				'slug'       => 'Slug',
				'categories' => 'Categories',
			)
		);
		$this->assertSame( 'Hero', $data['title'] );
		$this->assertSame( 'starter/hero', $data['slug'] );
		$this->assertSame( 'jooservices', $data['categories'] );
	}
}
