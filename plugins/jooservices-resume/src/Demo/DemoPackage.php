<?php
/**
 * Reads and validates the signed-off demo-content archive.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Demo;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Package errors are reported through WP-CLI, never rendered as HTML.

use RuntimeException;
use ZipArchive;

/**
 * Restricted archive reader for the licence-clean buyer package.
 */
final class DemoPackage {

	public const MAX_BYTES = 41943040;

	private const REQUIRED_FILES = array(
		'ATTRIBUTION.md',
		'LICENSES.md',
		'content.xml',
		'language.json',
		'manifest.json',
		'menus.json',
		'patterns.json',
		'settings.json',
		'structure.json',
		'theme-options.json',
		'widgets.json',
		'checksums.json',
	);

	/**
	 * Open archive.
	 *
	 * @var ZipArchive
	 */
	private ?ZipArchive $archive = null;

	/**
	 * Normalized package path => stored ZIP path.
	 *
	 * @var array<string, string>
	 */
	private array $entries = array();

	/**
	 * Package ZIP path.
	 *
	 * @var string
	 */
	private string $path;

	/**
	 * Opens the archive after basic size checks.
	 *
	 * @param string $path Local ZIP path.
	 */
	public function __construct( string $path ) {
		if ( ! class_exists( ZipArchive::class ) ) {
			throw new RuntimeException( 'The PHP zip extension is required to read the demo package.' );
		}
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			throw new RuntimeException( 'The demo package ZIP could not be read.' );
		}
		$size = filesize( $path );
		if ( false === $size || $size <= 0 || $size >= self::MAX_BYTES ) {
			throw new RuntimeException( 'The demo package must be smaller than 40 MB.' );
		}

