<?php
/**
 * Plugin detection from signatures.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Compat;

use JOOservices\ThemeCore\Compat\Detector;
use JOOservices\ThemeCore\Compat\Signatures;
use PHPUnit\Framework\TestCase;

final class DetectorTest extends TestCase {

	/**
	 * Detector that reports exactly the listed (kind => names) as present.
	 *
	 * @param array<string, array<string, string>> $present Present items: kind => [name => value].
	 */
	private function detector( array $present ): Detector {
		return new Detector( static fn( string $kind, string $name ): ?string => $present[ $kind ][ $name ] ?? null );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function keys(): array {
		$out = array();
		foreach ( array_keys( Signatures::all() ) as $key ) {
			$out[ $key ] = array( $key );
		}
		return $out;
	}

	public function test_there_are_at_least_28_signatures(): void {
		$this->assertGreaterThanOrEqual( 28, count( Signatures::all() ) );
	}

	/**
	 * @dataProvider keys
	 */
	public function test_every_signature_is_detected_by_its_first_signal_and_absent_otherwise( string $key ): void {
		$signature = Signatures::all()[ $key ];
		if ( isset( $signature['constants'][0] ) ) {
			$present = array( 'constant' => array( $signature['constants'][0] => '9.9' ) );
		} elseif ( isset( $signature['classes'][0] ) ) {
			$present = array( 'class' => array( $signature['classes'][0] => '' ) );
		} else {
			$present = array( 'plugin' => array( (string) ( $signature['file'] ?? 'missing/file.php' ) => '' ) );
		}
		$this->assertTrue( $this->detector( $present )->active( $key ), $key );
		$this->assertFalse( $this->detector( array() )->active( $key ), $key . ' absent' );
	}

	public function test_version_comes_from_the_constant_and_is_null_for_class_only_detection(): void {
		$d = $this->detector(
			array(
				'constant' => array( 'WPSEO_VERSION' => '24.1' ),
				'class'    => array( 'LiteSpeed\\Core' => '' ),
			)
		);
		$this->assertSame( '24.1', $d->version( 'yoast' ) );
		$this->assertTrue( $d->active( 'litespeed-cache' ) );
		$this->assertNull( $d->version( 'litespeed-cache' ) );
		$this->assertNull( $d->version( 'rankmath' ) );
	}

	public function test_detection_falls_back_from_constants_to_classes_functions_and_plugin_files(): void {
		$this->assertTrue( $this->detector( array( 'class' => array( 'TRP_Translate_Press' => '' ) ) )->active( 'translatepress' ) );
		$this->assertTrue( $this->detector( array( 'plugin' => array( 'polylang/polylang.php' => '' ) ) )->active( 'polylang' ) );
		$this->assertTrue( $this->detector( array( 'plugin' => array( 'wordpress-seo/wp-seo.php' => '' ) ) )->active( 'yoast' ) );
	}

	public function test_a_constant_without_its_class_is_not_a_fatal(): void {
		$d = $this->detector( array( 'constant' => array( 'WC_VERSION' => '9.0' ) ) );
		$this->assertTrue( $d->active( 'woocommerce' ) );
	}

	public function test_provides_returns_the_first_active_plugin_with_the_capability(): void {
		$d = $this->detector(
			array(
				'constant' => array(
					'RANK_MATH_VERSION' => '1',
					'WPSEO_VERSION'     => '2',
					'POLYLANG_VERSION'  => '3',
				),
			)
		);
		$this->assertSame( 'yoast', $d->provides( 'seo' ) );
		$this->assertSame( 'polylang', $d->provides( 'multilingual' ) );
		$this->assertNull( $d->provides( 'page-builder' ) );
	}

	public function test_results_are_cached_per_instance(): void {
		$calls = 0;
		$d     = new Detector(
			static function () use ( &$calls ): ?string {
				++$calls;
				return null;
			}
		);
		$d->active( 'yoast' );
		$first = $calls;
		$d->active( 'yoast' );
		$d->version( 'yoast' );
		$this->assertSame( $first, $calls );
	}

	public function test_unknown_keys_are_inactive(): void {
		$this->assertFalse( $this->detector( array() )->active( 'nope' ) );
	}

	public function test_the_live_probe_never_autoloads_classes(): void {
		$this->assertNull( Detector::liveProbe( 'class', 'Definitely\\Not\\Loaded' ) );
		$this->assertSame( '', Detector::liveProbe( 'class', self::class ) );
		$this->assertNull( Detector::liveProbe( 'unknown', 'x' ) );
		$this->assertSame( '', Detector::liveProbe( 'function', 'strlen' ) );
	}
}
