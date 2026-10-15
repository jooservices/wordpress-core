<?php
/**
 * Cache, optimisation and image plugin adapter.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Adapters;

use JOOservices\ThemeCore\Compat\Detector;

/**
 * Data driven: what the plugin does for the page (lazy loading, critical CSS, minification), which of
 * our handles it must leave alone and how to purge it. Every probe is guarded and cannot throw.
 *
 * Spec keys: lazy|ccss|minify (callable(): bool), exclusions (list of [filter, mode]), purge (callable(): void), notice.
 *
 * @internal
 */
final class CacheAdapter extends AbstractAdapter {

	/**
	 * Handles and tokens the plugin must not touch.
	 */
	public const EXCLUDE_TOKENS = array( 'joo-runtime', 'joo-config', 'joo-lazy', 'joo-embed', 'joo-' );

	/**
	 * Plugin key.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Spec.
	 *
	 * @var array<string, mixed>
	 */
	private array $spec;

	/**
	 * Creates the adapter.
	 *
	 * @param Detector             $detector Detector.
	 * @param string               $key      Plugin key.
	 * @param array<string, mixed> $spec     Spec.
	 */
	public function __construct( Detector $detector, string $key, array $spec ) {
		parent::__construct( $detector );
		$this->key  = $key;
		$this->spec = $spec;
	}

	/**
	 * Key.
	 */
	public function key(): string {
		return $this->key;
	}

	/**
	 * Notice.
	 */
	public function notice(): string {
		return (string) ( $this->spec['notice'] ?? '' );
	}

	/**
	 * Whether the plugin lazy-loads images itself.
	 */
	public function providesLazyload(): bool {
		return $this->flag( 'lazy' );
	}

	/**
	 * Whether the plugin generates critical CSS.
	 */
	public function providesCriticalCss(): bool {
		return $this->flag( 'ccss' );
	}

	/**
	 * Whether the plugin minifies or combines JavaScript.
	 */
	public function minifiesJs(): bool {
		return $this->flag( 'minify' );
	}

	/**
	 * Registers the exclusion filters for our script handles.
	 */
	public function boot(): void {
		foreach ( (array) ( $this->spec['exclusions'] ?? array() ) as $entry ) {
			$filter = (string) ( $entry[0] ?? '' );
			$mode   = (string) ( $entry[1] ?? 'list' );
			if ( '' === $filter ) {
				continue;
			}
			add_filter(
				$filter,
				static function ( $value, ...$args ) use ( $mode ) {
					return self::exclude( $value, $mode, $args );
				},
				10,
				3
			);
		}
	}

	/**
	 * Adds our tokens to an exclusion value (array, comma list or a boolean "do it" answer).
	 *
	 * @param mixed        $value Current value.
	 * @param string       $mode  list|csv|tag.
	 * @param array<mixed> $args  Extra filter arguments.
	 * @return mixed
	 */
	public static function exclude( $value, string $mode, array $args = array() ) {
		if ( 'tag' === $mode ) {
			foreach ( $args as $arg ) {
				if ( is_string( $arg ) && false !== strpos( $arg, 'joo-' ) ) {
					return false;
				}
			}
			return $value;
		}
		if ( 'csv' === $mode ) {
			$current = is_string( $value ) ? $value : '';
			return rtrim( $current, ', ' ) . ( '' === $current ? '' : ', ' ) . implode( ', ', array_slice( self::EXCLUDE_TOKENS, 0, 4 ) );
		}
		$list = is_array( $value ) ? $value : array();
		return array_values( array_unique( array_merge( $list, self::EXCLUDE_TOKENS ) ) );
	}

	/**
	 * Purges the plugin cache (guarded).
	 */
	public function purge(): void {
		if ( ! isset( $this->spec['purge'] ) || ! is_callable( $this->spec['purge'] ) ) {
			return;
		}
		try {
			( $this->spec['purge'] )();
		} catch ( \Throwable $error ) {
			unset( $error );
		}
	}

	/**
	 * Evaluates a boolean probe without ever throwing.
	 *
	 * @param string $name lazy|ccss|minify.
	 */
	private function flag( string $name ): bool {
		if ( ! isset( $this->spec[ $name ] ) || ! is_callable( $this->spec[ $name ] ) ) {
			return false;
		}
		try {
			return (bool) ( $this->spec[ $name ] )();
		} catch ( \Throwable $error ) {
			return false;
		}
	}
}
