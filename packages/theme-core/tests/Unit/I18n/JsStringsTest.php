<?php
/**
 * JavaScript string map.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\I18n;

use Brain\Monkey\Functions;
use JOOservices\TestKit\UnitTestCase;
use JOOservices\ThemeCore\I18n\JsStrings;

final class JsStringsTest extends UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
	}

	/**
	 * Keys used by t('key', ...) calls in the shipped JavaScript.
	 *
	 * @return list<string>
	 */
	private function keysUsedByJavaScript(): array {
		$root = dirname( __DIR__, 3 ) . '/resources';
		$keys = array();
		$iter = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iter as $file ) {
			$path = $file->getPathname();
			if ( ! str_ends_with( $path, '.js' ) || str_contains( $path, '/build/' ) || str_contains( $path, '.test.js' ) ) {
				continue;
			}
			preg_match_all( "/\\bt\\('([a-z_.]+)'/", (string) file_get_contents( $path ), $m );
			$keys = array_merge( $keys, $m[1] );
		}
		return array_values( array_unique( $keys ) );
	}

	public function test_every_key_used_by_the_javascript_has_a_translated_entry(): void {
		$map  = JsStrings::map();
		$used = $this->keysUsedByJavaScript();
		$this->assertNotEmpty( $used );
		foreach ( $used as $key ) {
			$this->assertArrayHasKey( $key, $map, 'missing JS string: ' . $key );
		}
	}

	public function test_the_filter_merges_into_the_runtime_configuration(): void {
		$strings = new JsStrings();
		$out     = $strings->filter(
			array(
				'i18n'  => array( 'existing' => 'x' ),
				'other' => 1,
			)
		);
		$this->assertSame( 'x', $out['i18n']['existing'] );
		$this->assertSame( 'Retry', $out['i18n']['retry'] );
		$this->assertSame( 1, $out['other'] );
		$this->assertSame( 'text', $strings->filter( 'text' ) );
	}
}