		$this->path = $path;
		$archive    = new ZipArchive();
		if ( true !== $archive->open( $path, ZipArchive::CHECKCONS ) ) {
			throw new RuntimeException( 'The demo package is not a valid ZIP archive.' );
		}
		$this->archive = $archive;
		$this->indexEntries();
	}

	/**
	 * Closes the archive.
	 */
	public function __destruct() {
		if ( $this->archive instanceof ZipArchive ) {
			$this->archive->close();
		}
	}

	/**
	 * Checks the archive contract, checksums, WXR, and required JSON documents.
	 *
	 * @return array<string, mixed>
	 */
	public function validate(): array {
		$errors = array();
		foreach ( self::REQUIRED_FILES as $name ) {
			if ( ! isset( $this->entries[ $name ] ) ) {
				$errors[] = 'Required package file is missing: ' . $name;
			}
		}
		if ( array() !== $errors ) {
			throw new RuntimeException( implode( ' ', $errors ) );
		}

		$size = 0;
		foreach ( $this->entries as $name => $stored_name ) {
			$stat = $this->archive()->statName( $stored_name );
			if ( ! is_array( $stat ) || ! isset( $stat['size'] ) ) {
				throw new RuntimeException( 'Could not inspect package entry: ' . $name );
			}
			$size += (int) $stat['size'];
			if ( $size > self::MAX_BYTES ) {
				throw new RuntimeException( 'The unpacked demo package must be smaller than 40 MB.' );
			}
		}

		$checksums = $this->json( 'checksums.json' );
		if ( array() === $checksums ) {
			throw new RuntimeException( 'The package checksum list is empty.' );
		}
		foreach ( $checksums as $name => $expected ) {
			if ( ! is_string( $name ) || ! is_string( $expected ) || ! preg_match( '/^[a-f0-9]{64}$/', $expected ) || ! isset( $this->entries[ $name ] ) ) {
				throw new RuntimeException( 'The package checksum list is invalid.' );
			}
			$actual = hash( 'sha256', $this->read( $name ) );
			if ( ! hash_equals( $expected, $actual ) ) {
				throw new RuntimeException( 'Package checksum failed: ' . $name );
			}
		}
		$unchecked = array_diff( array_keys( $this->entries ), array_keys( $checksums ), array( 'checksums.json' ) );
		if ( array() !== $unchecked ) {
			throw new RuntimeException( 'The package contains files missing from checksums.json: ' . implode( ', ', $unchecked ) );
		}

		$manifest = $this->json( 'manifest.json' );
		if ( 1 !== (int) ( $manifest['packageVersion'] ?? 0 ) || 'B' !== (string) ( $manifest['tier'] ?? '' ) || 'CC-BY-4.0' !== (string) ( $manifest['license'] ?? '' ) ) {
			throw new RuntimeException( 'Only the approved licence-clean Tier B package is supported.' );
		}
		$license = $this->read( 'LICENSES.md' );
		if ( false === strpos( $license, 'CC BY 4.0' ) || false === strpos( $license, 'No third-party' ) ) {
			throw new RuntimeException( 'The package licence statement is incomplete.' );
		}
		foreach ( array( 'menus.json', 'settings.json', 'language.json', 'widgets.json', 'patterns.json', 'theme-options.json', 'structure.json' ) as $json_file ) {
			$this->json( $json_file );
		}

		$xml = $this->read( 'content.xml' );
		if ( false !== stripos( $xml, '<!DOCTYPE' ) || false !== stripos( $xml, '<!ENTITY' ) ) {
			throw new RuntimeException( 'The WXR document cannot contain a document type or entity declaration.' );
		}
		libxml_use_internal_errors( true );
		$document = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
		if ( false === $document || '1.2' !== (string) $document->channel->children( 'http://wordpress.org/export/1.2/' )->wxr_version ) {
			throw new RuntimeException( 'The package must contain a valid WordPress WXR 1.2 document.' );
		}

		$media = array_values(
			array_filter(
				array_keys( $this->entries ),
				static fn( string $name ): bool => str_starts_with( $name, 'media/' )
			)
		);
		if ( array() === $media || count( $media ) !== (int) ( $manifest['expected']['mediaFiles'] ?? 0 ) ) {
			throw new RuntimeException( 'The package media files do not match manifest.json.' );
		}
		foreach ( $media as $file ) {
			if ( 'png' !== strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
				throw new RuntimeException( 'Only generated PNG media is allowed in the Tier B package.' );
			}
		}

		return array(
			'hash'      => hash_file( 'sha256', $this->path ),
			'size'      => filesize( $this->path ),
			'manifest'  => $manifest,
			'structure' => $this->json( 'structure.json' ),
			'xml'       => $document,
			'media'     => $media,
		);
	}

	/**
	 * Reads one file from the archive.
	 *
	 * @param string $name Normalized package path.
	 */
	public function read( string $name ): string {
		if ( ! isset( $this->entries[ $name ] ) ) {
			throw new RuntimeException( 'Package file is missing: ' . $name );
		}
		$value = $this->archive()->getFromName( $this->entries[ $name ] );
		if ( false === $value ) {
			throw new RuntimeException( 'Could not read package file: ' . $name );
		}
		return $value;
	}

	/**
	 * Reads and decodes a JSON object.
	 *
	 * @param string $name Package path.
	 * @return array<string, mixed>
	 */
	public function json( string $name ): array {
		$data = json_decode( $this->read( $name ), true );
		if ( ! is_array( $data ) ) {
			throw new RuntimeException( 'Package JSON is invalid: ' . $name );
		}
		return $data;
	}

	/**
	 * Maps safe package paths to ZIP entries and rejects ambiguous or unsafe paths.
	 */
	private function indexEntries(): void {
		$archive = $this->archive();
		$prefix  = '';
		for ( $index = 0; $index < $archive->numFiles; ++$index ) {
			$name = $archive->getNameIndex( $index );
			if ( false === $name || str_ends_with( $name, '/' ) ) {
				continue;
			}
			if ( str_starts_with( $name, 'Demo-Content/' ) ) {
				$prefix = 'Demo-Content/';
				break;
			}
		}

		$total = 0;
		for ( $index = 0; $index < $archive->numFiles; ++$index ) {
			$name = $archive->getNameIndex( $index );
			if ( false === $name || str_ends_with( $name, '/' ) ) {
				continue;
			}
			$normalized = str_starts_with( $name, $prefix ) ? substr( $name, strlen( $prefix ) ) : $name;
			if ( '' === $normalized || str_contains( $normalized, '\\' ) || str_contains( $normalized, "\0" ) || str_starts_with( $normalized, '/' ) || preg_match( '#(^|/)\.\.?(/|$)#', $normalized ) ) {
				throw new RuntimeException( 'The package contains an unsafe archive path.' );
			}
			if ( isset( $this->entries[ $normalized ] ) ) {
				throw new RuntimeException( 'The package contains a duplicate path: ' . $normalized );
			}
			$stat   = $archive->statIndex( $index );
			$total += (int) ( is_array( $stat ) ? ( $stat['size'] ?? 0 ) : 0 );
			if ( $total > self::MAX_BYTES ) {
				throw new RuntimeException( 'The unpacked demo package must be smaller than 40 MB.' );
			}
			$this->entries[ $normalized ] = $name;
		}
	}

	/**
	 * Returns the open archive.
	 */
	private function archive(): ZipArchive {
		if ( ! $this->archive instanceof ZipArchive ) {
			throw new RuntimeException( 'The demo package archive is not open.' );
		}
		return $this->archive;
	}
}
