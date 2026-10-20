<?php
/**
 * Persistence of settings.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Config;

/**
 * Stores only values that differ from the descriptor defaults in the autoloaded option joo_{slug}_settings
 * (a flat map of dotted keys).
 *
 * @internal
 */
final class SettingsRepository {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	private string $option;

	/**
	 * Descriptor.
	 *
	 * @var SettingsSchema
	 */
	private SettingsSchema $schema;

	/**
	 * Sanitiser.
	 *
	 * @var Sanitizer
	 */
	private Sanitizer $sanitizer;

	/**
	 * Cached stored values.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * Creates the repository.
	 *
	 * @param string         $slug      Theme slug.
	 * @param SettingsSchema $schema    Descriptor.
	 * @param Sanitizer|null $sanitizer Sanitiser.
	 */
	public function __construct( string $slug, SettingsSchema $schema, ?Sanitizer $sanitizer = null ) {
		$this->option    = 'joo_' . str_replace( '-', '_', $slug ) . '_settings';
		$this->schema    = $schema;
		$this->sanitizer = $sanitizer ?? new Sanitizer();
	}

	/**
	 * Option name.
	 */
	public function optionName(): string {
		return $this->option;
	}

	/**
	 * Stored overrides.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$stored      = get_option( $this->option, array() );
			$this->cache = is_array( $stored ) ? $stored : array();
		}
		return $this->cache;
	}

	/**
	 * Whether a key has a stored override.
	 *
	 * @param string $key Dotted key.
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->all() );
	}

	/**
	 * Stored value (null when absent).
	 *
	 * @param string $key Dotted key.
	 * @return mixed
	 */
	public function get( string $key ) {
		return $this->all()[ $key ] ?? null;
	}

	/**
	 * Validates and stores values. Unknown keys are ignored; invalid values are reported and not stored.
	 *
	 * @param array<string, mixed> $input    Raw values keyed by dotted key.
	 * @param callable|null        $baseline Returns the value a key has without any saved override
	 *                                       (manifest or core default); equal values are not stored.
	 * @return array{saved: array<string, mixed>, errors: array<string, string>}
	 */
	public function save( array $input, ?callable $baseline = null ): array {
		$stored = $this->all();
		$saved  = array();
		$errors = array();
		foreach ( $input as $key => $raw ) {
			$definition = $this->schema->get( (string) $key );
			if ( null === $definition ) {
				continue;
			}
			$definition['key'] = (string) $key;
			$result            = $this->sanitizer->sanitize( $definition, $raw );
			if ( ! $result['ok'] ) {
				$errors[ $key ] = $result['error'];
				continue;
			}
			$default = null !== $baseline ? $baseline( (string) $key ) : ( $definition['default'] ?? null );
			if ( $default === $result['value'] ) {
				unset( $stored[ $key ] );
			} else {
				$stored[ $key ] = $result['value'];
			}
			$saved[ $key ] = $result['value'];
		}
		$this->write( $stored );
		return array(
			'saved'  => $saved,
			'errors' => $errors,
		);
	}

	/**
	 * Removes one override or all of them.
	 *
	 * @param string|null $key Dotted key, or null for everything.
	 */
	public function reset( ?string $key = null ): void {
		if ( null === $key ) {
			$this->write( array() );
			return;
		}
		$stored = $this->all();
		unset( $stored[ $key ] );
		$this->write( $stored );
	}

	/**
	 * Bumps the core cache version to invalidate cached fragments.
	 */
	public function bumpCacheVersion(): void {
		update_option( 'joo_cache_version', time(), true );
	}

	/**
	 * Writes the option.
	 *
	 * @param array<string, mixed> $stored Overrides.
	 */
	private function write( array $stored ): void {
		$this->cache = $stored;
		if ( array() === $stored ) {
			delete_option( $this->option );
			return;
		}
		update_option( $this->option, $stored, true );
	}
}
