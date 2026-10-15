<?php
/**
 * Effective settings.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Config;

use JOOservices\ThemeCore\Support\Hooks;

/**
 * Resolves settings with the precedence: PHP filter, saved value, theme manifest, core default.
 *
 * @api
 */
final class Settings {

	public const SOURCE_FILTER   = 'filter';
	public const SOURCE_USER     = 'user';
	public const SOURCE_MANIFEST = 'manifest';
	public const SOURCE_CORE     = 'core';

	/**
	 * Descriptor.
	 *
	 * @var SettingsSchema
	 */
	private SettingsSchema $schema;

	/**
	 * Manifest.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Repository.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $repository;

	/**
	 * Defaults after the joo/settings/defaults filter.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $defaults = null;

	/**
	 * Creates the settings service.
	 *
	 * @param SettingsSchema     $schema     Descriptor.
	 * @param Manifest           $manifest   Manifest.
	 * @param SettingsRepository $repository Repository.
	 */
	public function __construct( SettingsSchema $schema, Manifest $manifest, SettingsRepository $repository ) {
		$this->schema     = $schema;
		$this->manifest   = $manifest;
		$this->repository = $repository;
	}

	/**
	 * Effective value of a key.
	 *
	 * @param string $key     Dotted key.
	 * @param mixed  $default Returned for keys that are not in the descriptor and have no filter value.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$resolved = $this->resolve( $key );
		return null === $resolved ? $default : $resolved[0];
	}

	/**
	 * Where the effective value comes from: filter, user, manifest or core ('unknown' for unregistered keys).
	 *
	 * @param string $key Dotted key.
	 */
	public function source( string $key ): string {
		$resolved = $this->resolve( $key );
		return null === $resolved ? 'unknown' : $resolved[1];
	}

	/**
	 * Every registered key with its effective value.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$out = array();
		foreach ( array_keys( $this->schema->all() ) as $key ) {
			$out[ $key ] = $this->get( $key );
		}
		return $out;
	}

	/**
	 * Validates and stores overrides. Values equal to what the key would have without an override are not stored.
	 *
	 * @param array<string, mixed> $input Raw values keyed by dotted key.
	 * @return array{saved: array<string, mixed>, errors: array<string, string>}
	 */
	public function save( array $input ): array {
		return $this->repository->save( $input, fn( string $key ) => $this->baseline( $key ) );
	}

	/**
	 * Value of a key ignoring saved overrides and filters (manifest, then core default).
	 *
	 * @param string $key Dotted key.
	 * @return mixed
	 */
	public function baseline( string $key ) {
		$definition = $this->schema->get( $key );
		$path       = null === $definition ? null : ( $definition['manifest'] ?? null );
		if ( is_string( $path ) && $this->manifest->has( $path ) ) {
			return $this->manifest->get( $path );
		}
		return $this->defaults()[ $key ] ?? null;
	}

	/**
	 * Descriptor.
	 */
	public function schema(): SettingsSchema {
		return $this->schema;
	}

	/**
	 * Repository (for the admin page and WP-CLI).
	 */
	public function repository(): SettingsRepository {
		return $this->repository;
	}

	/**
	 * Manifest.
	 */
	public function manifest(): Manifest {
		return $this->manifest;
	}

	/**
	 * Resolves a key.
	 *
	 * @param string $key Dotted key.
	 * @return array{0: mixed, 1: string}|null
	 */
	private function resolve( string $key ): ?array {
		$filtered = Hooks::filter( 'joo/settings/value', null, $key );
		if ( null !== $filtered ) {
			return array( $filtered, self::SOURCE_FILTER );
		}
		$definition = $this->schema->get( $key );
		if ( null === $definition ) {
			return null;
		}
		if ( $this->repository->has( $key ) ) {
			return array( $this->repository->get( $key ), self::SOURCE_USER );
		}
		$path = $definition['manifest'] ?? null;
		if ( is_string( $path ) && $this->manifest->has( $path ) ) {
			return array( $this->manifest->get( $path ), self::SOURCE_MANIFEST );
		}
		return array( $this->defaults()[ $key ] ?? null, self::SOURCE_CORE );
	}

	/**
	 * Core defaults, filtered once.
	 *
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		if ( null === $this->defaults ) {
			$filtered       = Hooks::filter( 'joo/settings/defaults', $this->schema->defaults() );
			$this->defaults = is_array( $filtered ) ? $filtered : $this->schema->defaults();
		}
		return $this->defaults;
	}
}
