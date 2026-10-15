<?php
/**
 * Text helpers for titles and descriptions.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo;

/**
 * Pure, multibyte-safe string helpers (no WordPress dependency).
 *
 * @internal
 */
final class Text {

	/**
	 * Strips tags, shortcodes and block comments, decodes entities and collapses whitespace.
	 *
	 * @param string $html Raw text or HTML.
	 */
	public static function plain( string $html ): string {
		$html = (string) preg_replace( '/<!--.*?-->/s', ' ', $html );
		$html = (string) preg_replace( '#<(h[1-6]|pre|code|table|script|style|figure)\b[^>]*>.*?</\1>#is', ' ', $html );
		$html = (string) preg_replace( '/\[\/?[a-zA-Z][^\]]*\]/', ' ', $html );
		$html = (string) preg_replace( '/<[^>]*>/s', ' ', $html );
		$html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$html = (string) preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $html );
		return trim( (string) preg_replace( '/\s+/u', ' ', $html ) );
	}

	/**
	 * Truncates on a word boundary and adds an ellipsis only when something was cut.
	 *
	 * @param string $text Text.
	 * @param int    $max  Maximum length in characters (ellipsis included).
	 */
	public static function truncate( string $text, int $max ): string {
		$text = trim( $text );
		if ( $max < 2 || mb_strlen( $text, 'UTF-8' ) <= $max ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, $max - 1, 'UTF-8' );
		$space = mb_strrpos( $cut, ' ', 0, 'UTF-8' );
		if ( false !== $space && $space >= (int) ( $max * 0.5 ) ) {
			$cut = mb_substr( $cut, 0, $space, 'UTF-8' );
		}
		return (string) preg_replace( '/[\s,;:|\x{2013}\x{2014}-]+$/u', '', $cut ) . "\u{2026}";
	}

	/**
	 * Summary of about $target characters, ending at a sentence boundary when one is close enough.
	 *
	 * @param string $text   Plain text.
	 * @param int    $target Target length.
	 */
	public static function summary( string $text, int $target = 155 ): string {
		$text = trim( $text );
		if ( mb_strlen( $text, 'UTF-8' ) <= $target ) {
			return $text;
		}
		$window = mb_substr( $text, 0, $target, 'UTF-8' );
		if ( 1 === preg_match( '/^(.{80,})[.!?](?=\s|$)/us', $window, $match ) ) {
			return $match[1] . mb_substr( $window, mb_strlen( $match[1], 'UTF-8' ), 1, 'UTF-8' );
		}
		return self::truncate( $text, $target );
	}
}
