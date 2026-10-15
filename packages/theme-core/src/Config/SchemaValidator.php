<?php
/**
 * Minimal JSON Schema validator for the keyword subset used by the manifest schema.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Config;

/**
 * Supports: type, enum, required, properties, additionalProperties, patternProperties (^x- only), items,
 * minItems, minimum, maximum, minLength, maxLength, pattern. Annotation keywords are ignored.
 *
 * @internal
 */
final class SchemaValidator {

	public const SUPPORTED = array( 'type', 'enum', 'required', 'properties', 'additionalProperties', 'patternProperties', 'items', 'minItems', 'minimum', 'maximum', 'minLength', 'maxLength', 'pattern' );

	public const ANNOTATIONS = array( '$schema', '$id', 'title', 'description', 'default', 'examples' );

	/**
	 * Validates data and returns error messages with JSON pointers (empty when valid).
	 *
	 * @param mixed                $data    Decoded JSON (objects as associative arrays).
	 * @param array<string, mixed> $schema  Schema.
	 * @param string               $pointer JSON pointer of $data (internal).
	 * @return list<string>
	 */
	public function validate( $data, array $schema, string $pointer = '' ): array {
		$errors = array();
		$at     = '' === $pointer ? '/' : $pointer;

		if ( isset( $schema['type'] ) && ! $this->typeMatches( $data, $schema['type'] ) ) {
			$expected = is_array( $schema['type'] ) ? implode( '|', $schema['type'] ) : (string) $schema['type'];
			return array( sprintf( '%s: expected %s, got %s', $at, $expected, $this->typeName( $data ) ) );
		}
		if ( isset( $schema['enum'] ) && ! in_array( $data, $schema['enum'], true ) ) {
			$errors[] = sprintf( '%s: value must be one of %s', $at, implode( ', ', array_map( 'json_encode', $schema['enum'] ) ) );
		}
		if ( is_string( $data ) ) {
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $data ) : strlen( $data );
			if ( isset( $schema['minLength'] ) && $length < $schema['minLength'] ) {
				$errors[] = sprintf( '%s: must be at least %d characters', $at, $schema['minLength'] );
			}
			if ( isset( $schema['maxLength'] ) && $length > $schema['maxLength'] ) {
				$errors[] = sprintf( '%s: must be at most %d characters', $at, $schema['maxLength'] );
			}
			if ( isset( $schema['pattern'] ) && 1 !== preg_match( '/' . str_replace( '/', '\/', (string) $schema['pattern'] ) . '/u', $data ) ) {
				$errors[] = sprintf( '%s: does not match pattern %s', $at, $schema['pattern'] );
			}
		}
		if ( is_int( $data ) || is_float( $data ) ) {
			if ( isset( $schema['minimum'] ) && $data < $schema['minimum'] ) {
				$errors[] = sprintf( '%s: must be >= %s', $at, $schema['minimum'] );
			}
			if ( isset( $schema['maximum'] ) && $data > $schema['maximum'] ) {
				$errors[] = sprintf( '%s: must be <= %s', $at, $schema['maximum'] );
			}
		}
		if ( is_array( $data ) && $this->isList( $data ) ) {
			if ( isset( $schema['minItems'] ) && count( $data ) < $schema['minItems'] ) {
				$errors[] = sprintf( '%s: needs at least %d item(s)', $at, $schema['minItems'] );
			}
			if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
				foreach ( $data as $index => $item ) {
					$errors = array_merge( $errors, $this->validate( $item, $schema['items'], $pointer . '/' . $index ) );
				}
			}
		}
		if ( is_array( $data ) && ( ! $this->isList( $data ) || ( array() === $data && 'object' === ( $schema['type'] ?? null ) ) ) ) {
			$errors = array_merge( $errors, $this->validateObject( $data, $schema, $pointer, $at ) );
		}
		return $errors;
	}

	/**
	 * Keywords used by a schema that this validator does not support.
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @return list<string>
	 */
	public function unsupportedKeywords( array $schema ): array {
		$unsupported = array();
		foreach ( $schema as $keyword => $value ) {
			if ( ! in_array( $keyword, self::SUPPORTED, true ) && ! in_array( $keyword, self::ANNOTATIONS, true ) ) {
				$unsupported[] = (string) $keyword;
			}
			if ( 'properties' === $keyword && is_array( $value ) ) {
				foreach ( $value as $sub ) {
					if ( is_array( $sub ) ) {
						$unsupported = array_merge( $unsupported, $this->unsupportedKeywords( $sub ) );
					}
				}
			}
			if ( 'patternProperties' === $keyword && is_array( $value ) ) {
				foreach ( $value as $pattern => $sub ) {
					if ( '^x-' !== $pattern ) {
						$unsupported[] = 'patternProperties:' . $pattern;
					}
					if ( is_array( $sub ) ) {
						$unsupported = array_merge( $unsupported, $this->unsupportedKeywords( $sub ) );
					}
				}
			}
			if ( in_array( $keyword, array( 'items', 'additionalProperties' ), true ) && is_array( $value ) ) {
				$unsupported = array_merge( $unsupported, $this->unsupportedKeywords( $value ) );
			}
		}
		return array_values( array_unique( $unsupported ) );
	}

	/**
	 * Validates an object-like array.
	 *
	 * @param array<mixed>         $data    Data.
	 * @param array<string, mixed> $schema  Schema.
	 * @param string               $pointer Pointer.
	 * @param string               $at      Display pointer.
	 * @return list<string>
	 */
	private function validateObject( array $data, array $schema, string $pointer, string $at ): array {
		$errors     = array();
		$properties = is_array( $schema['properties'] ?? null ) ? $schema['properties'] : array();
		foreach ( (array) ( $schema['required'] ?? array() ) as $required ) {
			if ( ! array_key_exists( $required, $data ) ) {
				$errors[] = sprintf( '%s: missing required property "%s"', $at, $required );
			}
		}
		foreach ( $data as $key => $value ) {
			$key = (string) $key;
			if ( isset( $properties[ $key ] ) && is_array( $properties[ $key ] ) ) {
				$errors = array_merge( $errors, $this->validate( $value, $properties[ $key ], $pointer . '/' . $key ) );
				continue;
			}
			if ( isset( $schema['patternProperties']['^x-'] ) && 0 === strpos( $key, 'x-' ) ) {
				continue;
			}
			$additional = $schema['additionalProperties'] ?? true;
			if ( false === $additional ) {
				$errors[] = sprintf( '%s: unknown property "%s"', $at, $key );
			} elseif ( is_array( $additional ) ) {
				$errors = array_merge( $errors, $this->validate( $value, $additional, $pointer . '/' . $key ) );
			}
		}
		return $errors;
	}

	/**
	 * Whether a value matches a JSON Schema type (or list of types).
	 *
	 * @param mixed                $data Data.
	 * @param string|list<string> $type Type or types.
	 */
	private function typeMatches( $data, $type ): bool {
		foreach ( (array) $type as $candidate ) {
			switch ( $candidate ) {
				case 'string':
					$ok = is_string( $data );
					break;
				case 'integer':
					$ok = is_int( $data );
					break;
				case 'number':
					$ok = is_int( $data ) || is_float( $data );
					break;
				case 'boolean':
					$ok = is_bool( $data );
					break;
				case 'array':
					$ok = is_array( $data ) && $this->isList( $data );
					break;
				case 'object':
					$ok = is_array( $data ) && ( array() === $data || ! $this->isList( $data ) );
					break;
				case 'null':
					$ok = null === $data;
					break;
				default:
					$ok = false;
			}
			if ( $ok ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * JSON type name of a value.
	 *
	 * @param mixed $data Data.
	 */
	private function typeName( $data ): string {
		if ( is_array( $data ) ) {
			return $this->isList( $data ) ? 'array' : 'object';
		}
		return null === $data ? 'null' : gettype( $data );
	}

	/**
	 * Whether an array is a list.
	 *
	 * @param array<mixed> $data Data.
	 */
	private function isList( array $data ): bool {
		return array() === $data || array_keys( $data ) === range( 0, count( $data ) - 1 );
	}
}
