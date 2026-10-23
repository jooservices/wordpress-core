<?php
/**
 * Minimal PSR-11 compatible container contract.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Container;

/**
 * Same method signatures as Psr\Container\ContainerInterface so a PSR-11 swap is trivial.
 *
 * @internal
 */
interface ContainerInterface {

	/**
	 * Returns the service for an id.
	 *
	 * @param string $id Service id.
	 * @return mixed
	 * @throws NotFoundException When the id is unknown.
	 */
	public function get( string $id );

	/**
	 * Whether the container can return a service for the id.
	 *
	 * @param string $id Service id.
	 */
	public function has( string $id ): bool;
}
