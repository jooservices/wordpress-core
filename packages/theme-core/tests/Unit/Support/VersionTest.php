<?php
/**
 * Version range tests.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Support;

use JOOservices\ThemeCore\Support\Version;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase {

	/**
	 * @return array<string, array{0: string, 1: string, 2: bool}>
	 */
	public static function ranges(): array {
		return array(
			'caret major'           => array( '1.4.2', '^1.2', true ),
			'caret excludes next'   => array( '2.0.0', '^1.2', false ),
			'caret below'           => array( '1.1.9', '^1.2', false ),
			'caret zero minor'      => array( '0.1.5', '^0.1', true ),
			'caret zero excludes'   => array( '0.2.0', '^0.1', false ),
			'caret zero patch'      => array( '0.0.3', '^0.0.3', true ),
			'caret zero patch next' => array( '0.0.4', '^0.0.3', false ),
			'tilde minor'           => array( '1.2.9', '~1.2.3', true ),
			'tilde excludes minor'  => array( '1.3.0', '~1.2.3', false ),
			'tilde major'           => array( '1.9.0', '~1', true ),
			'gte'                   => array( '3.0.0', '>=2.0', true ),
			'gt equal'              => array( '2.0.0', '>2.0.0', false ),
			'gt partial'            => array( '2.1.0', '>2.0', true ),
			'lt'                    => array( '1.9.9', '<2.0.0', true ),
			'lte inclusive'         => array( '2.0.0', '<=2.0.0', true ),
			'exact'                 => array( '1.2.3', '1.2.3', true ),
			'exact other'           => array( '1.2.4', '1.2.3', false ),
			'wildcard'              => array( '1.2.9', '1.2.x', true ),
			'wildcard star'         => array( '1.9.9', '1.*', true ),
			'and range'             => array( '1.5.0', '>=1.2 <2.0', true ),
			'and range outside'     => array( '2.1.0', '>=1.2 <2.0', false ),
			'or range first'        => array( '1.5.0', '^1.0 || ^3.0', true ),
			'or range second'       => array( '3.1.0', '^1.0 || ^3.0', true ),
			'or range none'         => array( '2.5.0', '^1.0 || ^3.0', false ),
			'star accepts anything' => array( '9.9.9', '*', true ),
			'invalid version'       => array( 'banana', '^1.0', false ),
			'prerelease is trimmed' => array( '1.2.0-beta.1', '^1.2', true ),
			'v prefix'              => array( 'v1.2.0', '^1.0', true ),
			'empty range'           => array( '1.0.0', '', false ),
		);
	}

	/**
	 * @dataProvider ranges
	 */
	public function test_satisfies( string $version, string $range, bool $expected ): void {
		$this->assertSame( $expected, Version::satisfies( $version, $range ) );
	}

	public function test_core_returns_the_constant_or_zero(): void {
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', Version::core() );
	}
}
