<?php
/**
 * Base class for fast unit tests (no WordPress).
 *
 * @package JOOservices\TestKit
 */

declare(strict_types=1);

namespace JOOservices\TestKit;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Sets up Brain Monkey (WordPress function and hook mocking) and Mockery for each test.
 */
abstract class UnitTestCase extends TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Boots Brain Monkey and stubs the common escaping and translation functions.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->stubWordPressBasics();
	}

	/**
	 * Tears Brain Monkey down.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Returns the given value for the escaping and translation helpers so output can be asserted verbatim.
	 */
	protected function stubWordPressBasics(): void {
		Monkey\Functions\stubs(
			array(
				'esc_html'            => static fn( $text ) => htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ),
				'esc_attr'            => static fn( $text ) => htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ),
				'esc_url'             => static fn( $url ) => (string) $url,
				'esc_url_raw'         => static fn( $url ) => (string) $url,
				'wp_kses_post'        => static fn( $text ) => (string) $text,
				'sanitize_text_field' => static fn( $text ) => trim( strip_tags( (string) $text ) ),
				'wp_json_encode'      => static fn( $data, $flags = 0 ) => json_encode( $data, $flags ),
				'__'                  => static fn( $text ) => (string) $text,
				'_x'                  => static fn( $text ) => (string) $text,
				'esc_html__'          => static fn( $text ) => htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ),
				'esc_attr__'          => static fn( $text ) => htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ),
				'esc_html_e'          => static function ( $text ) {
					echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); // phpcs:ignore WordPress.Security.EscapeOutput
				},
			)
		);
	}
}
