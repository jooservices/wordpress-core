<?php
/**
 * The jooservices theme on the core (real WordPress, theme loaded through JOO_TEST_THEME=jooservices).
 *
 * @package JOOservices
 */

declare(strict_types=1);

namespace JOOservices\Jooservices\Tests\Integration;

use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\Jooservices\BuyerOptions;
use JOOservices\ThemeCore\Blocks\TemplateLocator;
use JOOservices\ThemeCore\Blocks\PatternLoader;
use JOOservices\ThemeCore\Blocks\ThemeScheme;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Kernel;

final class JooservicesThemeTest extends IntegrationTestCase {

	private const DIR = __DIR__ . '/../..';

	public function set_up(): void {
		parent::set_up();
		if ( null === Kernel::container() || 'jooservices' !== Kernel::container()->get( Manifest::class )->slug() ) {
			$this->markTestSkipped( 'Run with JOO_TEST_THEME=jooservices (make test-integration THEME=jooservices TEST_THEME=jooservices).' );
		}
	}

	public function test_the_manifest_is_valid_and_every_module_booted(): void {
		$manifest = Kernel::container()->get( Manifest::class );
		$this->assertTrue( $manifest->isValid(), implode( '; ', $manifest->errors() ) );
		foreach ( Kernel::registry()->status() as $id => $status ) {
			$this->assertSame( 'booted', $status, 'module ' . $id );
		}
	}

	public function test_theme_php_stays_within_the_line_budget(): void {
		$lines = 0;
		foreach ( file( self::DIR . '/functions.php' ) as $line ) {
			$t = trim( $line );
			if ( '' !== $t && ! str_starts_with( $t, '*' ) && ! str_starts_with( $t, '/**' ) && ! str_starts_with( $t, '//' ) ) {
				++$lines;
			}
		}
		$this->assertLessThanOrEqual( 80, $lines, 'functions.php non-comment lines' );
	}

	public function test_image_sizes_come_from_the_manifest(): void {
		$sizes = wp_get_additional_image_sizes();
		foreach ( array( 'joo-card', 'joo-cover', 'joo-thumb', 'joo-avatar' ) as $name ) {
			$this->assertArrayHasKey( $name, $sizes, $name );
		}
		$this->assertSame( 640, $sizes['joo-card']['width'] );
		$this->assertTrue( $sizes['joo-card']['crop'] );
	}

	public function test_menu_locations_are_registered(): void {
		$locations = get_registered_nav_menus();
		$this->assertArrayHasKey( 'primary', $locations );
		$this->assertArrayHasKey( 'footer', $locations );
	}

	public function test_the_theme_style_is_registered_after_the_global_styles(): void {
		$this->go_to( '/' );
		do_action( 'wp_enqueue_scripts' );
		$this->assertTrue( wp_style_is( 'jooservices-theme', 'registered' ) );
		$this->assertTrue( wp_style_is( 'jooservices-theme', 'enqueued' ) );
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

	public function test_templates_and_parts_only_use_registered_blocks(): void {
		$files = array_merge( glob( self::DIR . '/templates/*.html' ) ?: array(), glob( self::DIR . '/parts/*.html' ) ?: array() );
		$this->assertGreaterThanOrEqual( 15, count( $files ) );
		foreach ( $files as $file ) {
			foreach ( $this->blockNames( (string) file_get_contents( $file ) ) as $name ) {
				$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( $name ), basename( $file ) . ': ' . $name . ' is not registered' );
			}
		}
	}

	public function test_every_template_part_listed_in_theme_json_exists(): void {
		$json = json_decode( (string) file_get_contents( self::DIR . '/theme.json' ), true );
		foreach ( $json['templateParts'] as $part ) {
			$this->assertFileExists( self::DIR . '/parts/' . $part['name'] . '.html' );
		}
		foreach ( $json['customTemplates'] as $template ) {
			$this->assertFileExists( self::DIR . '/templates/' . $template['name'] . '.html' );
		}
	}

