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
}
