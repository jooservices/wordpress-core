<?php
/**
 * Adapter registry.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat;

use JOOservices\ThemeCore\Compat\Adapters\AbstractSeoAdapter;
use JOOservices\ThemeCore\Compat\Adapters\CacheAdapter;
use JOOservices\ThemeCore\Compat\Adapters\JetpackAdapter;
use JOOservices\ThemeCore\Support\Hooks;
use JOOservices\ThemeCore\Support\Logger;

/**
 * Holds the adapters, boots the ones whose plugin is active (failures are isolated and logged) and reports state.
 *
 * @api
 */
final class AdapterRegistry {

	/**
	 * Adapters by key.
	 *
	 * @var array<string, AdapterInterface>
	 */
	private array $adapters = array();

	/**
	 * Booted adapter keys.
	 *
	 * @var list<string>
	 */
	private array $booted = array();

	/**
	 * Detector.
	 *
	 * @var Detector
	 */
	private Detector $detector;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Creates the registry.
	 *
	 * @param Detector                $detector Detector.
	 * @param Logger                  $logger   Logger.
	 * @param list<AdapterInterface> $adapters Built-in adapters.
	 */
	public function __construct( Detector $detector, Logger $logger, array $adapters ) {
		$this->detector = $detector;
		$this->logger   = $logger;
		foreach ( $adapters as $adapter ) {
			$this->adapters[ $adapter->key() ] = $adapter;
		}
	}

	/**
	 * Adds adapters from `joo/compat/adapters` (instances replace built-ins with the same key).
	 */
	public function collect(): void {
		$extra = Hooks::filter( 'joo/compat/adapters', array() );
		foreach ( is_array( $extra ) ? $extra : array() as $adapter ) {
			if ( $adapter instanceof AdapterInterface ) {
				$this->adapters[ $adapter->key() ] = $adapter;
			}
		}
	}

	/**
	 * Boots every supported adapter.
	 */
	public function bootAll(): void {
		$this->collect();
		foreach ( $this->adapters as $key => $adapter ) {
			try {
				if ( ! $adapter->supports() ) {
					continue;
				}
				$adapter->boot();
				$this->booted[] = $key;
			} catch ( \Throwable $error ) {
				$this->logger->error( 'Compat adapter ' . $key . ' failed: ' . $error->getMessage() );
			}
		}
	}

	/**
	 * Keys of booted adapters.
	 *
	 * @return list<string>
	 */
	public function booted(): array {
		return $this->booted;
	}

	/**
	 * One adapter.
	 *
	 * @param string $key Plugin key.
	 */
	public function get( string $key ): ?AdapterInterface {
		return $this->adapters[ $key ] ?? null;
	}

	/**
	 * Booted cache adapters.
	 *
	 * @return list<CacheAdapter>
	 */
	public function cacheAdapters(): array {
		$out = array();
		foreach ( $this->booted as $key ) {
			if ( $this->adapters[ $key ] instanceof CacheAdapter ) {
				$out[] = $this->adapters[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Booted SEO adapters.
	 *
	 * @return list<AbstractSeoAdapter>
	 */
	public function seoAdapters(): array {
		$out = array();
		foreach ( $this->booted as $key ) {
			if ( $this->adapters[ $key ] instanceof AbstractSeoAdapter ) {
				$out[] = $this->adapters[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Key of the first booted cache adapter that lazy-loads images.
	 */
	public function lazyProvider(): ?string {
		foreach ( $this->cacheAdapters() as $adapter ) {
			if ( $adapter->providesLazyload() ) {
				return $adapter->key();
			}
		}
		$jetpack = $this->adapters['jetpack'] ?? null;
		if ( $jetpack instanceof JetpackAdapter && in_array( 'jetpack', $this->booted, true ) && $jetpack->providesLazyload() ) {
			return 'jetpack';
		}
		return null;
	}

	/**
	 * Whether another plugin generates critical CSS.
	 */
	public function criticalCssProvided(): bool {
		foreach ( $this->cacheAdapters() as $adapter ) {
			if ( $adapter->providesCriticalCss() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Purges every booted cache plugin.
	 */
	public function purgeAll(): void {
		foreach ( $this->cacheAdapters() as $adapter ) {
			$adapter->purge();
		}
	}

	/**
	 * Rows for the Compatibility tab.
	 *
	 * @return list<array{label: string, version: string, provides: list<string>, tier: int, adapter: string, notice: string}>
	 */
	public function report(): array {
		$rows = array();
		foreach ( $this->detector->all() as $key => $plugin ) {
			$adapter = $this->adapters[ $key ] ?? null;
			$rows[]  = array(
				'label'    => $plugin['name'],
				'version'  => $plugin['version'],
				'provides' => $plugin['provides'],
				'tier'     => $plugin['tier'],
				'adapter'  => in_array( $key, $this->booted, true ) ? 'active' : ( null === $adapter ? 'none' : 'failed' ),
				'notice'   => null === $adapter ? '' : $adapter->notice(),
			);
		}
		return $rows;
	}
}
