<?php
/**
 * Conditional registration of script modules and styles.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Assets;

use JOOservices\ThemeCore\Support\Hooks;
use JOOservices\ThemeCore\Support\Logger;

/**
 * Modules call module() / style() once; the registry registers everything with WordPress and enqueues only
 * what the current request context needs. Hashed file names make URLs cache-safe (no ver query string).
 *
 * @api
 */
final class AssetRegistry {

	/**
	 * Context names understood by the `when` option.
	 */
	public const CONTEXTS = array( 'any', 'home', 'front', 'single', 'page', 'singular', 'archive', 'search', '404' );

	/**
	 * Manifests by name.
	 *
	 * @var array<string, AssetManifest>
	 */
	private array $manifests = array();

	/**
	 * Queued definitions.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $queue = array();

	/**
	 * Enqueued handles (for diagnostics).
	 *
	 * @var array{js: list<string>, css: list<string>}
	 */
	private array $enqueued = array(
		'js'  => array(),
		'css' => array(),
	);

	/**
	 * Styles to print late (footer), by handle.
	 *
	 * @var list<string>
	 */
	private array $deferredStyles = array();

	/**
	 * Tells whether critical CSS is inlined for this request (styles flagged defer then print in the footer).
	 *
	 * @var callable|null
	 */
	private $criticalProbe = null;

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
	 * Sets the probe that reports whether critical CSS is inlined.
	 *
	 * @param callable $probe Returns bool.
	 */
	public function setCriticalProbe( callable $probe ): void {
		$this->criticalProbe = $probe;
	}

	/**
	 * Adds a build manifest.
	 *
	 * @param string        $name     Manifest name ('core' is added by core, themes add their own).
	 * @param AssetManifest $manifest Manifest.
	 */
	public function addManifest( string $name, AssetManifest $manifest ): void {
		$this->manifests[ $name ] = $manifest;
	}

	/**
	 * A registered build manifest (null when unknown).
	 *
	 * @param string $name Manifest name.
	 */
	public function manifest( string $name = 'core' ): ?AssetManifest {
		return $this->manifests[ $name ] ?? null;
	}

	/**
	 * Queues a script module.
	 *
	 * Options: when (context name or callable, default 'any'), manifest (name, default 'core'),
	 * lazy (true: registered and exposed to the runtime config but never enqueued), deps (handles).
	 *
	 * @param string               $handle  Module id (also its import map id).
	 * @param string               $logical Logical asset name from assets.json.
	 * @param array<string, mixed> $options Options.
	 */
	public function module( string $handle, string $logical, array $options = array() ): void {
		$this->queue[] = array(
			'type'    => 'js',
			'handle'  => $handle,
			'logical' => $logical,
			'options' => $options,
		);
	}

	/**
	 * Queues a stylesheet.
	 *
	 * Options: when, manifest, deps, preload (bool: print a preload link), defer (bool: print in the footer,
	 * used when critical CSS is inlined), late (bool: enqueue after WordPress' global styles so theme rules win
	 * ties of equal specificity), media.
	 *
	 * @param string               $handle  Style handle.
	 * @param string               $logical Logical asset name.
	 * @param array<string, mixed> $options Options.
	 */
	public function style( string $handle, string $logical, array $options = array() ): void {
		$this->queue[] = array(
			'type'    => 'css',
			'handle'  => $handle,
			'logical' => $logical,
			'options' => $options,
		);
	}

