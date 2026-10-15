<?php
/**
 * Block foundation (real WordPress).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Assets\AssetManifest;
use JOOservices\ThemeCore\Assets\AssetRegistry;
use JOOservices\ThemeCore\Blocks\BlockRegistrar;
use JOOservices\ThemeCore\Blocks\DynamicBlock;
use JOOservices\ThemeCore\Blocks\EditorBundle;
use JOOservices\ThemeCore\Blocks\Html;
use JOOservices\ThemeCore\Blocks\PatternLoader;
use JOOservices\ThemeCore\Blocks\TemplateLocator;
use JOOservices\ThemeCore\Blocks\ThemeScheme;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Kernel;

final class BlocksTest extends IntegrationTestCase {

	private const FIXTURES = '/workspace/packages/theme-core/tests/Fixtures';

	public function set_up(): void {
		parent::set_up();
		Kernel::reset();
		Notices::reset();
	}

	public function tear_down(): void {
		foreach ( array( 'joo/hello', 'joo/hidden', 'joo/boom' ) as $name ) {
			if ( \WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}
		Kernel::reset();
		parent::tear_down();
	}

	/**
	 * Boots with the blocks fixture manifest and the fixture block folders, then registers the blocks.
	 */
	private function boot( string $manifest = 'blocks.json', bool $withBlocks = true ): BlockRegistrar {
		if ( $withBlocks ) {
			add_filter( 'joo/blocks/register', static fn( $dirs ) => array_merge( (array) $dirs, array( self::FIXTURES . '/blocks/hello', self::FIXTURES . '/blocks/hidden', self::FIXTURES . '/blocks/boom' ) ) );
		}
		Kernel::boot( self::FIXTURES . '/manifests/' . $manifest );
		do_action( 'after_setup_theme' );
		$registrar = Kernel::container()->get( BlockRegistrar::class );
		$registrar->register();
		return $registrar;
	}

	public function test_a_folder_with_block_json_and_render_php_becomes_a_block(): void {
		$registrar = $this->boot();
		$this->assertContains( 'joo/hello', $registrar->names() );
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( 'joo/hello' );
		$this->assertNotNull( $type );
		$this->assertSame( 'joo', $type->category );
		$this->assertIsCallable( $type->render_callback );
		$out = do_blocks( '<!-- wp:joo/hello {"name":"Viet"} /-->' );
		$this->assertStringContainsString( 'Hello Viet', $out );
		$this->assertStringContainsString( 'wp-block-joo-hello', $out, 'wrapper attributes are applied' );
		$this->assertStringContainsString( 'Hello World', do_blocks( '<!-- wp:joo/hello /-->' ), 'attribute defaults' );
	}

	public function test_blocks_disabled_in_the_manifest_are_not_registered(): void {
		$registrar = $this->boot();
		$this->assertNotContains( 'joo/hidden', $registrar->names() );
		$this->assertFalse( \WP_Block_Type_Registry::get_instance()->is_registered( 'joo/hidden' ) );
	}

	public function test_the_block_category_is_added(): void {
		$this->boot();
		$slugs = array_column( apply_filters( 'block_categories_all', array(), new \WP_Block_Editor_Context() ), 'slug' );
		$this->assertContains( 'joo', $slugs );
	}

	public function test_malicious_attributes_are_escaped(): void {
		$this->boot();
		$out = do_blocks( '<!-- wp:joo/hello {"name":"<script>alert(1)<\/script><img src=x onerror=alert(2)>"} /-->' );
		$this->assertStringNotContainsString( '<script>', $out );
		$this->assertStringNotContainsString( '<img', $out );
		$this->assertStringContainsString( '&lt;script&gt;', $out );
	}

	public function test_data_and_html_filters_apply(): void {
		$this->boot();
		add_filter( 'joo/blocks/data/hello', static fn( $data ) => array_merge( $data, array( 'name' => 'Filtered' ) ) );
		add_filter( 'joo/blocks/html/hello', static fn( $html ) => $html . '<!--after-->' );
		$out = do_blocks( '<!-- wp:joo/hello {"name":"Original"} /-->' );
		$this->assertStringContainsString( 'Hello Filtered', $out );
		$this->assertStringEndsWith( '<!--after-->', trim( $out ) );
	}

	public function test_a_theme_template_overrides_the_core_template(): void {
		$this->boot();
		add_filter( 'stylesheet_directory', static fn() => self::FIXTURES . '/theme-override' );
		$out = do_blocks( '<!-- wp:joo/hello {"name":"Viet"} /-->' );
		$this->assertStringContainsString( 'class="override"', $out );
		$this->assertStringContainsString( 'Theme says hi to Viet', $out );
		$this->assertStringNotContainsString( 'Hello Viet', $out );
	}

	public function test_template_lookup_precedence_child_parent_then_block_folder(): void {
		$dir = self::FIXTURES . '/blocks/hello';
		$this->assertSame( $dir . '/render.php', TemplateLocator::locate( 'hello', $dir ) );
		add_filter( 'template_directory', static fn() => self::FIXTURES . '/theme-override' );
		$this->assertSame( self::FIXTURES . '/theme-override/parts/joo/hello.php', TemplateLocator::locate( 'hello', $dir ), 'parent theme' );
		add_filter( 'stylesheet_directory', static fn() => self::FIXTURES . '/nonexistent' );
		$this->assertSame( self::FIXTURES . '/theme-override/parts/joo/hello.php', TemplateLocator::locate( 'hello', $dir ), 'child without file falls through' );
		$this->assertSame( '', TemplateLocator::locate( '../etc', $dir ), 'path traversal is refused' );
		$this->assertSame( '', TemplateLocator::locate( 'nothing', self::FIXTURES . '/nonexistent' ) );
	}

	public function test_a_template_exception_hides_the_block_and_keeps_the_page(): void {
		$this->boot();
		$before = ob_get_level();
		$out    = do_blocks( 'before <!-- wp:joo/boom /--> after' );
		$this->assertSame( $before, ob_get_level(), 'output buffers are closed' );
		$this->assertStringNotContainsString( 'partial output', $out );
		$this->assertStringContainsString( 'before', $out );
		$this->assertStringContainsString( 'after', $out );
	}

	public function test_a_block_class_can_cache_its_output_for_visitors_only(): void {
		$this->boot();
		$calls = 0;
		$block = new class( Kernel::container(), $calls ) extends DynamicBlock {
			/** @var int */
			public int $calls = 0;
			public function __construct( \JOOservices\ThemeCore\Container\Container $c, int &$calls ) {
				parent::__construct( $c );
				$this->calls = &$calls;
			}
			public static function name(): string {
				return 'joo/hello';
			}
			protected function data( array $attrs, \WP_Block $block ): array {
				++$this->calls;
				return $attrs;
			}
			public function cacheable(): bool {
				return true;
			}
		};
		$block->setDir( self::FIXTURES . '/blocks/hello' );
		$wp_block = new \WP_Block(
			array(
				'blockName' => 'joo/hello',
				'attrs'     => array( 'name' => 'Cached' ),
			)
		);
		$block->render( array( 'name' => 'Cached' ), '', $wp_block );
		$block->render( array( 'name' => 'Cached' ), '', $wp_block );
		$this->assertSame( 1, $calls, 'second render comes from the cache' );
		$block->render( array( 'name' => 'Other' ), '', $wp_block );
		$this->assertSame( 2, $calls, 'a different attribute is a different key' );
		wp_set_current_user( 1 );
		$block->render( array( 'name' => 'Cached' ), '', $wp_block );
		$block->render( array( 'name' => 'Cached' ), '', $wp_block );
		$this->assertSame( 4, $calls, 'logged-in users are never served cached output' );
		$this->assertSame( 'hello', $block::slug() );
	}

	public function test_view_module_and_style_are_wired_from_the_build_manifest_and_load_on_render(): void {
		add_filter( 'joo/blocks/register', static fn( $dirs ) => array_merge( (array) $dirs, array( self::FIXTURES . '/blocks/hello' ) ) );
		Kernel::boot( self::FIXTURES . '/manifests/blocks.json' );
		do_action( 'after_setup_theme' );
		Kernel::container()->get( AssetRegistry::class )->addManifest(
			'core',
			new AssetManifest(
				array(
					'blocks/hello/view'  => array(
						'type' => 'js',
						'file' => 'blocks/hello/view-ABC123.js',
						'size' => 10,
						'gzip' => 5,
					),
					'blocks/hello/style' => array(
						'type' => 'css',
						'file' => 'blocks/hello/style-DEF456.css',
						'size' => 10,
						'gzip' => 5,
					),
				),
				'https://cdn.example.test/build/'
			)
		);
		Kernel::container()->get( BlockRegistrar::class )->register();
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( 'joo/hello' );
		$this->assertSame( array( 'joo-block-hello' ), $type->view_script_module_ids );
		$this->assertSame( array( 'joo-block-hello' ), $type->style_handles );
		$this->assertFalse( wp_style_is( 'joo-block-hello', 'enqueued' ), 'not loaded before the block renders' );
		do_blocks( '<!-- wp:joo/hello /-->' );
		$this->assertTrue( wp_style_is( 'joo-block-hello', 'enqueued' ), 'loaded when the block renders' );
		$this->assertStringContainsString( 'style-DEF456.css', wp_styles()->registered['joo-block-hello']->src );
		$this->assertStringContainsString( 'view-ABC123.js', (string) $this->moduleSrc( 'joo-block-hello' ) );
	}

	private function moduleSrc( string $id ): ?string {
		$modules = wp_script_modules();
		$ref     = new \ReflectionObject( $modules );
		if ( ! $ref->hasProperty( 'registered' ) ) {
			return null;
		}
		$prop = $ref->getProperty( 'registered' );
		$prop->setAccessible( true );
		$registered = $prop->getValue( $modules );
		return $registered[ $id ]['src'] ?? null;
	}

	public function test_the_editor_bundle_describes_blocks_with_their_generated_controls(): void {
		$this->boot();
		$descriptions = Kernel::container()->get( EditorBundle::class )->descriptions();
		$names        = array_column( $descriptions, 'name' );
		$this->assertContains( 'joo/hello', $names );
		$this->assertNotContains( 'joo/hidden', $names );
		$hello = $descriptions[ array_search( 'joo/hello', $names, true ) ];
		$this->assertSame( array( 'limit', 'show' ), array_column( $hello['controls'], 'attribute' ) );
		$this->assertArrayHasKey( 'limit', $hello['attributes'] );
		$boom = $descriptions[ array_search( 'joo/boom', $names, true ) ];
		$this->assertSame( array(), $boom['controls'], 'a block without x-joo only gets the preview' );
	}

	public function test_the_theme_scheme_prints_one_small_script_and_a_no_js_default(): void {
		$this->boot();
		$head = $this->capture( 'wp_head' );
		$this->assertSame( 1, substr_count( $head, 'id="joo-theme-init"' ) );
		$script = Kernel::container()->get( ThemeScheme::class )->script();
		$this->assertLessThanOrEqual( ThemeScheme::MAX_SCRIPT_BYTES, strlen( $script ) );
		$this->assertStringContainsString( "localStorage.getItem('joo-theme')", $script );
		$this->assertStringContainsString( 'prefers-color-scheme', $script );
		$this->assertStringContainsString( 'data-theme="dark"', get_language_attributes() );
		add_filter( 'joo/assets/inline_script_nonce', static fn() => 'abc123' );
		$this->assertStringContainsString( 'nonce="abc123"', $this->capture( 'wp_head' ) );
	}

	public function test_a_single_scheme_prints_no_script(): void {
		$this->boot( 'single-scheme.json', false );
		$scheme = Kernel::container()->get( ThemeScheme::class );
		$this->assertSame( '', $scheme->script() );
		$this->assertSame( 'light', $scheme->defaultScheme() );
		$this->assertStringNotContainsString( 'joo-theme-init', $this->capture( 'wp_head' ) );
		$this->assertStringContainsString( 'data-theme="light"', get_language_attributes() );
	}

	public function test_the_pattern_category_and_extra_folders_register(): void {
		$this->boot( 'blocks.json', false );
		$loader = Kernel::container()->get( PatternLoader::class );
		$loader->register();
		$this->assertTrue( \WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( 'jooservices' ) );
		$slugs = $loader->fromDirectory( self::FIXTURES . '/patterns' );
		$this->assertSame( array( 'jooservices/hero' ), $slugs, 'the pattern without a slug is skipped' );
		$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( 'jooservices/hero' );
		$this->assertSame( array( 'jooservices' ), $pattern['categories'] );
		$this->assertSame( 1200, $pattern['viewportWidth'] );
		$this->assertStringContainsString( 'Hero pattern', $pattern['content'] );
		$this->assertSame( array(), $loader->fromDirectory( self::FIXTURES . '/patterns' ), 'registering twice is a no-op' );
		unregister_block_pattern( 'jooservices/hero' );
	}

	public function test_html_helpers_escape(): void {
		$this->assertSame( '&lt;b&gt;', Html::e( '<b>' ) );
		$this->assertSame(
			' href="https://x.test/?a=1&amp;b=2" disabled',
			Html::attrs(
				array(
					'href'     => 'https://x.test/?a=1&b=2',
					'disabled' => true,
					'hidden'   => false,
					'none'     => null,
				)
			)
		);
		$this->assertSame(
			'',
			Html::attrs(
				array(
					'onclick'  => 'alert(1)',
					'bad name' => 'x',
				)
			),
			'event handlers and invalid names are dropped'
		);
		$this->assertSame( '<a href="https://x.test/">Hi &lt;there&gt;</a>', Html::link( 'https://x.test/', 'Hi <there>' ) );
		$this->assertSame( 'Plain', Html::link( 'javascript:alert(1)', 'Plain' ), 'unsafe URLs become plain text' );
		$image = new \JOOservices\ThemeCore\Content\Model\ImageRef( 'https://x.test/a.jpg', 640, 480, 'A "photo"' );
		$tag   = Html::img( $image, array( 'class' => 'thumb' ) );
		$this->assertStringContainsString( 'width="640"', $tag );
		$this->assertStringContainsString( 'loading="lazy"', $tag );
		$this->assertStringContainsString( 'alt="A &quot;photo&quot;"', $tag );
		$this->assertStringNotContainsString( 'loading=', Html::img( $image, array( 'eager' => true ) ) );
		$this->assertSame( '', Html::img( null ) );
	}
}
