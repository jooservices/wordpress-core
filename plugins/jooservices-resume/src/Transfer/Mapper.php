<?php
/**
 * Maps company data to and from JSON Resume, the own JSON format and CSV.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Transfer;

use JOOservices\Resume\Model\Period;
use JOOservices\Resume\Model\Repeaters;

/**
 * Pure mapping and validation (no WordPress calls). A company row is the flat array that the importer saves.
 */
final class Mapper {

	public const FORMAT = 'jooservices-resume';

	public const VERSION = 1;

	public const MAX_ROWS = 200;

	/**
	 * Validates and normalises one company row from either format.
	 *
	 * @param array<string, mixed>       $raw         Raw row.
	 * @param list<array<string, mixed>> $definitions Custom field definitions.
	 * @return array{company: array<string, mixed>, errors: list<array{code: string, field: string}>}
	 */
	public static function company( array $raw, array $definitions ): array {
		$errors = array();
		$name   = Repeaters::text( $raw['name'] ?? '', 120 );
		$from   = (string) ( $raw['from'] ?? '' );
		$to     = (string) ( $raw['to'] ?? '' );
		$url    = trim( (string) ( $raw['url'] ?? '' ) );
		if ( '' === $name ) {
			$errors[] = array(
				'code'  => 'company_name',
				'field' => 'name',
			);
		}
		if ( ! Period::isMonth( $from ) || ( '' !== $to && ! Period::isMonth( $to ) ) ) {
			$errors[] = array(
				'code'  => 'company_month',
				'field' => 'from',
			);
		} elseif ( '' !== $to && strcmp( $from, $to ) > 0 ) {
			$errors[] = array(
				'code'  => 'company_order',
				'field' => 'to',
			);
		}
		if ( '' !== $url && ! Repeaters::isHttps( $url ) ) {
			$errors[] = array(
				'code'  => 'company_url',
				'field' => 'url',
			);
		}
		$employment = (string) ( $raw['employment'] ?? '' );
		if ( '' !== $employment && ! in_array( $employment, Repeaters::EMPLOYMENT, true ) ) {
			$errors[]   = array(
				'code'  => 'employment',
				'field' => 'employment',
			);
			$employment = '';
		}
		$roles        = Repeaters::roles( self::list( $raw['roles'] ?? array() ), $from, $to );
		$achievements = Repeaters::achievements( self::list( $raw['achievements'] ?? array() ) );
		$custom       = Repeaters::customValues( is_array( $raw['custom'] ?? null ) ? $raw['custom'] : array(), $definitions );
		$errors       = array_merge( $errors, $roles['errors'], $achievements['errors'], $custom['errors'] );
		$tags         = $raw['technologies'] ?? array();
		return array(
			'company' => array(
				'name'          => $name,
				'role'          => Repeaters::text( $raw['role'] ?? '', 80 ),
				'from'          => $from,
				'to'            => $to,
				'pinned'        => ! empty( $raw['pinned'] ) && '' === $to,
				'url'           => $url,
				'summary'       => Repeaters::text( $raw['summary'] ?? '', 200 ),
				'description'   => (string) ( $raw['description'] ?? '' ),
				'city'          => Repeaters::text( $raw['city'] ?? '', 80 ),
				'country'       => Repeaters::text( $raw['country'] ?? '', 80 ),
				'employment'    => $employment,
				'industry'      => Repeaters::text( $raw['industry'] ?? '', 80 ),
				'remote'        => ! empty( $raw['remote'] ),
				'roles'         => $roles['items'],
				'achievements'  => $achievements['items'],
				'technologies'  => Repeaters::technologies( is_array( $tags ) ? $tags : array() ),
				'custom'        => $custom['values'],
				'compensation'  => Repeaters::text( $raw['compensation'] ?? '', 120 ),
				'equity'        => Repeaters::text( $raw['equity'] ?? '', 120 ),
				'public_fields' => array_values( array_intersect( array( 'compensation', 'equity' ), is_array( $raw['public_fields'] ?? null ) ? $raw['public_fields'] : array() ) ),
				'menu_order'    => (int) ( $raw['menu_order'] ?? 0 ),
			),
			'errors'  => $errors,
		);
	}

