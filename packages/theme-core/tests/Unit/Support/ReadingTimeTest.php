<?php
/**
 * Word count and reading time.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Support;

use JOOservices\ThemeCore\Support\ReadingTime;
use PHPUnit\Framework\TestCase;

final class ReadingTimeTest extends TestCase {

	public function test_words(): void {
		$this->assertSame( 0, ReadingTime::words( '' ) );
		$this->assertSame( 4, ReadingTime::words( 'One two three four.' ) );
		$this->assertSame( 3, ReadingTime::words( "Don't re-use it" ) );
		$this->assertSame( 4, ReadingTime::words( 'Tối ưu hiệu năng' ) );
		$this->assertSame( 4, ReadingTime::words( '日本語です' ) - 1 );
		$this->assertSame( 4, ReadingTime::words( 'v1.2 build 42' ) );
	}

	public function test_minutes(): void {
		$this->assertSame( 0, ReadingTime::minutes( 0 ) );
		$this->assertSame( 1, ReadingTime::minutes( 5 ) );
		$this->assertSame( 1, ReadingTime::minutes( 200 ) );
		$this->assertSame( 2, ReadingTime::minutes( 201 ) );
		$this->assertSame( 5, ReadingTime::minutes( 1000 ) );
	}

	public function test_words_of_content_ignore_markup_and_weight_code_at_a_quarter(): void {
		$plain = '<!-- wp:paragraph --><p>' . str_repeat( 'word ', 100 ) . '</p><!-- /wp:paragraph -->';
		$this->assertSame( 100, ReadingTime::wordsOfContent( $plain ) );
		$code = '<pre class="wp-block-code"><code>' . str_repeat( 'x ', 80 ) . '</code></pre>';
		$this->assertSame( 20, ReadingTime::wordsOfContent( $code ), '80 code words count as 20' );
		$this->assertSame( 120, ReadingTime::wordsOfContent( $plain . $code ) );
		$this->assertSame( 3, ReadingTime::wordsOfContent( 'One [gallery ids="1,2"] <b>two</b> &amp; three' ) );
		$this->assertSame( 0, ReadingTime::wordsOfContent( '' ) );
	}

	public function test_speed_depends_on_the_language(): void {
		$this->assertSame( 220, ReadingTime::wpm( 'en' ) );
		$this->assertSame( 220, ReadingTime::wpm( 'en-US' ) );
		$this->assertSame( 200, ReadingTime::wpm( 'vi' ) );
		$this->assertSame( 200, ReadingTime::wpm( 'fr' ), 'unknown languages use the default' );
		$this->assertSame( 2, ReadingTime::minutesAt( 221, 220 ) );
		$this->assertSame( 1, ReadingTime::minutesAt( 1, 220 ) );
		$this->assertSame( 0, ReadingTime::minutesAt( 0, 220 ) );
	}
}
