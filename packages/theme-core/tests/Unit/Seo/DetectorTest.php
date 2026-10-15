<?php
/**
 * SEO plugin detection.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Seo;

use JOOservices\ThemeCore\Compat\Detector;
use PHPUnit\Framework\TestCase;

final class DetectorTest extends TestCase {

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function plugins(): array {
		return array(
			'yoast'     => array( 'WPSEO_VERSION', 'Yoast SEO' ),
			'rank math' => array( 'RANK_MATH_VERSION', 'Rank Math' ),
			'aioseo'    => array( 'AIOSEO_VERSION', 'All in One SEO' ),
			'seopress'  => array( 'SEOPRESS_VERSION', 'SEOPress' ),
			'tsf'       => array( 'THE_SEO_FRAMEWORK_VERSION', 'The SEO Framework' ),
			'slim'      => array( 'SLIM_SEO_VERSION', 'Slim SEO' ),
		);
	}

	/**
	 * @dataProvider plugins
	 */
	public function test_each_plugin_is_detected_by_its_constant( string $constant, string $name ): void {
		$detector = new Detector( static fn( string $k, string $n ): ?string => 'constant' === $k && $n === $constant ? '24.1' : null );
		$this->assertSame(
			array(
				'name'    => $name,
				'version' => '24.1',
			),
			$detector->seoPlugin()
		);
		$this->assertCount( 1, $detector->seoPlugins() );
	}

	public function test_nothing_active_and_two_active(): void {
		$this->assertNull( ( new Detector( static fn(): ?string => null ) )->seoPlugin() );
		$two = new Detector( static fn( string $k, string $n ): ?string => 'constant' === $k && in_array( $n, array( 'WPSEO_VERSION', 'RANK_MATH_VERSION' ), true ) ? '1' : null );
		$this->assertCount( 2, $two->seoPlugins() );
	}
}
