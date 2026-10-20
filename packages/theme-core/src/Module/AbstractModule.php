<?php
/**
 * Base class for modules.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Module;

use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Config\Settings;

/**
 * Provides empty defaults and access to the container and settings.
 *
 * @internal
 */
abstract class AbstractModule implements ModuleInterface {

	/**
	 * Container injected by the registry before register().
	 *
	 * @var Container|null
	 */
	protected ?Container $container = null;

	/**
	 * Injects the container.
	 *
	 * @param Container $container Container.
	 */
	public function setContainer( Container $container ): void {
		$this->container = $container;
	}

	/**
	 * Default: no dependencies.
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * Default: nothing to register.
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		unset( $container );
	}

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Boots once: a second call does nothing (so hooks are never added twice).
	 */
	final public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;
		$this->doBoot();
	}

	/**
	 * Adds hooks and assets. Default: nothing to boot.
	 */
	protected function doBoot(): void {
	}

	/**
	 * Effective settings.
	 *
	 * @throws \LogicException When called before the container is injected.
	 */
	protected function settings(): Settings {
		if ( null === $this->container ) {
			throw new \LogicException( 'Module container is not set.' );
		}
		/** @var Settings $settings */
		$settings = $this->container->get( Settings::class );
		return $settings;
	}
}
