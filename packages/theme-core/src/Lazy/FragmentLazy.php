<?php
/**
 * Deferred fragments.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Lazy;

use JOOservices\ThemeCore\Performance\FragmentCache;

/**
 * Wraps secondary content in <joo-lazy>: a placeholder, the real HTML in <noscript> and a REST URL the
 * runtime fetches when the element nears the viewport. Fragments are either rendered by a registered renderer
 * (the same function serves the inline and the REST output, so both are identical) or stored as a snapshot when
 * the page was rendered (template-bound blocks such as core/comments).
 *
 * @api
 */
final class FragmentLazy {

	/**
	 * Registered fragments: name => [renderer|null, schema, height].
	 *
	 * @var array<string, array{renderer: callable|null, schema: array<string, array<string, mixed>>, height: int}>
	 */
	private array $fragments = array();

	/**
	 * Policy.
	 *
	 * @var Policy
	 */
	private Policy $policy;

	/**
	 * Cache.
	 *
	 * @var FragmentCache
	 */
	private FragmentCache $cache;

	/**
	 * Creates the service.
	 *
	 * @param Policy        $policy Policy.
	 * @param FragmentCache $cache  Cache used for snapshots.
	 */
	public function __construct( Policy $policy, FragmentCache $cache ) {
		$this->policy = $policy;
		$this->cache  = $cache;
	}

	/**
	 * Registers a fragment.
	 *
	 * Schema entries: key => [type (int|string|bool|enum), default, min, max, maxLength, enum].
	 *
	 * @param string                                        $name     Fragment name ([a-z0-9-]+).
	 * @param callable|null                                 $renderer Receives the validated args, returns HTML. Null: snapshot mode.
	 * @param array<string, array<string, mixed>>           $schema   Argument schema.
	 * @param int                                           $height   Reserved height in pixels (prevents layout shift).
	 * @throws \InvalidArgumentException For invalid names.
	 */
	public function register( string $name, ?callable $renderer, array $schema = array(), int $height = 200 ): void {
		if ( 1 !== preg_match( '/^[a-z0-9-]+$/', $name ) ) {
			throw new \InvalidArgumentException( 'Fragment names use lowercase letters, digits and hyphens.' );
		}
		$this->fragments[ $name ] = array(
			'renderer' => $renderer,
			'schema'   => $schema,
			'height'   => $height,
		);
	}

	/**
	 * Whether a fragment exists.
	 *
	 * @param string $name Fragment name.
	 */
	public function has( string $name ): bool {
		return isset( $this->fragments[ $name ] );
	}

	/**
	 * Validates request parameters against a fragment schema.
	 *
	 * @param string               $name   Fragment name.
	 * @param array<string, mixed> $params Raw parameters.
	 * @return array<string, mixed>|\WP_Error Cleaned arguments.
	 */
	public function validate( string $name, array $params ) {
		$clean = array();
		foreach ( $this->fragments[ $name ]['schema'] ?? array() as $key => $rule ) {
			$value = $params[ $key ] ?? $rule['default'] ?? null;
			if ( null === $value ) {
				continue;
			}
			switch ( (string) ( $rule['type'] ?? 'string' ) ) {
				case 'int':
					if ( ! is_int( $value ) && ! ( is_string( $value ) && 1 === preg_match( '/^-?\d+$/', $value ) ) ) {
						return new \WP_Error( 'rest_invalid_param', sprintf( 'Parameter %s must be an integer.', $key ), array( 'status' => 400 ) );
					}
					$value = (int) $value;
					if ( ( isset( $rule['min'] ) && $value < $rule['min'] ) || ( isset( $rule['max'] ) && $value > $rule['max'] ) ) {
						return new \WP_Error( 'rest_invalid_param', sprintf( 'Parameter %s is out of range.', $key ), array( 'status' => 400 ) );
					}
					break;
				case 'bool':
					$value = in_array( $value, array( true, 1, '1', 'true', 'on' ), true );
					break;
				case 'enum':
					if ( ! in_array( (string) $value, (array) ( $rule['enum'] ?? array() ), true ) ) {
						return new \WP_Error( 'rest_invalid_param', sprintf( 'Parameter %s is not an allowed value.', $key ), array( 'status' => 400 ) );
					}
					$value = (string) $value;
					break;
				default:
					$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
					if ( isset( $rule['maxLength'] ) && mb_strlen( $value ) > $rule['maxLength'] ) {
						return new \WP_Error( 'rest_invalid_param', sprintf( 'Parameter %s is too long.', $key ), array( 'status' => 400 ) );
					}
			}
			$clean[ $key ] = $value;
		}
		return $clean;
	}

	/**
	 * HTML of a fragment for the REST endpoint (null when a snapshot is missing).
	 *
	 * @param string               $name Fragment name.
	 * @param array<string, mixed> $args Validated arguments.
	 */
	public function render( string $name, array $args ): ?string {
		$fragment = $this->fragments[ $name ] ?? null;
		if ( null === $fragment ) {
			return null;
		}
		if ( null === $fragment['renderer'] ) {
			$stored = $this->cache->peek( $this->snapshotKey( $name, $args ), array( 'posts' ), 'lazy' );
			return is_string( $stored ) ? $stored : null;
		}
		/** @var callable $renderer */
		$renderer = $fragment['renderer'];
		return (string) $this->policy->inFragment( static fn() => $renderer( $args ) );
	}

	/**
	 * Wraps content for deferred loading, or returns it unchanged when the policy says no.
	 *
	 * @param string               $name   Fragment name.
	 * @param array<string, mixed> $args   Arguments.
	 * @param callable             $inline Returns the inline HTML.
	 */
	public function wrap( string $name, array $args, callable $inline ): string {
		$html = (string) $inline();
		if ( '' === trim( $html ) ) {
			return '';
		}
		if ( ! $this->policy->decide( 'fragment' )->lazy ) {
			return $html;
		}
		$fragment = $this->fragments[ $name ] ?? null;
		if ( null !== $fragment && null === $fragment['renderer'] ) {
			$this->cache->put( $this->snapshotKey( $name, $args ), $html, DAY_IN_SECONDS, array( 'posts' ), 'lazy' );
		}
		return $this->markup( $name, $args, $html );
	}

	/**
	 * The <joo-lazy> markup.
	 *
	 * @param string               $name Fragment name.
	 * @param array<string, mixed> $args Arguments.
	 * @param string               $html Inline HTML used as the no-JS fallback.
	 */
	public function markup( string $name, array $args, string $html ): string {
		$height = (int) ( $this->fragments[ $name ]['height'] ?? 200 );
		$src    = add_query_arg( array_map( static fn( $v ) => is_bool( $v ) ? (int) $v : $v, $args ), rest_url( 'joo/v1/fragment/' . $name ) );
		wp_enqueue_script_module( 'joo-lazy' );
		return '<joo-lazy name="' . esc_attr( $name ) . '" data-src="' . esc_url( $src ) . '" style="contain-intrinsic-size:auto ' . $height . 'px">'
			. '<div class="joo-lazy__placeholder" aria-busy="true">' . esc_html__( 'Loading…', 'joo-core' ) . '</div>'
			. '<noscript>' . $html . '</noscript></joo-lazy>';
	}

	/**
	 * Cache key of a snapshot.
	 *
	 * @param string               $name Fragment name.
	 * @param array<string, mixed> $args Arguments.
	 */
	private function snapshotKey( string $name, array $args ): string {
		ksort( $args );
		return 'snapshot:' . $name . ':' . md5( (string) wp_json_encode( $args ) );
	}
}
