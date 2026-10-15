<?php
/**
 * Build manifest (assets.json) reader.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Assets;

/**
 * Maps logical asset names to hashed files produced by tools/build-assets.mjs.
 *
 * @api
 */
final class AssetManifest {

	/**
	 * Assets by logical name.
	 *
	 * @var array<string, array{type: string, file: string, size: int, gzip: int}>
	 */
	private array $assets;

	/**
	 * Base URL of the build directory (no trailing slash).
	 *
	 * @var string
	 */
	private string $baseUrl;

	/**
	 * Creates the manifest.
	 *
	 * @param array<string, array{type: string, file: string, size: int, gzip: int}> $assets  Assets.
	 * @param string                                                                $baseUrl Build directory URL.
	 */
	public function __construct( array $assets, string $baseUrl ) {
		$this->assets  = $assets;
		$this->baseUrl = rtrim( $baseUrl, '/' );
	}

	/**
	 * Loads assets.json (an empty manifest when the file is missing or invalid).
	 *
	 * @param string $file    Path of assets.json.
	 * @param string $baseUrl URL of the directory that contains assets.json.
	 */
	public static function fromFile( string $file, string $baseUrl ): self {
		if ( ! is_readable( $file ) ) {
			return new self( array(), $baseUrl );
		}
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return new self( is_array( $data ) && is_array( $data['assets'] ?? null ) ? $data['assets'] : array(), $baseUrl );
	}

	/**
	 * Whether a logical name exists.
	 *
	 * @param string $logical Logical name.
	 */
	public function has( string $logical ): bool {
		return isset( $this->assets[ $logical ] );
	}

	/**
	 * Public URL of a logical asset (empty string when unknown).
	 *
	 * @param string $logical Logical name.
	 */
	public function url( string $logical ): string {
		return isset( $this->assets[ $logical ] ) ? $this->baseUrl . '/' . $this->assets[ $logical ]['file'] : '';
	}

	/**
	 * Stored metadata for a logical asset.
	 *
	 * @param string $logical Logical name.
	 * @return array{type: string, file: string, size: int, gzip: int}|null
	 */
	public function info( string $logical ): ?array {
		return $this->assets[ $logical ] ?? null;
	}

	/**
	 * Every logical name.
	 *
	 * @return list<string>
	 */
	public function names(): array {
		return array_keys( $this->assets );
	}
}
