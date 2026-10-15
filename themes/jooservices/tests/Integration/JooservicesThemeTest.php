<?php
/**
 * The jooservices theme on the core (real WordPress, theme loaded through JOO_TEST_THEME=jooservices).
 *
 * @package JOOservices
 */

declare(strict_types=1);

namespace JOOservices\Jooservices\Tests\Integration;

use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Kernel;

final class JooservicesThemeTest extends IntegrationTestCase {

	private const DIR = '/workspace/themes/jooservices';

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
		foreach ( array_merge( array( self::DIR . '/functions.php' ), glob( self::DIR . '/inc/*.php' ) ?: array() ) as $file ) {
			foreach ( file( $file ) as $line ) {
				$t = trim( $line );
				if ( '' !== $t && ! str_starts_with( $t, '*' ) && ! str_starts_with( $t, '/**' ) && ! str_starts_with( $t, '//' ) ) {
					++$lines;
				}
			}
		}
		$this->assertLessThanOrEqual( 80, $lines, 'functions.php + inc/ non-comment lines' );
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

	public function test_the_style_variations_define_the_same_palette_slugs(): void {
		$slugs = static function ( string $file ): array {
			$json = json_decode( (string) file_get_contents( $file ), true );
			return array_column( $json['settings']['color']['palette'], 'slug' );
		};
		$base  = $slugs( self::DIR . '/theme.json' );
		$this->assertSame( $base, $slugs( self::DIR . '/styles/dark.json' ) );
		$this->assertSame( $base, $slugs( self::DIR . '/styles/light.json' ) );
	}
}
