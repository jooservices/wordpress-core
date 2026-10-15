<?php
/**
 * Settings descriptor (schema/settings.schema.json).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Config;

/**
 * Read access to the settings descriptor: groups, keys, defaults, UI hints and sanitiser names.
 *
 * @internal
 */
final class SettingsSchema {

	/**
	 * Descriptor.
	 *
	 * @var array{version: int, groups: list<array{id: string, title: string}>, settings: array<string, array<string, mixed>>}
	 */
	private array $data;

	/**
	 * Creates the schema.
	 *
	 * @param array<string, mixed>|null $data Descriptor (default: file shipped with core).
	 */
	public function __construct( ?array $data = null ) {
		if ( null === $data ) {
			$path    = ( defined( 'JOO_CORE_DIR' ) ? JOO_CORE_DIR : dirname( __DIR__, 2 ) ) . '/schema/settings.schema.json';
			$decoded = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$data    = is_array( $decoded ) ? $decoded : array();
		}
		$this->data = array(
			'version'  => (int) ( $data['version'] ?? 1 ),
			'groups'   => is_array( $data['groups'] ?? null ) ? array_values( $data['groups'] ) : array(),
			'settings' => is_array( $data['settings'] ?? null ) ? $data['settings'] : array(),
		);
	}

	/**
	 * All setting definitions keyed by dotted key.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		return $this->data['settings'];
	}

	/**
	 * Definition of one key.
	 *
	 * @param string $key Dotted key.
	 * @return array<string, mixed>|null
	 */
	public function get( string $key ): ?array {
		return $this->data['settings'][ $key ] ?? null;
	}

	/**
	 * Groups (tabs).
	 *
	 * @return list<array{id: string, title: string}>
	 */
	public function groups(): array {
		return $this->data['groups'];
	}

	/**
	 * Descriptor version.
	 */
	public function version(): int {
		return $this->data['version'];
	}

	/**
	 * Defaults keyed by dotted key.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array_map( static fn( array $definition ) => $definition['default'] ?? null, $this->data['settings'] );
	}

	/**
	 * Keys of one group.
	 *
	 * @param string $group Group id.
	 * @return list<string>
	 */
	public function keysInGroup( string $group ): array {
		return array_keys( array_filter( $this->data['settings'], static fn( array $definition ) => ( $definition['group'] ?? '' ) === $group ) );
	}
}