	/**
	 * Reads an uploaded document: the own format or JSON Resume (`work`). The result lists every row with its errors.
	 *
	 * @param string                     $json        Document.
	 * @param list<array<string, mixed>> $definitions Custom field definitions.
	 * @return array{format: string, rows: list<array{company: array<string, mixed>, errors: list<array{code: string, field: string}>}>, errors: list<array{code: string, field: string}>}
	 */
	public static function parse( string $json, array $definitions ): array {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return array(
				'format' => '',
				'rows'   => array(),
				'errors' => array(
					array(
						'code'  => 'import_json',
						'field' => 'file',
					),
				),
			);
		}
		if ( ( $data['format'] ?? '' ) === self::FORMAT && is_array( $data['companies'] ?? null ) ) {
			$format = self::FORMAT;
			$source = array_values( array_filter( $data['companies'], 'is_array' ) );
		} elseif ( is_array( $data['work'] ?? null ) ) {
			$format = 'json-resume';
			$source = array_map( array( self::class, 'fromJsonResume' ), array_values( array_filter( $data['work'], 'is_array' ) ) );
		} else {
			return array(
				'format' => '',
				'rows'   => array(),
				'errors' => array(
					array(
						'code'  => 'import_format',
						'field' => 'file',
					),
				),
			);
		}
		if ( count( $source ) > self::MAX_ROWS ) {
			return array(
				'format' => $format,
				'rows'   => array(),
				'errors' => array(
					array(
						'code'  => 'import_rows',
						'field' => 'file',
					),
				),
			);
		}
		$rows = array();
		foreach ( $source as $raw ) {
			$rows[] = self::company( $raw, $definitions );
		}
		return array(
			'format' => $format,
			'rows'   => $rows,
			'errors' => array() === $rows ? array(
				array(
					'code'  => 'import_empty',
					'field' => 'file',
				),
			) : array(),
		);
	}

	/**
	 * A JSON Resume `work` entry as a company row.
	 *
	 * @param array<string, mixed> $work Entry.
	 * @return array<string, mixed>
	 */
	public static function fromJsonResume( array $work ): array {
		$highlights = is_array( $work['highlights'] ?? null ) ? $work['highlights'] : array();
		return array(
			'name'         => $work['name'] ?? ( $work['company'] ?? '' ),
			'role'         => $work['position'] ?? '',
			'from'         => self::month( (string) ( $work['startDate'] ?? '' ) ),
			'to'           => self::month( (string) ( $work['endDate'] ?? '' ) ),
			'url'          => $work['url'] ?? '',
			'summary'      => $work['summary'] ?? '',
			'city'         => $work['location'] ?? '',
			'achievements' => array_map( static fn( $text ): array => array( 'text' => $text ), array_filter( $highlights, 'is_scalar' ) ),
		);
	}

	/**
	 * `YYYY-MM-DD` or `YYYY-MM` reduced to a month; anything else stays unchanged so validation reports it.
	 *
	 * @param string $date Date.
	 */
	public static function month( string $date ): string {
		return 1 === preg_match( '/^(\d{4}-(?:0[1-9]|1[0-2]))(?:-\d{2})?$/', $date, $m ) ? $m[1] : $date;
	}

	/**
	 * Own-format document of company rows.
	 *
	 * @param list<array<string, mixed>> $companies Rows.
	 */
	public static function toOwn( array $companies ): string {
		return (string) json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- pure mapper, runs without WordPress.
			array(
				'format'    => self::FORMAT,
				'version'   => self::VERSION,
				'companies' => array_values( $companies ),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * JSON Resume `work` section (public data only: no compensation or equity).
	 *
	 * @param list<array<string, mixed>> $companies Rows.
	 */
	public static function toJsonResume( array $companies ): string {
		$work = array();
		foreach ( $companies as $company ) {
			$entry = array(
				'name'       => $company['name'],
				'position'   => $company['role'],
				'url'        => $company['url'],
				'startDate'  => $company['from'],
				'summary'    => $company['summary'],
				'highlights' => array_column( $company['achievements'], 'text' ),
			);
			if ( '' !== $company['to'] ) {
				$entry['endDate'] = $company['to'];
			}
			if ( '' !== $company['city'] ) {
				$entry['location'] = $company['city'];
			}
			$work[] = $entry;
		}
		return (string) json_encode( array( 'work' => $work ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- pure mapper, runs without WordPress.
	}

	/**
	 * CSV of the list (one line per company). Cells starting with a formula character are prefixed with a quote.
	 *
	 * @param list<array<string, mixed>> $companies Rows.
	 */
	public static function toCsv( array $companies ): string {
		$lines = array( self::csvLine( array( 'name', 'role', 'from', 'to', 'current', 'pinned', 'city', 'country', 'employment', 'industry', 'remote', 'url', 'technologies' ) ) );
		foreach ( $companies as $c ) {
			$lines[] = self::csvLine(
				array(
					$c['name'],
					$c['role'],
					$c['from'],
					$c['to'],
					'' === $c['to'] ? 'yes' : 'no',
					$c['pinned'] ? 'yes' : 'no',
					$c['city'],
					$c['country'],
					$c['employment'],
					$c['industry'],
					$c['remote'] ? 'yes' : 'no',
					$c['url'],
					implode( '; ', $c['technologies'] ),
				)
			);
		}
		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * One CSV line.
	 *
	 * @param list<string> $cells Cells.
	 */
	private static function csvLine( array $cells ): string {
		return implode(
			',',
			array_map(
				static function ( string $cell ): string {
					$cell = 1 === preg_match( '/^[=+\-@\t\r]/', $cell ) ? "'" . $cell : $cell;
					return '"' . str_replace( '"', '""', $cell ) . '"';
				},
				$cells
			)
		);
	}

	/**
	 * Items of a value that must be a list of arrays.
	 *
	 * @param mixed $value Value.
	 * @return list<array<string, mixed>>
	 */
	private static function list( $value ): array {
		return is_array( $value ) ? array_values( array_filter( $value, 'is_array' ) ) : array();
	}
}
