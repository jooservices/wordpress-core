<?php
/**
 * Sanitiser tests.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Config;

use Brain\Monkey\Functions;
use JOOservices\TestKit\UnitTestCase;
use JOOservices\ThemeCore\Config\Sanitizer;

final class SanitizerTest extends UnitTestCase {

	private Sanitizer $s;

	protected function setUp(): void {
		parent::setUp();
		$this->s = new Sanitizer();
		Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $v ) => trim( strip_tags( (string) $v ) ) );
	}

	public function test_bool_accepts_form_style_values(): void {
		foreach ( array( '1', 'on', 'true', 'yes', true, 1 ) as $truthy ) {
			$this->assertTrue( $this->s->sanitize( array( 'sanitize' => 'bool' ), $truthy )['value'] );
		}
		foreach ( array( '0', '', 'off', false, 0 ) as $falsy ) {
			$this->assertFalse( $this->s->sanitize( array( 'sanitize' => 'bool' ), $falsy )['value'] );
		}
	}

	public function test_int_range(): void {
		$def = array(
			'sanitize' => 'int_range',
			'minimum'  => 0,
			'maximum'  => 6,
		);
		$this->assertSame( 3, $this->s->sanitize( $def, '3' )['value'] );
		$this->assertFalse( $this->s->sanitize( $def, '7' )['ok'] );
		$this->assertFalse( $this->s->sanitize( $def, '-1' )['ok'] );
		$this->assertFalse( $this->s->sanitize( $def, 'abc' )['ok'] );
		$this->assertFalse( $this->s->sanitize( $def, '1.5' )['ok'] );
	}

	public function test_enum(): void {
		$def = array(
			'sanitize' => 'enum',
			'enum'     => array( 'a', 'b' ),
		);
		$this->assertSame( 'a', $this->s->sanitize( $def, 'a' )['value'] );
		$this->assertFalse( $this->s->sanitize( $def, 'c' )['ok'] );
		$this->assertFalse( $this->s->sanitize( $def, array( 'a' ) )['ok'] );
	}

	public function test_text_strips_tags_and_limits_length(): void {
		$def = array(
			'sanitize'  => 'text',
			'maxLength' => 5,
		);
		$this->assertSame( 'bold', $this->s->sanitize( $def, '<b>bold</b>' )['value'] );
		$this->assertFalse( $this->s->sanitize( $def, 'too long text' )['ok'] );
		$this->assertFalse( $this->s->sanitize( $def, array( 'x' ) )['ok'] );
	}

	public function test_attachment_requires_an_image_or_zero(): void {
		$def = array( 'sanitize' => 'attachment_id' );
		$this->assertSame( 0, $this->s->sanitize( $def, '0' )['value'] );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		$this->assertFalse( $this->s->sanitize( $def, '5' )['ok'] );
		Functions\when( 'get_post_type' )->justReturn( 'attachment' );
		Functions\when( 'wp_attachment_is_image' )->justReturn( true );
		$this->assertSame( 5, $this->s->sanitize( $def, '5' )['value'] );
	}

	public function test_user_must_be_able_to_edit_posts(): void {
		$def = array( 'sanitize' => 'user_id' );
		$this->assertSame( 0, $this->s->sanitize( $def, '0' )['value'] );
		Functions\when( 'get_userdata' )->justReturn( false );
		$this->assertFalse( $this->s->sanitize( $def, '9' )['ok'] );
		Functions\when( 'get_userdata' )->justReturn( new \stdClass() );
		Functions\when( 'user_can' )->justReturn( false );
		$this->assertFalse( $this->s->sanitize( $def, '9' )['ok'] );
		Functions\when( 'user_can' )->justReturn( true );
		$this->assertSame( 9, $this->s->sanitize( $def, '9' )['value'] );
	}

	public function test_term_ids_are_unique_positive_integers(): void {
		$this->assertSame( array( 3, 4 ), $this->s->sanitize( array( 'sanitize' => 'term_ids' ), '3, 4, 3, 0, -0' )['value'] );
		$this->assertSame( array( 7 ), $this->s->sanitize( array( 'sanitize' => 'term_ids' ), array( 7, '0' ) )['value'] );
	}

	public function test_string_list_trims_and_deduplicates(): void {
		$result = $this->s->sanitize( array( 'sanitize' => 'string_list' ), " en, vi ,en\n fr" );
		$this->assertSame( array( 'en', 'vi', 'fr' ), $result['value'] );
		$this->assertFalse( $this->s->sanitize( array( 'sanitize' => 'string_list' ), array( array( 'nested' ) ) )['ok'] );
	}

	public function test_preconnect_origins_must_be_https(): void {
		$def = array(
			'sanitize' => 'string_list',
			'key'      => 'perf.preconnect',
		);
		$this->assertTrue( $this->s->sanitize( $def, 'https://fonts.example' )['ok'] );
		$this->assertFalse( $this->s->sanitize( $def, 'http://insecure.example' )['ok'] );
	}

	public function test_unknown_sanitiser_fails(): void {
		$this->assertFalse( $this->s->sanitize( array( 'sanitize' => 'magic' ), 'x' )['ok'] );
	}
}