	/**
	 * Hooks the registration into the front end.
	 */
	public function hook(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'process' ), 1 );
		add_action( 'wp_footer', array( $this, 'printDeferredStyles' ), 5 );
	}

	/**
	 * Registers and enqueues the queued assets.
	 */
	public function process(): void {
		Hooks::action( 'joo/assets/register', $this );
		$critical = null !== $this->criticalProbe && (bool) ( $this->criticalProbe )();
		foreach ( $this->queue as $item ) {
			$options  = (array) $item['options'];
			$manifest = $this->manifests[ (string) ( $options['manifest'] ?? 'core' ) ] ?? null;
			$logical  = (string) $item['logical'];
			if ( null === $manifest || ! $manifest->has( $logical ) ) {
				$this->logger->warning( sprintf( 'Asset "%s" is not in the build manifest; skipped (run the build).', $logical ) );
				continue;
			}
			$url    = $manifest->url( $logical );
			$handle = (string) $item['handle'];
			$deps   = array_values( array_map( 'strval', (array) ( $options['deps'] ?? array() ) ) );
			$run    = $this->matches( $options['when'] ?? 'any' );
			if ( 'js' === $item['type'] ) {
				wp_register_script_module(
					$handle,
					$url,
					array_map(
						static fn( string $dependency ): array => array(
							'id'     => $dependency,
							'import' => 'static',
						),
						$deps
					),
					null // The file name is content-hashed: no ?ver= query string.
				);
				if ( $run && empty( $options['lazy'] ) ) {
					wp_enqueue_script_module( $handle );
					$this->enqueued['js'][] = $handle;
				}
				continue;
			}
			wp_register_style( $handle, $url, $deps, null, (string) ( $options['media'] ?? 'all' ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the file name is content-hashed.
			if ( ! $run ) {
				continue;
			}
			if ( ! empty( $options['defer'] ) && $critical ) {
				$this->deferredStyles[] = $handle;
				continue;
			}
			if ( ! empty( $options['late'] ) ) {
				// After WordPress' global styles (priority 1), so a theme rule wins a tie of equal specificity.
				add_action(
					'wp_enqueue_scripts',
					static function () use ( $handle ): void {
						wp_enqueue_style( $handle );
					},
					20
				);
				$this->enqueued['css'][] = $handle;
				continue;
			}
			wp_enqueue_style( $handle );
			$this->enqueued['css'][] = $handle;
		}
	}

	/**
	 * Enqueues deferred styles in the footer (printed as late styles).
	 */
	public function printDeferredStyles(): void {
		foreach ( $this->deferredStyles as $handle ) {
			wp_enqueue_style( $handle );
			$this->enqueued['css'][] = $handle;
		}
		$this->deferredStyles = array();
	}

	/**
	 * Logical name => URL for modules flagged lazy (the runtime can import() them on demand).
	 *
	 * @return array<string, string>
	 */
	public function lazyModules(): array {
		$out = array();
		foreach ( $this->queue as $item ) {
			$options = (array) $item['options'];
			if ( 'js' !== $item['type'] || empty( $options['lazy'] ) ) {
				continue;
			}
			$manifest = $this->manifests[ (string) ( $options['manifest'] ?? 'core' ) ] ?? null;
			if ( null !== $manifest && $manifest->has( (string) $item['logical'] ) ) {
				$out[ (string) $item['logical'] ] = $manifest->url( (string) $item['logical'] );
			}
		}
		return $out;
	}

	/**
	 * Handles enqueued during this request.
	 *
	 * @return array{js: list<string>, css: list<string>}
	 */
	public function debugList(): array {
		return $this->enqueued;
	}

	/**
	 * Preload URLs of styles flagged preload that are enqueued for this request.
	 *
	 * @return list<string>
	 */
	public function preloadStyles(): array {
		$out = array();
		foreach ( $this->queue as $item ) {
			$options = (array) $item['options'];
			if ( 'css' !== $item['type'] || empty( $options['preload'] ) || ! $this->matches( $options['when'] ?? 'any' ) ) {
				continue;
			}
			$manifest = $this->manifests[ (string) ( $options['manifest'] ?? 'core' ) ] ?? null;
			if ( null !== $manifest && $manifest->has( (string) $item['logical'] ) ) {
				$out[] = $manifest->url( (string) $item['logical'] );
			}
		}
		return $out;
	}

	/**
	 * Whether a context name or callable matches the current request.
	 *
	 * @param mixed $when Context name or callable.
	 */
	public function matches( $when ): bool {
		if ( is_callable( $when ) ) {
			return (bool) $when();
		}
		switch ( (string) $when ) {
			case 'home':
				return is_home();
			case 'front':
				return is_front_page();
			case 'single':
				return is_single();
			case 'page':
				return is_page();
			case 'singular':
				return is_singular();
			case 'archive':
				return is_archive();
			case 'search':
				return is_search();
			case '404':
				return is_404();
			default:
				return true;
		}
	}
}
