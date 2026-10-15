<?php
/**
 * Loads and validates the theme manifest.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Config;

/**
 * Reads joo-theme.json, validates it against schema/manifest.schema.json and caches the result in a
 * transient keyed by file mtime, size and core version (so edits invalidate the cache automatically).
 *
 * @internal
 */
final class ManifestLoader {

	private const MAX_BYTES = 262144;

	/**
	 * Validator.
	 *
	 * @var SchemaValidator
	 */
	private SchemaValidator $validator;

	/**
	 * Path of the schema file.
	 *
	 * @var string
	 */
	private string $schemaPath;

	/**
	 * Creates the loader.
	 *
	 * @param SchemaValidator|null $validator  Validator.
	 * @param string|null          $schemaPath Schema file (default: core schema).
	 */
	public function __construct( ?SchemaValidator $validator = null, ?string $schemaPath = null ) {
		$this->validator  = $validator ?? new SchemaValidator();
		$this->schemaPath = $schemaPath ?? ( defined( 'JOO_CORE_DIR' ) ? JOO_CORE_DIR : dirname( __DIR__, 2 ) ) . '/schema/manifest.schema.json';
	}

	/**
	 * Loads a manifest file.
	 *
	 * @param string $path Absolute path to joo-theme.json.
	 */
	public function load( string $path ): Manifest {
		$fallback = basename( dirname( $path ) );
		if ( ! is_readable( $path ) || ! is_file( $path ) ) {
			return Manifest::invalid( array( 'Manifest file not found: ' . basename( $path ) ), $fallback );
		}
		$size = (int) filesize( $path );
		if ( $size > self::MAX_BYTES ) {
			return Manifest::invalid( array( sprintf( 'Manifest is larger than %d bytes.', self::MAX_BYTES ) ), $fallback );
		}

		$use_cache = ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) && function_exists( 'get_transient' );
		$key       = 'joo_manifest_' . substr( md5( $path . '|' . (int) filemtime( $path ) . '|' . $size . '|' . ( defined( 'JOO_CORE_VERSION' ) ? JOO_CORE_VERSION : '' ) ), 0, 20 );
		if ( $use_cache ) {
			$cached = get_transient( $key );
			if ( is_array( $cached ) && isset( $cached['data'], $cached['errors'] ) ) {
				return new Manifest( $cached['data'], $cached['errors'], $fallback );
			}
		}

		$manifest = $this->parse( (string) file_get_contents( $path ), $fallback ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( $use_cache && $manifest->isValid() ) {
			set_transient(
				$key,
				array(
					'data'   => $manifest->raw(),
					'errors' => array(),
				),
				12 * HOUR_IN_SECONDS
			);
		}
		return $manifest;
	}

	/**
	 * Parses and validates JSON text.
	 *
	 * @param string $json     JSON text.
	 * @param string $fallback Fallback slug.
	 */
	public function parse( string $json, string $fallback = 'theme' ): Manifest {
		$json = preg_replace( '/^\xEF\xBB\xBF/', '', $json ) ?? $json;
		try {
			$data = json_decode( $json, true, 32, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $error ) {
			return Manifest::invalid( array( 'Manifest is not valid JSON: ' . $error->getMessage() ), $fallback );
		}
		if ( ! is_array( $data ) ) {
			return Manifest::invalid( array( 'Manifest must be a JSON object.' ), $fallback );
		}
		$schema = $this->schema();
		if ( array() === $schema ) {
			return Manifest::invalid( array( 'Manifest schema is missing from core.' ), $fallback );
		}
		$errors = $this->validator->validate( $data, $schema );
		return $errors ? new Manifest( array(), $errors, $fallback ) : new Manifest( $data, array(), $fallback );
	}

	/**
	 * Manifest schema (empty array when unreadable).
	 *
	 * @return array<string, mixed>
	 */
	private function schema(): array {
		if ( ! is_readable( $this->schemaPath ) ) {
			return array();
		}
		$decoded = json_decode( (string) file_get_contents( $this->schemaPath ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $decoded ) ? $decoded : array();
	}
}
