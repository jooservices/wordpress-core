<?php
/**
 * Manifest and loader tests.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Config;

use Brain\Monkey\Functions;
use JOOservices\TestKit\UnitTestCase;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\ManifestLoader;

final class ManifestTest extends UnitTestCase {

	private function loader(): ManifestLoader {
		return new ManifestLoader( null, dirname( __DIR__, 3 ) . '/schema/manifest.schema.json' );
	}

	public function test_dotted_get_has_and_defaults(): void {
		$m = new Manifest(
			array(
				'lazy' => array(
					'eagerCount' => 2,
					'images'     => false,
				),
				'slug' => 'x',
			)
		);
		$this->assertSame( 2, $m->get( 'lazy.eagerCount' ) );
		$this->assertSame( 'fallback', $m->get( 'lazy.nope', 'fallback' ) );
		$this->assertTrue( $m->has( 'lazy.images' ), 'false values still count as present' );
		$this->assertFalse( $m->has( 'lazy.nope' ) );
		$this->assertSame( 'x', $m->slug() );
	}

	public function test_fallback_slug_name_and_core_range(): void {
		$m = Manifest::invalid( array( 'broken' ), 'my-theme' );
		$this->assertFalse( $m->isValid() );
		$this->assertSame( 'my-theme', $m->slug() );
		$this->assertSame( 'my-theme', $m->name() );
		$this->assertSame( '*', $m->coreRange() );
		$this->assertSame( array( 'broken' ), $m->errors() );
	}

	public function test_modules_are_cast_to_booleans(): void {
		$m = new Manifest(
			array(
				'modules' => array(
					'seo'  => 1,
					'lazy' => false,
				),
			)
		);
		$this->assertSame(
			array(
				'seo'  => true,
				'lazy' => false,
			),
			$m->modules()
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function validExamples(): array {
		$out = array();
		foreach ( glob( dirname( __DIR__, 5 ) . '/docs/spec/examples/*.json' ) ?: array() as $file ) {
			$out[ basename( $file ) ] = array( $file );
		}
		return $out;
	}

	/**
	 * @dataProvider validExamples
	 */
	public function test_spec_examples_are_valid( string $file ): void {
		$manifest = $this->loader()->parse( (string) file_get_contents( $file ) );
		$this->assertSame( array(), $manifest->errors() );
		$this->assertTrue( $manifest->isValid() );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function invalidFixtures(): array {
		$out = array();
		foreach ( glob( dirname( __DIR__, 2 ) . '/Fixtures/manifest-invalid/*.json' ) ?: array() as $file ) {
			$out[ basename( $file ) ] = array( $file );
		}
		return $out;
	}

	/**
	 * @dataProvider invalidFixtures
	 */
	public function test_invalid_fixtures_are_rejected( string $file ): void {
		$manifest = $this->loader()->parse( (string) file_get_contents( $file ) );
		$this->assertFalse( $manifest->isValid(), basename( $file ) );
		$this->assertNotEmpty( $manifest->errors() );
		$this->assertStringContainsString( '/', $manifest->errors()[0], 'errors carry JSON pointers' );
	}

	public function test_broken_json_empty_and_non_object_documents(): void {
		$loader = $this->loader();
		$this->assertStringContainsString( 'not valid JSON', $loader->parse( '{oops' )->errors()[0] );
		$this->assertStringContainsString( 'not valid JSON', $loader->parse( '' )->errors()[0] );
		$this->assertStringContainsString( 'JSON object', $loader->parse( '"text"' )->errors()[0] );
	}

	public function test_utf8_bom_is_ignored(): void {
		$json = "\xEF\xBB\xBF" . '{"schema":1,"slug":"bom","name":"Bom","core":"^0.1"}';
		$this->assertTrue( $this->loader()->parse( $json )->isValid() );
	}

	public function test_manifest_validation_distinguishes_json_arrays_and_objects(): void {
		$loader  = $this->loader();
		$invalid = $loader->parse( '{"schema":1,"slug":"edge-case","name":"Edge case","core":"^0.1","features":[]}' );
		$valid   = $loader->parse( '{"schema":1,"slug":"edge-case","name":"Edge case","core":"^0.1","features":{},"modules":{"0":true}}' );

		$this->assertFalse( $invalid->isValid() );
		$this->assertStringContainsString( '/features: expected object, got array', $invalid->errors()[0] );
		$this->assertTrue( $valid->isValid(), implode( '; ', $valid->errors() ) );
		$this->assertSame( array( 0 => true ), $valid->modules() );
	}

	public function test_missing_file_and_oversized_file(): void {
		$loader = $this->loader();
		$this->assertStringContainsString( 'not found', $loader->load( '/nonexistent/joo-theme.json' )->errors()[0] );
		$file = tempnam( sys_get_temp_dir(), 'joo' );
		file_put_contents( (string) $file, str_repeat( ' ', 300000 ) );
		$this->assertStringContainsString( 'larger than', $loader->load( (string) $file )->errors()[0] );
		unlink( (string) $file );
	}

	public function test_load_uses_and_fills_the_transient_cache(): void {
		$file = tempnam( sys_get_temp_dir(), 'joo' );
		file_put_contents( (string) $file, '{"schema":1,"slug":"cached","name":"Cached","core":"^0.1"}' );
		$store = array();
		Functions\when( 'get_transient' )->alias( static fn( $key ) => $store[ $key ] ?? false );
		Functions\when( 'set_transient' )->alias(
			static function ( $key, $value ) use ( &$store ) {
				$store[ $key ] = $value;
				return true;
			}
		);
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}
		$loader = $this->loader();
		$first  = $loader->load( (string) $file );
		$this->assertSame( 'cached', $first->slug() );
		$this->assertCount( 1, $store );
		file_put_contents( (string) $file, '{broken' );
		$second = $loader->load( (string) $file );
		$this->assertFalse( $second->isValid(), 'a changed file produces a new cache key' );
		unlink( (string) $file );
	}
}
