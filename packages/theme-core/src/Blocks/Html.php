<?php
/**
 * Escaping helpers for block templates.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks;

use JOOservices\ThemeCore\Content\Model\ImageRef;

/**
 * Small, safe helpers: every method returns escaped HTML.
 *
 * @api
 */
final class Html {

	/**
	 * Escapes text.
	 *
	 * @param string $text Text.
	 */
	public static function e( string $text ): string {
		return esc_html( $text );
	}

	/**
	 * Builds an attribute string (null and false are skipped, true gives a bare attribute).
	 *
	 * @param array<string, string|int|bool|null> $attrs Attributes.
	 */
	public static function attrs( array $attrs ): string {
		$out = '';
		foreach ( $attrs as $name => $value ) {
			if ( null === $value || false === $value || 1 !== preg_match( '/^[a-zA-Z_:][-a-zA-Z0-9_:.]*$/', $name ) ) {
				continue;
			}
			if ( 0 === stripos( $name, 'on' ) ) {
				continue; // Inline event handlers are never rendered.
			}
			$out .= true === $value ? ' ' . $name : ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
		}
		return $out;
	}

	/**
	 * Link markup.
	 *
	 * @param string               $url   URL.
	 * @param string               $label Label (plain text).
	 * @param array<string, mixed> $attrs Extra attributes.
	 */
	public static function link( string $url, string $label, array $attrs = array() ): string {
		$safe = esc_url( $url );
		if ( '' === $safe ) {
			return esc_html( $label );
		}
		return '<a href="' . $safe . '"' . self::attrs( $attrs ) . '>' . esc_html( $label ) . '</a>';
	}

	/**
	 * Image markup with explicit size (no layout shift) and lazy loading unless `eager` is set.
	 *
	 * @param ImageRef|null        $image Image.
	 * @param array<string, mixed> $opts  Options: class, eager, sizes.
	 */
	public static function img( ?ImageRef $image, array $opts = array() ): string {
		if ( null === $image || '' === $image->url ) {
			return '';
		}
		$attrs = array(
			'src'      => esc_url_raw( $image->url ),
			'alt'      => $image->alt,
			'width'    => $image->width > 0 ? $image->width : null,
			'height'   => $image->height > 0 ? $image->height : null,
			'class'    => isset( $opts['class'] ) ? (string) $opts['class'] : null,
			'sizes'    => isset( $opts['sizes'] ) ? (string) $opts['sizes'] : null,
			'loading'  => ! empty( $opts['eager'] ) ? null : 'lazy',
			'decoding' => 'async',
		);
		return '<img' . self::attrs( $attrs ) . '>';
	}
}