	public function test_header_and_footer_keep_buyer_content_in_editable_site_editor_blocks(): void {
		$header  = (string) file_get_contents( self::DIR . '/parts/header.html' );
		$footer  = (string) file_get_contents( self::DIR . '/parts/footer.html' );
		$pattern = (string) file_get_contents( self::DIR . '/patterns/site-footer.php' );
		$this->assertStringContainsString( 'wp:site-logo', $header );
		$this->assertStringContainsString( 'wp:site-title', $header );
		$this->assertStringContainsString( 'jooservices/site-footer', $footer );
		$this->assertStringContainsString( 'joo-footer__socials', $pattern );
		$this->assertStringContainsString( 'wp:joo/profile-card', $pattern );
		$this->assertStringContainsString( "esc_html__( 'Connect', 'jooservices' )", $pattern );
	}

	public function test_the_style_variations_define_the_same_palette_slugs(): void {
		$slugs = static function ( string $file ): array {
			$json = json_decode( (string) file_get_contents( $file ), true );
			return array_column( $json['settings']['color']['palette'], 'slug' );
		};
		$base  = $slugs( self::DIR . '/theme.json' );
		$this->assertSame( $base, $slugs( self::DIR . '/styles/dark.json' ) );
		$this->assertSame( $base, $slugs( self::DIR . '/styles/light.json' ) );
		$this->assertSame( $base, $slugs( self::DIR . '/styles/ocean.json' ) );
		$this->assertSame( $base, $slugs( self::DIR . '/styles/forest.json' ) );
		$this->assertSame( $base, $slugs( self::DIR . '/styles/paper.json' ) );
	}

	public function test_all_five_style_variations_are_available_to_the_editor_and_runtime(): void {
		$schemes = Kernel::container()->get( ThemeScheme::class )->schemes();
		$this->assertSame( array( 'dark', 'light', 'ocean', 'forest', 'paper' ), $schemes );
		foreach ( $schemes as $scheme ) {
			$this->assertFileExists( self::DIR . '/styles/' . $scheme . '.json' );
		}
		$script = Kernel::container()->get( ThemeScheme::class )->script();
		$this->assertStringContainsString( 'a.includes(s)', $script );
		$this->assertLessThanOrEqual( ThemeScheme::MAX_SCRIPT_BYTES, strlen( $script ) );
	}

	public function test_buyer_patterns_are_registered_in_the_joo_services_editor_category(): void {
		$original_theme = get_stylesheet();
		switch_theme( 'jooservices' );
		try {
			Kernel::container()->get( PatternLoader::class )->register();
			$registry = \WP_Block_Patterns_Registry::get_instance();
			foreach ( array( 'hero', 'about', 'contact', '404-search', '404-terminal', 'newsletter', 'site-footer' ) as $slug ) {
				$name = 'jooservices/' . $slug;
				$this->assertTrue( $registry->is_registered( $name ), $name );
				$this->assertSame( array( 'jooservices' ), $registry->get_registered( $name )['categories'] );
			}
		} finally {
			if ( 'jooservices' !== $original_theme ) {
				switch_theme( $original_theme );
			}
		}
	}

