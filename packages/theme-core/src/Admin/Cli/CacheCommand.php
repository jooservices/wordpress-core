<?php
/**
 * wp joo cache
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Admin\Cli;

/**
 * Cache maintenance.
 *
 * @internal
 */
final class CacheCommand {

	/**
	 * Bumps the cache version so every cached fragment is rebuilt.
	 *
	 * @param array<int, string> $args Positional arguments.
	 */
	public function flush( array $args ): void {
		unset( $args );
		update_option( 'joo_cache_version', time(), true );
		\WP_CLI::success( 'JOOservices caches flushed.' );
	}
}
