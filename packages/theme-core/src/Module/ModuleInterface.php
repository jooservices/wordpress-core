<?php
/**
 * Module contract.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Module;

use JOOservices\ThemeCore\Container\Container;

/**
 * A unit of core functionality that can be enabled or disabled.
 *
 * @api
 */
interface ModuleInterface {

	/**
	 * Unique module id (lowercase letters, digits, hyphens).
	 */
	public static function id(): string;

	/**
	 * Ids of modules that must be registered and booted first.
	 *
	 * @return list<string>
	 */
	public function dependencies(): array;

	/**
	 * Binds services. Must not add hooks, write options or print output.
	 *
	 * @param Container $container Service container.
	 */
	public function register( Container $container ): void;

	/**
	 * Adds hooks. Called after every module has been registered.
	 */
	public function boot(): void;
}