	public function test_theme_manifest_preserves_existing_rendered_block_snapshot_values(): void {
		$manifest = Kernel::container()->get( Manifest::class );
		$this->assertSame( '/ai/prompt-caching', $manifest->get( 'blocks.notFoundPlaceholder' ) );
		$this->assertSame( 'about', $manifest->get( 'content.profilePagePath' ) );
		$this->assertSame( array( 'ls', 'tree', 'help', 'grep -ri docker .' ), $manifest->get( 'terminal.shortcuts' ) );
		$categories  = apply_filters( 'block_categories_all', array(), new \WP_Block_Editor_Context() );
		$jooCategory = array_values( array_filter( $categories, static fn( $category ) => 'joo' === $category['slug'] ) )[0] ?? null;
		$this->assertSame( 'JOOservices', $jooCategory['title'] ?? null );

		wp_update_user(
			array(
				'ID'           => 1,
				'display_name' => 'Snapshot Owner',
			)
		);
		$about = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_name'  => 'about',
				'post_title' => 'About',
			)
		);
		wp_cache_flush();
		$profile = do_blocks( '<!-- wp:joo/profile-card /-->' );
		$this->assertStringContainsString( '<a class="joo-profile__who" href="' . esc_url( get_permalink( $about ) ) . '">', $profile );

		$notFound = do_blocks( '<!-- wp:joo/not-found /-->' );
		preg_match( '/<input id="joo-404-path"[^>]+>/', $notFound, $match );
		$this->assertSame( '<input id="joo-404-path" name="path" autocomplete="off" placeholder="/ai/prompt-caching">', $match[0] ?? '' );
		$config = apply_filters( 'joo/assets/runtime_config', array() );
		$this->assertSame( array( 'ls', 'tree', 'help', 'grep -ri docker .' ), $config['terminalShortcuts'] );
	}

	public function test_buyer_options_change_frontend_output(): void {
		$settings = Kernel::container()->get( Settings::class );
		$options  = new BuyerOptions( $settings );
		$settings->save(
			array(
				'features.rail'           => false,
				'features.slider'         => false,
				'features.back_to_top'    => false,
				'content.rail_profile'    => false,
				'content.rail_companies'  => false,
				'content.rail_categories' => false,
				'content.rail_topics'     => false,
				'content.rail_tags'       => false,
				'appearance.card_density' => 'compact',
				'appearance.date_format'  => 'Y-m-d',
			)
		);
		try {
			$railPart = array(
				'blockName' => 'core/template-part',
				'attrs'     => array( 'slug' => 'rail' ),
			);
			$slider   = array( 'blockName' => 'joo/featured-slider' );
			$this->assertSame( '', $options->filterBlock( '<aside>rail</aside>', $railPart ) );
			$this->assertSame( '', $options->filterBlock( '<div>slider</div>', $slider ) );
			foreach (
				array(
					'joo/profile-card'  => array( 'joo-rail__profile', 'content.rail_profile' ),
					'joo/companies'     => array( 'joo-rail__companies', 'content.rail_companies' ),
					'joo/category-tree' => array( 'joo-rail__categories', 'content.rail_categories' ),
					'joo/top-topics'    => array( 'joo-rail__topics', 'content.rail_topics' ),
					'joo/tag-list'      => array( 'joo-rail__tags', 'content.rail_tags' ),
				) as $name => $entry
			) {
				$parsed = array(
					'blockName' => $name,
					'attrs'     => array( 'className' => $entry[0] ),
				);
				$this->assertSame( '', $options->filterBlock( '<div>block</div>', $parsed ), $entry[1] );
			}
			$paragraph = array( 'blockName' => 'core/paragraph' );
			$this->assertSame( '<p>kept</p>', $options->filterBlock( '<p>kept</p>', $paragraph ) );
			$footerProfile = array(
				'blockName' => 'joo/profile-card',
				'attrs'     => array( 'className' => 'joo-footer__socials' ),
			);
			$this->assertSame( '<section>kept</section>', $options->filterBlock( '<section>kept</section>', $footerProfile ) );
			$this->assertContains( 'joo-card-density--compact', $options->bodyClasses( array() ) );
			$this->assertSame( 'Y-m-d', $options->dateFormat( false ) );
			ob_start();
			$options->topAnchor();
			$this->assertSame( '<span id="top"></span>', (string) ob_get_clean() );
			ob_start();
			$options->backToTop();
			$this->assertSame( '', (string) ob_get_clean() );
		} finally {
			$settings->repository()->reset();
		}
		$this->assertNotContains( 'joo-card-density--compact', $options->bodyClasses( array() ) );
		$this->assertFalse( $options->dateFormat( false ) );
		ob_start();
		$options->backToTop();
		$this->assertStringContainsString( 'href="#top"', (string) ob_get_clean() );
	}

	public function test_sample_child_theme_overrides_a_template_part_and_block_template(): void {
		$child  = __DIR__ . '/../fixtures/sample-child';
		$filter = static fn() => $child;
		add_filter( 'stylesheet_directory', $filter );
		try {
			$part = locate_template( 'parts/footer.html', false, false );
			$this->assertSame( $child . '/parts/footer.html', $part );
			$this->assertStringContainsString( 'Footer override from the sample child theme', (string) file_get_contents( $part ) );

			$blockTemplate = TemplateLocator::locate( 'profile-card', dirname( __DIR__, 4 ) . '/packages/theme-core/resources/blocks/profile-card' );
			$this->assertSame( $child . '/parts/joo/profile-card.php', $blockTemplate );
			$this->assertStringContainsString( 'Child block template override', do_blocks( '<!-- wp:joo/profile-card /-->' ) );
		} finally {
			remove_filter( 'stylesheet_directory', $filter );
		}
	}
}
