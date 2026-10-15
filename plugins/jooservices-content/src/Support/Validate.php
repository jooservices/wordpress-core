<?php
/**
 * Small validators shared by the components.
 *
 * @package JOOservices\Content
 */

declare(strict_types=1);

namespace JOOservices\Content\Support;

/**
 * Pure helpers.
 */
final class Validate {

	/**
	 * Whether a value is a valid month (YYYY-MM).
	 *
	 * @param mixed $value Value.
	 */
	public static function month( $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $value );
	}

	/**
	 * Keeps a valid month or returns an empty string.
	 *
	 * @param mixed $value Value.
	 */
	public static function cleanMonth( $value ): string {
		return self::month( $value ) ? (string) $value : '';
	}

	/**
	 * Keeps an https URL or returns an empty string.
	 *
	 * @param mixed $value Value.
	 */
	public static function httpsUrl( $value ): string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}
		$url = esc_url_raw( trim( $value ), array( 'https' ) );
		return 0 === strpos( $url, 'https://' ) ? $url : '';
	}

	/**
	 * Keeps an attachment id that is an image, else 0.
	 *
	 * @param mixed $value Value.
	 */
	public static function imageId( $value ): int {
		$id = absint( $value );
		return $id > 0 && wp_attachment_is_image( $id ) ? $id : 0;
	}

	/**
	 * Compares two months: true when $from <= $to (an empty $to means "present").
	 *
	 * @param string $from From month.
	 * @param string $to   To month or empty.
	 */
	public static function periodIsValid( string $from, string $to ): bool {
		return '' === $to || '' === $from || strcmp( $from, $to ) <= 0;
	}

	/**
	 * Human period ("2022-03 – 2025-06" or "2022-03 – Present").
	 *
	 * @param string $from From month.
	 * @param string $to   To month or empty.
	 */
	public static function period( string $from, string $to ): string {
		return $from . ' – ' . ( '' === $to ? __( 'Present', 'jooservices-content' ) : $to );
	}
}
