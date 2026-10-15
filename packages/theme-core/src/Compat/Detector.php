<?php
/**
 * Detects active third-party plugins by their well-known constants.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat;

/**
 * Capability detection used by yield decisions. The probe is injectable so tests do not need to define constants.
 *
 * @api
 */
final class Detector {

	/**
	 * SEO plugins: constant => name.
	 */
	public const SEO_PLUGINS = array(
		'WPSEO_VERSION'             => 'Yoast SEO',
		'RANK_MATH_VERSION'         => 'Rank Math',
		'AIOSEO_VERSION'            => 'All in One SEO',
		'SEOPRESS_VERSION'          => 'SEOPress',
		'THE_SEO_FRAMEWORK_VERSION' => 'The SEO Framework',
		'SLIM_SEO_VERSION'          => 'Slim SEO',
	);

	/**
	 * Returns the version for a constant, or null when it is not defined.
	 *
	 * @var callable(string): ?string
	 */
	private $probe;

	/**
	 * Creates the detector.
	 *
	 * @param callable(string): ?string|null $probe Constant probe (default: defined()/constant()).
	 */
	public function __construct( ?callable $probe = null ) {
		$this->probe = $probe ?? static fn( string $name ): ?string => defined( $name ) ? (string) constant( $name ) : null;
	}

	/**
	 * Active SEO plugins.
	 *
	 * @return list<array{name: string, version: string}>
	 */
	public function seoPlugins(): array {
		$found = array();
		foreach ( self::SEO_PLUGINS as $constant => $name ) {
			$version = ( $this->probe )( $constant );
			if ( null !== $version ) {
				$found[] = array(
					'name'    => $name,
					'version' => $version,
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
}
