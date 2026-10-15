<?php
/**
 * Detects active third-party plugins.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat;

/**
 * Cheap, autoload-free detection from constants, already loaded classes and functions, then the active plugin list.
 * The environment probe is injectable: callable(string $kind, string $name): ?string with kind constant|class|function|plugin.
 *
 * @api
 */
final class Detector {

	/**
	 * Environment probe.
	 *
	 * @var callable(string, string): ?string
	 */
	private $probe;

	/**
	 * Per-request cache: key => version|'' (detected) | false (absent).
	 *
	 * @var array<string, string|false>
	 */
	private array $cache = array();

	/**
	 * Creates the detector.
	 *
	 * @param callable(string, string): ?string|null $probe Environment probe (default: the live environment).
	 */
	public function __construct( ?callable $probe = null ) {
		$this->probe = $probe ?? array( self::class, 'liveProbe' );
	}

	/**
	 * Looks at the running site (never autoloads classes).
	 *
	 * @param string $kind constant|class|function|plugin.
	 * @param string $name Name or plugin file.
	 */
	public static function liveProbe( string $kind, string $name ): ?string {
		switch ( $kind ) {
			case 'constant':
				return defined( $name ) ? (string) constant( $name ) : null;
			case 'class':
				return class_exists( $name, false ) ? '' : null;
			case 'function':
				return function_exists( $name ) ? '' : null;
			case 'plugin':
				if ( ! function_exists( 'get_option' ) ) {
					return null;
				}
				$active = (array) get_option( 'active_plugins', array() );
				if ( in_array( $name, $active, true ) ) {
					return '';
				}
				$network = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
				return isset( $network[ $name ] ) ? '' : null;
			default:
				return null;
		}
	}

	/**
	 * Whether a plugin is active.
	 *
	 * @param string $key Plugin key (see Signatures).
	 */
	public function active( string $key ): bool {
		return false !== $this->detect( $key );
	}

	/**
	 * Detected version (null when absent or unknown).
	 *
	 * @param string $key Plugin key.
	 */
	public function version( string $key ): ?string {
		$found = $this->detect( $key );
		return is_string( $found ) && '' !== $found ? $found : null;
	}

	/**
	 * Key of the first active plugin that provides a capability.
	 *
	 * @param string $capability seo|lazyload|critical-css|minify|multilingual|page-builder|cache|forms|images...
	 */
	public function provides( string $capability ): ?string {
		foreach ( Signatures::all() as $key => $signature ) {
			if ( in_array( $capability, $signature['provides'], true ) && $this->active( $key ) ) {
				return $key;
			}
		}
		return null;
	}

	/**
	 * Active plugins as key => [name, version, provides, tier].
	 *
	 * @return array<string, array{name: string, version: string, provides: list<string>, tier: int}>
	 */
	public function all(): array {
		$out = array();
		foreach ( Signatures::all() as $key => $signature ) {
			if ( $this->active( $key ) ) {
				$out[ $key ] = array(
					'name'     => $signature['name'],
					'version'  => $this->version( $key ) ?? '',
					'provides' => $signature['provides'],
					'tier'     => $signature['tier'],
				);
			}
		}
		return $out;
	}

	/**
	 * Active SEO plugins.
	 *
	 * @return list<array{name: string, version: string}>
	 */
	public function seoPlugins(): array {
		$found = array();
		foreach ( $this->all() as $plugin ) {
			if ( in_array( 'seo', $plugin['provides'], true ) ) {
				$found[] = array(
					'name'    => $plugin['name'],
					'version' => $plugin['version'],
				);
			}
		}
		return $found;
	}

	/**
	 * First active SEO plugin (null when none).
	 *
	 * @return array{name: string, version: string}|null
	 */
	public function seoPlugin(): ?array {
		return $this->seoPlugins()[0] ?? null;
	}

	/**
	 * Detects one plugin (constants, classes, functions, plugin file; cached).
	 *
	 * @param string $key Plugin key.
	 * @return string|false Version ('' when unknown) or false.
	 */
	private function detect( string $key ) {
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}
		$signature = Signatures::all()[ $key ] ?? null;
		if ( null === $signature ) {
			$this->cache[ $key ] = false;
			return false;
		}
		$result = false;
		foreach ( $signature['constants'] ?? array() as $constant ) {
			$value = ( $this->probe )( 'constant', $constant );
			if ( null !== $value ) {
				$result = $value;
				break;
			}
		}
		if ( false === $result ) {
			foreach ( $signature['classes'] ?? array() as $class ) {
				if ( null !== ( $this->probe )( 'class', $class ) ) {
					$result = '';
					break;
				}
			}
		}
		if ( false === $result ) {
			foreach ( $signature['functions'] ?? array() as $function ) {
				if ( null !== ( $this->probe )( 'function', $function ) ) {
					$result = '';
					break;
				}
			}
		}
		if ( false === $result && isset( $signature['file'] ) && null !== ( $this->probe )( 'plugin', $signature['file'] ) ) {
			$result = '';
		}
		$this->cache[ $key ] = $result;
		return $result;
	}
}
