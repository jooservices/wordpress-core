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
	 * Default words per minute.
	 */
	public const WPM = 200;

	/**
	 * Words per minute by language.
	 */
	public const WPM_BY_LANGUAGE = array(
		'en' => 220,
		'vi' => 200,
	);

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

	/**
	 * Reading speed for a language code.
	 *
	 * @param string $language Language (en, vi, pt-BR).
	 */
	public static function wpm( string $language ): int {
		$base = strtolower( explode( '-', $language )[0] );
		return self::WPM_BY_LANGUAGE[ $base ] ?? self::WPM;
	}

	/**
	 * Minutes for a word count at a given speed.
	 *
	 * @param int $words Word count.
	 * @param int $wpm   Words per minute.
	 */
	public static function minutesAt( int $words, int $wpm ): int {
		return $words <= 0 ? 0 : max( 1, (int) ceil( $words / max( 1, $wpm ) ) );
	}

	/**
	 * Words of raw post content: code blocks count at 25%, block comments, shortcodes and tags are ignored.
	 *
	 * @param string $content Raw post content.
	 */
	public static function wordsOfContent( string $content ): int {
		$code = 0;
		$text = (string) preg_replace_callback(
			'#<pre\b[^>]*>.*?</pre>#is',
			static function ( array $match ) use ( &$code ): string {
				$code += self::words( html_entity_decode( (string) preg_replace( '/<[^>]*>/', ' ', $match[0] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				return ' ';
			},
			$content
		);
		$text = (string) preg_replace( '/<!--.*?-->/s', ' ', $text );
		$text = (string) preg_replace( '/\[\/?[a-zA-Z][^\]]*\]/', ' ', $text );
		$text = html_entity_decode( (string) preg_replace( '/<[^>]*>/', ' ', $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return self::words( $text ) + (int) round( $code * 0.25 );
	}

	/**
	 * Words and minutes of a post, cached in `_joo_reading_cache` keyed by the content hash.
	 *
	 * @param int $postId Post id.
	 * @return array{words: int, minutes: int}
	 */
	public static function forPost( int $postId ): array {
		$post = get_post( $postId );
		if ( ! $post instanceof \WP_Post ) {
			return array(
				'words'   => 0,
				'minutes' => 0,
			);
		}
		$hash   = md5( $post->post_content );
		$cached = get_post_meta( $postId, '_joo_reading_cache', true );
		if ( is_array( $cached ) && ( $cached['hash'] ?? '' ) === $hash ) {
			return array(
				'words'   => (int) $cached['words'],
				'minutes' => (int) $cached['minutes'],
			);
		}
		$lang    = (string) get_post_meta( $postId, '_joo_lang', true );
		$words   = self::wordsOfContent( $post->post_content );
		$minutes = self::minutesAt( $words, self::wpm( '' !== $lang ? $lang : (string) get_locale() ) );
		update_post_meta(
			$postId,
			'_joo_reading_cache',
			array(
				'hash'    => $hash,
				'words'   => $words,
				'minutes' => $minutes,
			)
		);
		return array(
			'words'   => $words,
			'minutes' => $minutes,
		);
	}
}
