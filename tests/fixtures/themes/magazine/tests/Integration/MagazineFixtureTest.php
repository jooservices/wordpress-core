<?php
/**
 * Magazine fixture proves that a grid theme can run on the core alone.
 *
 * @package JOOservices
 */

declare(strict_types=1);

namespace JOOservices\Magazine\Tests\Integration;

use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Assets\AssetRegistry;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Kernel;

final class MagazineFixtureTest extends IntegrationTestCase {

	private const DIR = __DIR__ . '/../..';

	public function set_up(): void {
		parent::set_up();
		if ( null === Kernel::container() || 'magazine' !== Kernel::container()->get( Manifest::class )->slug() ) {
			$this->markTestSkipped( 'Run with JOO_TEST_THEME=magazine (make test-integration THEME=magazine).' );
		}
	}

	public function test_manifest_matches_the_magazine_spec_and_terminal_is_disabled(): void {
		$manifest = Kernel::container()->get( Manifest::class );
		$root     = dirname( (string) realpath( self::DIR ), 4 );
		$spec     = json_decode( (string) file_get_contents( $root . '/docs/spec/examples/magazine.json' ), true );

		$this->assertTrue( $manifest->isValid(), implode( '; ', $manifest->errors() ) );
		$this->assertSame( $spec, $manifest->raw() );
		$this->assertFalse( $manifest->modules()['terminal'] );
		$this->assertSame( 'disabled', Kernel::status()['terminal'] );
	}

	public function test_resume_plugin_is_not_loaded(): void {
		$this->assertFalse( defined( 'JOO_RESUME_FILE' ), 'The Magazine fixture must run without the Resume plugin.' );
	}

	public function test_disabled_terminal_registers_no_hook_route_or_asset(): void {
		$this->assertSame( array(), $this->terminalHooks() );

		$routes = rest_get_server()->get_routes();
		$this->assertArrayNotHasKey( '/joo/v1/terminal/fs', $routes );

		/** @var AssetRegistry $assets */
		$assets       = Kernel::container()->get( AssetRegistry::class );
		$coreManifest = $assets->manifest();
		$this->assertNotNull( $coreManifest );
		$this->assertTrue( $coreManifest->has( 'terminal' ), 'The build contains a terminal asset so this assertion is meaningful.' );
		$this->assertArrayNotHasKey( 'terminal', $assets->lazyModules() );
	}

	public function test_manifest_disabled_blocks_are_not_registered_or_used_by_templates(): void {
		$registry = \WP_Block_Type_Registry::get_instance();
		foreach ( array( 'joo/companies', 'joo/profile-card', 'joo/terminal-dock' ) as $block ) {
			$this->assertFalse( $registry->is_registered( $block ), $block );
		}

		foreach ( array_merge( glob( self::DIR . '/templates/*.html' ) ?: array(), glob( self::DIR . '/parts/*.html' ) ?: array() ) as $file ) {
			$markup = (string) file_get_contents( $file );
			foreach ( array( 'joo/companies', 'joo/profile-card', 'joo/terminal-dock', 'rail' ) as $disabled ) {
				$this->assertStringNotContainsString( $disabled, $markup, basename( $file ) );
			}
		}
		$this->assertStringContainsString( '"type":"grid"', (string) file_get_contents( self::DIR . '/templates/home.html' ) );
	}

	public function test_home_single_category_tag_search_and_404_render_without_php_notices(): void {
		$category = self::factory()->category->create( array( 'name' => 'Magazine fixture category' ) );
		$tag      = self::factory()->tag->create( array( 'name' => 'Magazine fixture tag' ) );
		$post     = self::factory()->post->create(
			array(
				'post_title'    => 'Magazine fixture article',
				'post_content'  => '<!-- wp:paragraph --><p>Magazine fixture article content.</p><!-- /wp:paragraph -->',
				'post_category' => array( $category ),
			)
		);
		wp_set_post_terms( $post, array( $tag ), 'post_tag' );

		$views = array(
			'home'     => array( home_url( '/' ), 'home' ),
			'single'   => array( get_permalink( $post ), 'single' ),
			'category' => array( get_category_link( $category ), 'archive' ),
			'tag'      => array( get_tag_link( $tag ), 'archive' ),
			'search'   => array( add_query_arg( 's', 'Magazine fixture article', home_url( '/' ) ), 'search' ),
			'404'      => array( home_url( '/magazine-fixture-missing/' ), '404' ),
		);

		foreach ( $views as $name => $view ) {
			$this->go_to( $view[0] );
			if ( 'single' === $name && have_posts() ) {
				the_post();
			}
			$html = $this->renderTemplate( $view[1] );
			$this->assertStringContainsString( '<main', $html, $name . ' rendered its main landmark' );
		}
	}

	/**
	 * Expand the fixture's template parts and render the block markup for the current query.
	 *
	 * @param string $name Template file name.
	 */
	private function renderTemplate( string $name ): string {
		$markup = (string) file_get_contents( self::DIR . '/templates/' . $name . '.html' );
		$markup = (string) preg_replace_callback(
			'/<!-- wp:template-part \{"slug":"([a-z-]+)"\} \/-->/',
			static fn( array $match ): string => (string) file_get_contents( self::DIR . '/parts/' . $match[1] . '.html' ),
			$markup
		);
		$notices = array();
		set_error_handler(
			static function ( int $severity, string $message, string $file, int $line ) use ( &$notices ): bool {
				$notices[] = sprintf( '%s:%d %s', $file, $line, $message );
				return true;
			},
			E_NOTICE | E_USER_NOTICE
		);
		try {
			$html = do_blocks( $markup );
		} finally {
			restore_error_handler();
		}
		$this->assertSame( array(), $notices, 'PHP notices while rendering ' . $name );
		return $html;
	}

	/**
	 * Finds hooks registered from TerminalModule or its route object.
	 *
	 * @return list<string>
	 */
	private function terminalHooks(): array {
		$hooks      = array();
		$moduleFile = realpath( JOO_CORE_DIR . '/src/Terminal/TerminalModule.php' );
		foreach ( (array) ( $GLOBALS['wp_filter'] ?? array() ) as $name => $hook ) {
			if ( ! $hook instanceof \WP_Hook ) {
				continue;
			}
			foreach ( $hook->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];
					if ( is_array( $function ) && isset( $function[0] ) && $function[0] instanceof \JOOservices\ThemeCore\Terminal\Rest\FsRoute ) {
						$hooks[] = (string) $name;
					}
					if ( $function instanceof \Closure ) {
						$file = realpath( ( new \ReflectionFunction( $function ) )->getFileName() ?: '' );
						if ( $moduleFile && $file === $moduleFile ) {
							$hooks[] = (string) $name;
						}
					}
				}
			}
		}
		return array_values( array_unique( $hooks ) );
	}
}
