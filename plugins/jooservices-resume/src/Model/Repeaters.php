<?php
/**
 * Roles, achievements, technologies and custom values of a company.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Model;

/**
 * Pure sanitisers and validators for the repeater fields. Errors are `array{code, field}` pairs that the WordPress layer
 * turns into translated messages. Repeaters are stored as JSON with a schema version.
 */
final class Repeaters {

	public const VERSION = 1;

	public const EMPLOYMENT = array( 'full_time', 'contract', 'freelance', 'founder' );

	public const FIELD_TYPES = array( 'text', 'number', 'date', 'url', 'list' );

	/**
	 * Encodes items with the schema version.
	 *
	 * @param list<array<string, mixed>> $items Items.
	 */
	public static function encode( array $items ): string {
		if ( array() === $items ) {
			return '';
		}
		return (string) json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- pure model, runs without WordPress.
			array(
				'v'     => self::VERSION,
				'items' => array_values( $items ),
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * Decodes a stored value into its items (empty for missing or broken JSON).
	 *
	 * @param mixed $stored Stored string or array.
	 * @return list<array<string, mixed>>
	 */
	public static function decode( $stored ): array {
		$data = is_string( $stored ) ? json_decode( $stored, true ) : $stored;
		if ( ! is_array( $data ) ) {
			return array();
		}
		$items = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : ( array_is_list( $data ) ? $data : array() );
		return array_values( array_filter( $items, 'is_array' ) );
	}

	/**
	 * Roles: title, period and summary, inside the company period and not overlapping each other.
	 *
	 * @param list<array<string, mixed>> $roles       Raw roles.
	 * @param string                     $companyFrom Company start.
	 * @param string                     $companyTo   Company end.
	 * @return array{items: list<array<string, string>>, errors: list<array{code: string, field: string}>}
	 */
	public static function roles( array $roles, string $companyFrom, string $companyTo ): array {
		$items  = array();
		$errors = array();
		foreach ( array_values( $roles ) as $i => $raw ) {
			$field = 'roles.' . $i;
			$title = self::text( $raw['title'] ?? '', 80 );
			$from  = (string) ( $raw['from'] ?? '' );
			$to    = (string) ( $raw['to'] ?? '' );
			if ( '' === $title ) {
				$errors[] = array(
					'code'  => 'role_title',
					'field' => $field,
				);
				continue;
			}
			if ( ! Period::isMonth( $from ) || ( '' !== $to && ! Period::isMonth( $to ) ) ) {
				$errors[] = array(
					'code'  => 'role_month',
					'field' => $field,
				);
				continue;
			}
			if ( '' !== $to && strcmp( $from, $to ) > 0 ) {
				$errors[] = array(
					'code'  => 'role_order',
					'field' => $field,
				);
				continue;
			}
			if ( '' !== $companyFrom && ! Period::inside( $from, $to, $companyFrom, $companyTo ) ) {
				$errors[] = array(
					'code'  => 'role_outside',
					'field' => $field,
				);
				continue;
			}
			foreach ( $items as $kept ) {
				if ( Period::overlaps( $from, $to, $kept['from'], $kept['to'] ) ) {
					$errors[] = array(
						'code'  => 'role_overlap',
						'field' => $field,
					);
					continue 2;
				}
			}
			$items[] = array(
				'title'   => $title,
				'from'    => $from,
				'to'      => $to,
				'summary' => self::text( $raw['summary'] ?? '', 400 ),
			);
		}
		usort( $items, static fn( array $a, array $b ): int => strcmp( $b['from'], $a['from'] ) );
		return array(
			'items'  => $items,
			'errors' => $errors,
		);
	}

	/**
	 * Achievements: text with an optional metric, unit and https link.
	 *
	 * @param list<array<string, mixed>> $achievements Raw achievements.
	 * @return array{items: list<array<string, string>>, errors: list<array{code: string, field: string}>}
	 */
	public static function achievements( array $achievements ): array {
		$items  = array();
		$errors = array();
		foreach ( array_values( $achievements ) as $i => $raw ) {
			$field  = 'achievements.' . $i;
			$text   = self::text( $raw['text'] ?? '', 240 );
			$metric = self::text( $raw['metric'] ?? '', 30 );
			$link   = trim( (string) ( $raw['url'] ?? '' ) );
			if ( '' === $text ) {
				$errors[] = array(
					'code'  => 'achievement_text',
					'field' => $field,
				);
				continue;
			}
			if ( '' !== $metric && ! is_numeric( str_replace( array( ',', ' ' ), '', $metric ) ) ) {
				$errors[] = array(
					'code'  => 'achievement_metric',
					'field' => $field,
				);
				continue;
			}
			if ( '' !== $link && ! self::isHttps( $link ) ) {
				$errors[] = array(
					'code'  => 'achievement_url',
					'field' => $field,
				);
				continue;
			}
			$items[] = array(
				'text'   => $text,
				'metric' => $metric,
				'unit'   => self::text( $raw['unit'] ?? '', 20 ),
				'url'    => $link,
			);
		}
		return array(
			'items'  => $items,
			'errors' => $errors,
		);
	}

	/**
	 * Technologies: unique non-empty tags, at most 40.
	 *
	 * @param list<mixed> $tags Raw tags.
	 * @return list<string>
	 */
	public static function technologies( array $tags ): array {
		$seen = array();
		foreach ( $tags as $tag ) {
			$clean = self::text( $tag, 40 );
			if ( '' !== $clean ) {
				$seen[ mb_strtolower( $clean ) ] ??= $clean;
			}
		}
		return array_slice( array_values( $seen ), 0, 40 );
	}

	/**
	 * Owner-defined field definitions: label, key, type, visibility and order.
	 *
	 * @param list<array<string, mixed>> $definitions Raw definitions.
	 * @return array{items: list<array<string, mixed>>, errors: list<array{code: string, field: string}>}
	 */
	public static function definitions( array $definitions ): array {
		$items  = array();
		$errors = array();
		$keys   = array();
		foreach ( array_values( $definitions ) as $i => $raw ) {
			$field = 'definitions.' . $i;
			$key   = strtolower( (string) ( $raw['key'] ?? '' ) );
			$label = self::text( $raw['label'] ?? '', 60 );
			$type  = (string) ( $raw['type'] ?? 'text' );
			if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{1,29}$/', $key ) ) {
				$errors[] = array(
					'code'  => 'definition_key',
					'field' => $field,
				);
				continue;
			}
			if ( '' === $label || ! in_array( $type, self::FIELD_TYPES, true ) ) {
				$errors[] = array(
					'code'  => 'definition_label',
					'field' => $field,
				);
				continue;
			}
			if ( isset( $keys[ $key ] ) ) {
				$errors[] = array(
					'code'  => 'definition_duplicate',
					'field' => $field,
				);
				continue;
			}
			$keys[ $key ] = true;
			$items[]      = array(
				'key'    => $key,
				'label'  => $label,
				'type'   => $type,
				'public' => ! empty( $raw['public'] ),
				'order'  => (int) ( $raw['order'] ?? $i ),
			);
		}
		usort( $items, static fn( array $a, array $b ): int => $a['order'] <=> $b['order'] );
		return array(
			'items'  => $items,
			'errors' => $errors,
		);
	}

	/**
	 * Values of the owner-defined fields, validated by their definition. Unknown keys are dropped.
	 *
	 * @param array<string, mixed>       $values      Raw values by key.
	 * @param list<array<string, mixed>> $definitions Definitions.
	 * @return array{values: array<string, mixed>, errors: list<array{code: string, field: string}>}
	 */
	public static function customValues( array $values, array $definitions ): array {
		$clean  = array();
		$errors = array();
		foreach ( $definitions as $definition ) {
			$key = (string) $definition['key'];
			if ( ! array_key_exists( $key, $values ) || '' === $values[ $key ] || array() === $values[ $key ] ) {
				continue;
			}
			$value = $values[ $key ];
			$field = 'custom.' . $key;
			switch ( $definition['type'] ) {
				case 'number':
					if ( ! is_numeric( $value ) ) {
						$errors[] = array(
							'code'  => 'custom_number',
							'field' => $field,
						);
						break;
					}
					$clean[ $key ] = $value + 0;
					break;
				case 'date':
					if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) || ! checkdate( (int) substr( $value, 5, 2 ), (int) substr( $value, 8, 2 ), (int) substr( $value, 0, 4 ) ) ) {
						$errors[] = array(
							'code'  => 'custom_date',
							'field' => $field,
						);
						break;
					}
					$clean[ $key ] = $value;
					break;
				case 'url':
					if ( ! is_string( $value ) || ! self::isHttps( $value ) ) {
						$errors[] = array(
							'code'  => 'custom_url',
							'field' => $field,
						);
						break;
					}
					$clean[ $key ] = trim( $value );
					break;
				case 'list':
					$clean[ $key ] = self::technologies( is_array( $value ) ? $value : array( $value ) );
					break;
				default:
					$clean[ $key ] = self::text( $value, 300 );
			}
		}
		return array(
			'values' => $clean,
			'errors' => $errors,
		);
	}

	/**
	 * Single-line text without markup, trimmed and cut to a length.
	 *
	 * @param mixed $value  Value.
	 * @param int   $length Maximum characters.
	 */
	public static function text( $value, int $length ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$plain = trim( (string) preg_replace( '/\s+/u', ' ', strip_tags( (string) $value ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- pure model, runs without WordPress.
		return mb_substr( $plain, 0, $length );
	}

	/**
	 * Whether the value is an https URL with a host.
	 *
	 * @param string $value URL.
	 */
	public static function isHttps( string $value ): bool {
		$parts = parse_url( trim( $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure model, runs without WordPress.
		return is_array( $parts ) && 'https' === ( $parts['scheme'] ?? '' ) && '' !== ( $parts['host'] ?? '' ) && false !== filter_var( trim( $value ), FILTER_VALIDATE_URL );
	}
}
