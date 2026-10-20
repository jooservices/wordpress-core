<?php
/**
 * wp joo cache
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Admin\Cli;

use JOOservices\ThemeCore\Config\SettingsRepository;

/**
 * Cache maintenance.
 *
 * @internal
 */
final class CacheCommand {

	/**
	 * Settings repository.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $repository;

	/**
	 * Creates the command.
	 *
	 * @param SettingsRepository $repository Settings repository.
	 */
	public function __construct( SettingsRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Bumps the cache version so every cached fragment is rebuilt.
	 *
	 * @param array<int, string> $args Positional arguments.
	 */
	public function flush( array $args ): void {
		if ( ! CapabilityGuard::check() ) {
			return;
		}
		unset( $args );
		$this->repository->bumpCacheVersion();
		\WP_CLI::success( __( 'JOOservices caches flushed.', 'joo-core' ) );
	}
}
