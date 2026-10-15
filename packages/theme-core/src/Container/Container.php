<?php
/**
 * Dependency container.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Container;

/**
 * Small container with factories, singletons, instances and decorators.
 *
 * @api
 */
final class Container implements ContainerInterface {

	/**
	 * Registered factories: id => [factory, shared].
	 *
	 * @var array<string, array{0: callable, 1: bool}>
	 */
	private array $factories = array();

	/**
	 * Resolved shared instances.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	/**
	 * Decorators per id, applied in registration order.
	 *
	 * @var array<string, list<callable>>
	 */
	private array $extensions = array();

	/**
	 * Ids currently being resolved (circular dependency detection).
	 *
	 * @var list<string>
	 */
	private array $resolving = array();

	/**
	 * Registers a factory that builds a new service on every get().
	 *
	 * @param string   $id      Service id.
	 * @param callable $factory Receives the container.
	 */
	public function bind( string $id, callable $factory ): void {
		unset( $this->instances[ $id ] );
		$this->factories[ $id ] = array( $factory, false );
	}

	/**
	 * Registers a factory whose result is cached.
	 *
	 * @param string   $id      Service id.
	 * @param callable $factory Receives the container.
	 */
	public function singleton( string $id, callable $factory ): void {
		unset( $this->instances[ $id ] );
		$this->factories[ $id ] = array( $factory, true );
	}

	/**
	 * Registers a ready-made value.
	 *
	 * @param string $id    Service id.
	 * @param mixed  $value Value.
	 */
	public function instance( string $id, $value ): void {
		$this->instances[ $id ] = $value;
		$this->factories[ $id ] = array( static fn() => $value, true );
	}

	/**
	 * Adds a decorator called with the resolved service and the container; it must return the service.
	 *
	 * @param string   $id        Service id.
	 * @param callable $decorator Decorator.
	 */
	public function extend( string $id, callable $decorator ): void {
		$this->extensions[ $id ][] = $decorator;
		// Decorating an already resolved singleton re-resolves it on the next get().
		unset( $this->instances[ $id ] );
	}

	/**
	 * Whether the id is known.
	 *
	 * @param string $id Service id.
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] ) || array_key_exists( $id, $this->instances );
	}

	/**
	 * Returns the service for the id.
	 *
	 * @param string $id Service id.
	 * @return mixed
	 * @throws NotFoundException  Unknown id.
	 * @throws ContainerException Circular dependency or failing factory.
	 */
	public function get( string $id ) {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}
		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new NotFoundException( sprintf( 'Service "%s" is not registered.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( in_array( $id, $this->resolving, true ) ) {
			throw new ContainerException( sprintf( 'Circular dependency: %s', implode( ' -> ', array_merge( $this->resolving, array( $id ) ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$this->resolving[] = $id;
		try {
			list( $factory, $shared ) = $this->factories[ $id ];
			$service                  = $factory( $this );
			foreach ( $this->extensions[ $id ] ?? array() as $decorator ) {
				$service = $decorator( $service, $this );
			}
		} finally {
			array_pop( $this->resolving );
		}

		if ( $shared ) {
			$this->instances[ $id ] = $service;
		}
		return $service;
	}
}
