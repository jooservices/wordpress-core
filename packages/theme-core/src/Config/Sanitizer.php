<?php
/**
 * Setting value sanitisers.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Config;

/**
 * Turns raw input into a valid setting value or an error message, driven by the descriptor's sanitiser name.
 *
 * @internal
 */
final class Sanitizer {

	/**
	 * Sanitises a value.
	 *
	 * @param array<string, mixed> $definition Setting definition from the descriptor.
	 * @param mixed                $value      Raw value.
	 * @return array{ok: bool, value: mixed, error: string}
	 */
	public function sanitize( array $definition, $value ): array {
		$name = (string) ( $definition['sanitize'] ?? 'text' );
		switch ( $name ) {
			case 'bool':
				return $this->ok( $this->toBool( $value ) );
			case 'int_range':
				return $this->intRange( $definition, $value );
			case 'enum':
				$allowed = (array) ( $definition['enum'] ?? array() );
				$text    = is_scalar( $value ) ? (string) $value : '';
				return in_array( $text, $allowed, true ) ? $this->ok( $text ) : $this->fail( 'Choose one of: ' . implode( ', ', $allowed ) );
			case 'text':
				return $this->text( $definition, $value );
			case 'attachment_id':
				return $this->attachment( $value );
			case 'user_id':
				return $this->user( $value );
			case 'term_ids':
				return $this->ok( array_values( array_unique( array_filter( array_map( 'absint', $this->toList( $value ) ) ) ) ) );
			case 'string_list':
				return $this->stringList( $definition, $value );
		}
		return $this->fail( 'Unknown sanitiser "' . $name . '".' );
	}

	/**
	 * Success result.
	 *
	 * @param mixed $value Value.
	 * @return array{ok: bool, value: mixed, error: string}
	 */
	private function ok( $value ): array {
		return array(
			'ok'    => true,
			'value' => $value,
			'error' => '',
		);
	}

	/**
	 * Failure result.
	 *
	 * @param string $error Message.
	 * @return array{ok: bool, value: mixed, error: string}
	 */
	private function fail( string $error ): array {
		return array(
			'ok'    => false,
			'value' => null,
			'error' => $error,
		);
	}

	/**
	 * Boolean coercion for form input.
	 *
	 * @param mixed $value Raw.
	 */
	private function toBool( $value ): bool {
		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( '1', 'true', 'on', 'yes' ), true );
		}
		return (bool) $value;
	}

	/**
	 * Integer within a range.
	 *
	 * @param array<string, mixed> $definition Definition.
	 * @param mixed                $value      Raw.
	 * @return array{ok: bool, value: mixed, error: string}
	 */
	private function intRange( array $definition, $value ): array {
		if ( is_string( $value ) ) {
			$value = trim( $value );
		}
		if ( ! is_int( $value ) && ! ( is_string( $value ) && 1 === preg_match( '/^-?\d+$/', $value ) ) ) {
			return $this->fail( 'Enter a whole number.' );
		}
		$number = (int) $value;
		$min    = $definition['minimum'] ?? null;
		$max    = $definition['maximum'] ?? null;
		if ( null !== $min && $number < $min ) {
			return $this->fail( sprintf( 'Must be at least %d.', $min ) );
		}
		if ( null !== $max && $number > $max ) {
			return $this->fail( sprintf( 'Must be at most %d.', $max ) );
		}
		return $this->ok( $number );
	}

	/**
	 * Plain text with a length limit.
	 *
	 * @param array<string, mixed> $definition Definition.
	 * @param mixed                $value      Raw.
	 * @return array{ok: bool, value: mixed, error: string}
	 */
	private function text( array $definition, $value ): array {
		if ( ! is_scalar( $value ) ) {
			return $this->fail( 'Enter text.' );
		}
		$text = sanitize_text_field( (string) $value );
		$max  = $definition['maxLength'] ?? null;
		if ( null !== $max && mb_strlen( $text ) > $max ) {
			return $this->fail( sprintf( 'Use at most %d characters.', $max ) );
		}
		return $this->ok( $text );
	}

	/**
	 * Attachment id of an image (0 clears).
	 *
	 * @param mixed $value Raw.
	 * @return array{ok: bool, value: mixed, error: string}
	 */
	private function attachment( $value ): array {
		$id = absint( $value );
		if ( 0 === $id ) {
			return $this->ok( 0 );
		}
		if ( 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
			return $this->fail( 'Choose an image from the media library.' );
		}
		return $this->ok( $id );
	}

	/**
	 * User id that can edit posts (0 = automatic).
	 *
	 * @param mixed $value Raw.
	 * @return array{ok: bool, value: mixed, error: string}
	 */
	private function user( $value ): array {
		$id = absint( $value );
		if ( 0 === $id ) {
			return $this->ok( 0 );
		}
		$user = get_userdata( $id );
		if ( false === $user || ! user_can( $user, 'edit_posts' ) ) {
			return $this->fail( 'Choose a user who can edit posts.' );
		}
		return $this->ok( $id );
	}

	/**
	 * List of unique, trimmed, non-empty strings (https origins for preconnect style lists).
	 *
	 * @param array<string, mixed> $definition Definition.
	 * @param mixed                $value      Raw.
	 * @return array{ok: bool, value: mixed, error: string}
	 */
	private function stringList( array $definition, $value ): array {
		$list = array();
		foreach ( $this->toList( $value ) as $item ) {
			if ( ! is_scalar( $item ) ) {
				return $this->fail( 'Enter text values.' );
			}
			$text = sanitize_text_field( (string) $item );
			if ( '' !== $text ) {
				$list[] = $text;
			}
		}
		$list = array_values( array_unique( $list ) );
		if ( 'perf.preconnect' === ( $definition['key'] ?? '' ) ) {
			foreach ( $list as $origin ) {
				if ( 0 !== strpos( $origin, 'https://' ) ) {
					return $this->fail( 'Origins must start with https://.' );
				}
			}
		}
		return $this->ok( $list );
	}

	/**
	 * Accepts arrays or comma/newline separated strings.
	 *
	 * @param mixed $value Raw.
	 * @return list<mixed>
	 */
	private function toList( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY ) ?: array();
		}
		return is_array( $value ) ? array_values( $value ) : array();
	}
}
