<?php
/**
 * Theme manifest value object.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Config;

/**
 * Parsed (and validated) joo-theme.json.
 *
 * @api
 */
final class Manifest {

	/**
	 * Manifest data.
	 *
	 * @var array<string, mixed>
	 */
	private array $data;

	/**
	 * Validation errors.
	 *
	 * @var list<string>
	 */
	private array $errors;

	/**
	 * Fallback slug.
	 *
	 * @var string
	 */
	private string $fallbackSlug;

	/**
	 * Creates a manifest.
	 *
	 * @param array<string, mixed> $data         Data.
	 * @param list<string>         $errors       Validation errors.
	 * @param string               $fallbackSlug Slug used when the manifest has none (theme directory name).
	 */
	public function __construct( array $data = array(), array $errors = array(), string $fallbackSlug = 'theme' ) {
		$this->data         = $data;
		$this->errors       = $errors;
		$this->fallbackSlug = $fallbackSlug;
	}

	/**
	 * Empty manifest used when loading failed.
	 *
	 * @param list<string> $errors       Why it is empty.
	 * @param string       $fallbackSlug Slug fallback.
	 */
	public static function invalid( array $errors, string $fallbackSlug = 'theme' ): self {
		return new self( array(), $errors, $fallbackSlug );
	}

	/**
	 * Whether the manifest is usable.
	 */
	public function isValid(): bool {
		return array() === $this->errors;
	}

	/**
	 * Validation errors.
	 *
	 * @return list<string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Reads a dotted path.
	 *
	 * @param string $path    Dotted path such as lazy.eagerCount.
	 * @param mixed  $default Value when the path does not exist.
	 * @return mixed
	 */
	public function get( string $path, $default = null ) {
		$node = $this->data;
		foreach ( explode( '.', $path ) as $part ) {
			if ( ! is_array( $node ) || ! array_key_exists( $part, $node ) ) {
				return $default;
			}
			$node = $node[ $part ];
		}
		return $node;
	}

	/**
	 * Whether a dotted path exists (even when its value is false or null).
	 *
	 * @param string $path Dotted path.
	 */
	public function has( string $path ): bool {
		$node = $this->data;
		foreach ( explode( '.', $path ) as $part ) {
			if ( ! is_array( $node ) || ! array_key_exists( $part, $node ) ) {
				return false;
			}
			$node = $node[ $part ];
		}
		return true;
	}

	/**
	 * Theme slug.
	 */
	public function slug(): string {
		$slug = $this->get( 'slug' );
		return is_string( $slug ) && '' !== $slug ? $slug : $this->fallbackSlug;
	}

	/**
	 * Theme name.
	 */
	public function name(): string {
		$name = $this->get( 'name' );
		return is_string( $name ) ? $name : $this->slug();
	}

	/**
	 * Required core version range ('*' when unknown).
	 */
	public function coreRange(): string {
		$range = $this->get( 'core' );
		return is_string( $range ) && '' !== $range ? $range : '*';
	}

	/**
	 * Module toggles declared by the theme.
	 *
	 * @return array<string, bool>
	 */
	public function modules(): array {
		$modules = $this->get( 'modules', array() );
		return is_array( $modules ) ? array_map( 'boolval', $modules ) : array();
	}

	/**
	 * Raw data.
	 *
	 * @return array<string, mixed>
	 */
	public function raw(): array {
		return $this->data;
	}
}
