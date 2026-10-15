<?php
/**
 * SEO text helpers.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Seo;

use JOOservices\ThemeCore\Seo\Text;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase {

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function plainCases(): array {
		return array(
			'html and entities' => array( '<p>Fish &amp; <b>chips</b></p>', 'Fish & chips' ),
			'shortcode removed' => array( 'Hello [gallery ids="1,2"] world', 'Hello world' ),
			'block comments'    => array( '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->', 'Hi' ),
			'headings dropped'  => array( '<h2>Heading</h2><p>Body text</p>', 'Body text' ),
			'code dropped'      => array( '<p>Use</p><pre>echo 1;</pre><p>now</p>', 'Use now' ),
			'table dropped'     => array( '<p>A</p><table><tr><td>cell</td></tr></table><p>B</p>', 'A B' ),
			'whitespace'        => array( "  a \n\t b   c  ", 'a b c' ),
			'vietnamese'        => array( '<p>Tối ưu hiệu năng WordPress</p>', 'Tối ưu hiệu năng WordPress' ),
			'emoji'             => array( 'Ship it 🚀 now', 'Ship it 🚀 now' ),
			'zero width'        => array( "a\u{200B}b", 'ab' ),
			'empty'             => array( '', '' ),
			'only markup'       => array( '<div><span></span></div>', '' ),
			'unclosed tag'      => array( 'x <b>bold', 'x bold' ),
			'script dropped'    => array( 'a<script>alert(1)</script>b', 'a b' ),
		);
	}

	/**
	 * @dataProvider plainCases
	 */
	public function test_plain( string $input, string $expected ): void {
		$this->assertSame( $expected, Text::plain( $input ) );
	}

	public function test_truncate_keeps_short_text_and_cuts_on_word_boundaries(): void {
		$this->assertSame( 'short', Text::truncate( 'short', 70 ) );
		$out = Text::truncate( 'The quick brown fox jumps over the lazy dog again and again', 30 );
		$this->assertSame( "The quick brown fox jumps\u{2026}", $out );
		$this->assertLessThanOrEqual( 30, mb_strlen( $out ) );
	}

	public function test_truncate_handles_very_long_words_and_multibyte(): void {
		$out = Text::truncate( str_repeat( 'Đ', 120 ), 50 );
		$this->assertSame( 50, mb_strlen( $out ) );
		$this->assertTrue( mb_check_encoding( $out, 'UTF-8' ) );
		$this->assertStringEndsWith( "\u{2026}", $out );
	}

	public function test_truncate_never_breaks_utf8_or_overflows_for_random_strings(): void {
		mt_srand( 42 );
		$alphabet = array( 'a', 'b', 'ệ', 'ư', 'Đ', '🚀', ' ', ' ', '-', 'x' );
		for ( $i = 0; $i < 200; $i++ ) {
			$text = '';
			for ( $j = 0, $n = mt_rand( 1, 200 ); $j < $n; $j++ ) {
				$text .= $alphabet[ mt_rand( 0, count( $alphabet ) - 1 ) ];
			}
			$max = mt_rand( 10, 80 );
			$out = Text::truncate( $text, $max );
			$this->assertTrue( mb_check_encoding( $out, 'UTF-8' ), 'valid UTF-8 for case ' . $i );
			$this->assertLessThanOrEqual( $max, mb_strlen( $out ), 'length for case ' . $i );
			if ( mb_strlen( trim( $text ) ) <= $max ) {
				$this->assertStringNotContainsString( "\u{2026}", $out );
			}
		}
	}

	public function test_summary_prefers_a_sentence_boundary(): void {
		$text = str_repeat( 'word ', 18 ) . 'ends here. ' . str_repeat( 'more ', 30 );
		$out  = Text::summary( $text, 155 );
		$this->assertStringEndsWith( '.', $out );
		$this->assertLessThanOrEqual( 155, mb_strlen( $out ) );
	}

	public function test_summary_falls_back_to_word_truncation(): void {
		$out = Text::summary( str_repeat( 'alpha beta ', 40 ), 100 );
		$this->assertLessThanOrEqual( 100, mb_strlen( $out ) );
		$this->assertStringEndsWith( "\u{2026}", $out );
	}
}
