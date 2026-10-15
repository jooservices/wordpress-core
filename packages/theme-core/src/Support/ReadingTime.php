<?php
/**
 * Word count and reading time.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Support;

/**
 * One definition of "words" shared by the reading-time label and structured data.
 *
 * @api
 */
final class ReadingTime {

	/**
	 * Words per minute used for the estimate.
	 */
	public const WPM = 200;

	/**
	 * Counts words in plain text (Unicode letters and digits; CJK characters count one each).
	 *
	 * @param string $plain Plain text.
	 */
	public static function words( string $plain ): int {
		$cjk   = (int) preg_match_all( '/[\x{3040}-\x{30FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $plain );
		$other = (string) preg_replace( '/[\x{3040}-\x{30FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', ' ', $plain );
		return $cjk + (int) preg_match_all( '/[\p{L}\p{N}]+(?:[\'\x{2019}-][\p{L}\p{N}]+)*/u', $other );
	}

	/**
	 * Minutes to read (at least 1 for non-empty text, 0 for empty text).
	 *
	 * @param int $words Word count.
	 */
	public static function minutes( int $words ): int {
		return $words <= 0 ? 0 : max( 1, (int) ceil( $words / self::WPM ) );
	}
}
