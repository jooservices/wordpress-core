<?php
/**
 * Module registry: ordering, lifecycle and failure isolation.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Module;

use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Support\Logger;

/**
 * Registers, orders and boots modules. A failing module never stops the others.
 *
 * @internal
 */
final class ModuleRegistry {

	public const STATUS_DISABLED   = 'disabled';
	public const STATUS_REGISTERED = 'registered';
	public const STATUS_BOOTED     = 'booted';
	public const STATUS_FAILED     = 'failed';
	public const STATUS_BLOCKED    = 'blocked';

	/**
	 * Module instances by id.
	 *
	 * @var array<string, ModuleInterface>
	 */
	private array $modules = array();

	/**
	 * Status per module id.
	 *
	 * @var array<string, string>
	 */
	private array $status = array();

	/**
	 * Failure messages per module id.
	 *
	 * @var array<string, string>
	 */
	private array $messages = array();

	/**
	 * Resolved boot order.
	 *
	 * @var list<string>
	 */
	private array $order = array();

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Creates the registry.
	 *
	 * @param Logger $logger Logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Adds a module class or instance.
	 *
	 * @param class-string<ModuleInterface>|ModuleInterface $module Module class or instance.
	 * @throws \InvalidArgumentException For invalid or duplicate modules.
	 */
	public function add( $module ): void {
		if ( is_string( $module ) ) {
			if ( ! class_exists( $module ) || ! is_subclass_of( $module, ModuleInterface::class ) ) {
				throw new \InvalidArgumentException( sprintf( '%s does not implement ModuleInterface.', $module ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$module = new $module();
		}
		$id = $module::id();
		if ( 1 !== preg_match( '/^[a-z][a-z0-9-]*$/', $id ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid module id "%s".', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( isset( $this->modules[ $id ] ) ) {
			throw new \InvalidArgumentException( sprintf( 'Duplicate module id "%s".', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$this->modules[ $id ] = $module;
		$this->status[ $id ]  = self::STATUS_DISABLED;
	}

	/**
	 * Ids of all added modules.
	 *
	 * @return list<string>
	 */
	public function ids(): array {
		return array_keys( $this->modules );
	}

	/**
	 * Selects enabled modules and computes the boot order (Kahn's algorithm).
	 *
	 * @param list<string> $enabled Enabled module ids.
	 * @return list<string> Boot order.
	 * @throws \RuntimeException On dependency cycles.
	 */
	public function resolve( array $enabled ): array {
		$selected = array_values( array_intersect( array_keys( $this->modules ), $enabled ) );

		// Drop modules with missing or disabled dependencies (repeat until stable).
		do {
			$dropped = false;
			foreach ( $selected as $index => $id ) {
				foreach ( $this->modules[ $id ]->dependencies() as $dependency ) {
					if ( ! in_array( $dependency, $selected, true ) ) {
						$this->status[ $id ]   = self::STATUS_BLOCKED;
						$this->messages[ $id ] = sprintf( 'Missing or disabled dependency "%s".', $dependency );
						$this->logger->warning( sprintf( 'Module "%s" skipped: %s', $id, $this->messages[ $id ] ) );
						unset( $selected[ $index ] );
						$dropped = true;
						break;
					}
				}
			}
			$selected = array_values( $selected );
		} while ( $dropped );

		$incoming = array();
		foreach ( $selected as $id ) {
			$incoming[ $id ] = count( $this->modules[ $id ]->dependencies() );
		}
		$queue = array_keys( array_filter( $incoming, static fn( int $count ) => 0 === $count ) );
		sort( $queue );
		$order = array();
		while ( $queue ) {
			$id      = array_shift( $queue );
			$order[] = $id;
			$ready   = array();
			foreach ( $selected as $other ) {
				if ( in_array( $id, $this->modules[ $other ]->dependencies(), true ) && 0 === --$incoming[ $other ] ) {
					$ready[] = $other;
				}
			}
			sort( $ready );
			$queue = array_merge( $queue, $ready );
		}
		if ( count( $order ) !== count( $selected ) ) {
			$cycle = implode( ', ', array_diff( $selected, $order ) );
			throw new \RuntimeException( 'Module dependency cycle involving: ' . $cycle ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$this->order = $order;
		return $order;
	}

	/**
	 * Registers every resolved module; failures are isolated.
	 *
	 * @param Container $container Container.
	 */
	public function registerAll( Container $container ): void {
		foreach ( $this->order as $id ) {
			$module = $this->modules[ $id ];
			if ( $module instanceof AbstractModule ) {
				$module->setContainer( $container );
			}
			try {
				$module->register( $container );
				$this->status[ $id ] = self::STATUS_REGISTERED;
			} catch ( \Throwable $error ) {
				$this->fail( $id, 'register', $error );
			}
		}
	}

	/**
	 * Boots every registered module; dependents of a failed module are blocked.
	 */
	public function bootAll(): void {
		foreach ( $this->order as $id ) {
			if ( self::STATUS_REGISTERED !== $this->status[ $id ] ) {
				continue;
			}
			$blocked = false;
			foreach ( $this->modules[ $id ]->dependencies() as $dependency ) {
				if ( in_array( $this->status[ $dependency ] ?? '', array( self::STATUS_FAILED, self::STATUS_BLOCKED ), true ) ) {
					$this->status[ $id ]   = self::STATUS_BLOCKED;
					$this->messages[ $id ] = sprintf( 'Dependency "%s" failed.', $dependency );
					$blocked               = true;
					break;
				}
			}
			if ( $blocked ) {
				continue;
			}
			try {
				$this->modules[ $id ]->boot();
				$this->status[ $id ] = self::STATUS_BOOTED;
			} catch ( \Throwable $error ) {
				$this->fail( $id, 'boot', $error );
			}
		}
	}

	/**
	 * Status per module id.
	 *
	 * @return array<string, string>
	 */
	public function status(): array {
		return $this->status;
	}

	/**
	 * Failure or block message for a module.
	 *
	 * @param string $id Module id.
	 */
	public function message( string $id ): string {
		return $this->messages[ $id ] ?? '';
	}

	/**
	 * Records a failure.
	 *
	 * @param string     $id    Module id.
	 * @param string     $phase register or boot.
	 * @param \Throwable $error Error.
	 */
	private function fail( string $id, string $phase, \Throwable $error ): void {
		$this->status[ $id ]   = self::STATUS_FAILED;
		$this->messages[ $id ] = sprintf( '%s in %s(): %s', get_class( $error ), $phase, $error->getMessage() );
		$this->logger->error( sprintf( 'Module "%s" failed: %s', $id, $this->messages[ $id ] ) );
	}
}
