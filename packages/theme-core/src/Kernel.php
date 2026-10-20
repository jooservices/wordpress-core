<?php
/**
 * Core kernel: the single entry point for themes.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore;

use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\ManifestLoader;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Config\SettingsRepository;
use JOOservices\ThemeCore\Config\SettingsSchema;
use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Module\ModuleRegistry;
use JOOservices\ThemeCore\Support\Hooks;
use JOOservices\ThemeCore\Support\Logger;
use JOOservices\ThemeCore\Support\Requirements;
use JOOservices\ThemeCore\Support\Version;

/**
 * Boots core from a theme: requirements, manifest, container, modules.
 *
 * @api
 */
final class Kernel {

	/**
	 * Built-in module classes. Classes that do not exist yet are skipped.
	 *
	 * @var list<string>
	 */
	public const BUILTIN_MODULES = array(
		'JOOservices\\ThemeCore\\Assets\\AssetsModule',
		'JOOservices\\ThemeCore\\Admin\\AdminModule',
		'JOOservices\\ThemeCore\\Performance\\PerformanceModule',
		'JOOservices\\ThemeCore\\Lazy\\LazyModule',
		'JOOservices\\ThemeCore\\Compat\\CompatModule',
		'JOOservices\\ThemeCore\\I18n\\I18nModule',
		'JOOservices\\ThemeCore\\Content\\ContentModule',
		'JOOservices\\ThemeCore\\Seo\\SeoModule',
		'JOOservices\\ThemeCore\\Blocks\\BlocksModule',
		'JOOservices\\ThemeCore\\Terminal\\TerminalModule',
	);

	/**
	 * Service ids themes may not replace through joo/core/services.
	 *
	 * @var list<string>
	 */
	private const PROTECTED_SERVICES = array( Container::class, ModuleRegistry::class, self::class );

	/**
	 * Running instance.
	 *
	 * @var Kernel|null
	 */
	private static ?Kernel $instance = null;

	/**
	 * Whether boot() has been called.
	 *
	 * @var bool
	 */
	private static bool $attempted = false;

	/**
	 * Container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Module registry.
	 *
	 * @var ModuleRegistry|null
	 */
	private ?ModuleRegistry $registry = null;

	/**
	 * Private: use Kernel::boot().
	 */
	private function __construct() {
		$this->container = new Container();
	}

	/**
	 * Boots core. Idempotent: later calls do nothing.
	 *
	 * @param string               $manifestPath Absolute path to the theme's joo-theme.json.
	 * @param array<string, mixed> $options      Reserved for future options.
	 * @throws \Throwable Only when WP_DEBUG is on and JOO_CORE_THROW is true (tests).
	 */
	public static function boot( string $manifestPath, array $options = array() ): void {
		unset( $options );
		if ( self::$attempted ) {
			return;
		}
		self::$attempted = true;
		try {
			$kernel = new self();
			$kernel->start( $manifestPath );
		} catch ( \Throwable $error ) {
			self::$instance = null;
			Notices::add( 'error', 'Core failed to start: ' . $error->getMessage(), 'kernel-failure' );
			if ( defined( 'JOO_CORE_THROW' ) && JOO_CORE_THROW ) {
				throw $error;
			}
		}
	}

	/**
	 * Service container (null when core did not boot).
	 */
	public static function container(): ?Container {
		return self::$instance ? self::$instance->container : null;
	}

	/**
	 * Module status map (empty when core did not boot).
	 *
	 * @return array<string, string>
	 */
	public static function status(): array {
		return self::$instance && null !== self::$instance->registry ? self::$instance->registry->status() : array();
	}

	/**
	 * Module registry for diagnostics.
	 *
	 * @internal
	 */
	public static function registry(): ?ModuleRegistry {
		return self::$instance ? self::$instance->registry : null;
	}

	/**
	 * Forgets the running instance (tests only).
	 *
	 * @internal
	 */
	public static function reset(): void {
		self::$instance  = null;
		self::$attempted = false;
	}

	/**
	 * Performs the boot.
	 *
	 * @param string $manifestPath Manifest path.
	 */
	private function start( string $manifestPath ): void {
		Notices::register();
		if ( defined( 'JOO_CORE_UNSUPPORTED' ) ) {
			return;
		}
		if ( defined( 'JOO_CORE_DUPLICATE' ) ) {
			Notices::add( 'warning', sprintf( 'A second copy of core was found at %1$s; version %2$s is in use.', (string) JOO_CORE_DUPLICATE, Version::core() ), 'duplicate-core' );
		}
		$requirements = Requirements::check();
		if ( $requirements ) {
			$messages = array_column( $requirements, 'message' );
			$this->registerEarlySiteHealthTest( 'joo_core_requirements', 'JOOservices core requirements', 'JOOservices core requirements are not met', implode( ' ', $messages ) );
		}
		foreach ( $requirements as $problem ) {
			Notices::add( 'error', $problem['message'], 'requirement-' . $problem['code'] );
			return;
		}

		$manifest = ( new ManifestLoader() )->load( $manifestPath );
		if ( ! $manifest->isValid() ) {
			$message = 'The theme manifest is invalid: ' . implode( '; ', array_slice( $manifest->errors(), 0, 3 ) );
			Notices::add( 'error', $message, 'manifest-invalid' );
			$this->registerEarlySiteHealthTest( 'joo_manifest_valid', 'JOOservices theme manifest', 'The theme manifest is invalid', $message );
			return;
		}
		if ( ! Version::satisfies( Version::core(), $manifest->coreRange() ) ) {
			$message = sprintf( 'Theme "%1$s" requires JOOservices core %2$s; the bundled version is %3$s.', $manifest->name(), $manifest->coreRange(), Version::core() );
			Notices::add( 'error', $message, 'core-incompatible' );
			// Site Health shows it as a critical entry (the kernel did not boot, so the Site Health service does not exist).
			$this->registerEarlySiteHealthTest( 'joo_core_incompatible', 'JOOservices core version', 'The theme needs a newer JOOservices core', $message );
			return;
		}

		self::$instance = $this;
		$this->buildContainer( $manifest );
		$this->loadModules();
	}

	/**
	 * Binds the core services.
	 *
	 * @param Manifest $manifest Manifest (possibly empty).
	 */
	private function buildContainer( Manifest $manifest ): void {
		$c = $this->container;
		$c->instance( self::class, $this );
		$c->instance( Container::class, $c );
		$c->instance( Manifest::class, $manifest );
		$c->singleton(
			Logger::class,
			static fn( Container $c ) => new Logger(
				static fn(): bool => ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) || (bool) $c->get( Settings::class )->get( 'core.debug', false )
			)
		);
		$c->singleton( SettingsSchema::class, static fn() => new SettingsSchema() );
		$c->singleton( SettingsRepository::class, static fn( Container $c ) => new SettingsRepository( $c->get( Manifest::class )->slug(), $c->get( SettingsSchema::class ) ) );
		$c->singleton( Settings::class, static fn( Container $c ) => new Settings( $c->get( SettingsSchema::class ), $c->get( Manifest::class ), $c->get( SettingsRepository::class ) ) );
		/** @var Logger $logger */
		$logger         = $c->get( Logger::class );
		$this->registry = new ModuleRegistry( $logger );
		$c->instance( ModuleRegistry::class, $this->registry );

		$services = Hooks::filter( 'joo/core/services', array() );
		if ( is_array( $services ) ) {
			foreach ( $services as $id => $factory ) {
				if ( is_string( $id ) && is_callable( $factory ) && ! in_array( $id, self::PROTECTED_SERVICES, true ) ) {
					$c->singleton( $id, $factory );
				}
			}
		}
	}

	/**
	 * Collects, orders, registers and schedules the modules.
	 */
	private function loadModules(): void {
		$registry = $this->registry;
		if ( null === $registry ) {
			return;
		}
		/** @var Logger $logger */
		$logger  = $this->container->get( Logger::class );
		$classes = Hooks::filter( 'joo/core/modules', self::BUILTIN_MODULES );
		$classes = is_array( $classes ) ? $classes : array();

		foreach ( $classes as $class ) {
			if ( is_string( $class ) && ! class_exists( $class ) ) {
				continue; // Module not shipped in this core version.
			}
			try {
				$registry->add( $class );
			} catch ( \Throwable $error ) {
				$logger->error( 'Module rejected: ' . $error->getMessage() );
			}
		}

		/** @var Settings $settings */
		$settings = $this->container->get( Settings::class );
		$enabled  = array();
		foreach ( $registry->ids() as $id ) {
			$key = 'modules.' . $id . '.enabled';
			if ( null === $settings->schema()->get( $key ) || (bool) $settings->get( $key, true ) ) {
				$enabled[] = $id;
			}
		}

		try {
			$registry->resolve( $enabled );
		} catch ( \Throwable $error ) {
			$logger->error( 'Module resolution failed: ' . $error->getMessage() );
			Notices::add( 'error', 'Modules could not be ordered: ' . $error->getMessage(), 'module-resolution' );
			return;
		}
		$registry->registerAll( $this->container );

		$boot = function (): void {
			$this->registry?->bootAll();
			Hooks::action( 'joo/core/ready', $this->container );
		};
		if ( did_action( 'after_setup_theme' ) ) {
			$boot();
		} else {
			add_action( 'after_setup_theme', $boot, 5 );
		}
	}

	/**
	 * Registers a critical Site Health result for failures before the container is available.
	 *
	 * @param string $id          Test id.
	 * @param string $label       Test label.
	 * @param string $resultLabel Result label.
	 * @param string $description Result description.
	 */
	private function registerEarlySiteHealthTest( string $id, string $label, string $resultLabel, string $description ): void {
		add_filter(
			'site_status_tests',
			static function ( $tests ) use ( $id, $label, $resultLabel, $description ) {
				$tests = is_array( $tests ) ? $tests : array();
				if ( ! isset( $tests['direct'] ) || ! is_array( $tests['direct'] ) ) {
					$tests['direct'] = array();
				}
				$tests['direct'][ $id ] = array(
					'label' => $label,
					'test'  => static fn(): array => array(
						'label'       => $resultLabel,
						'status'      => 'critical',
						'badge'       => array(
							'label' => __( 'JOOservices', 'joo-core' ),
							'color' => 'red',
						),
						'description' => '<p>' . esc_html( $description ) . '</p>',
						'actions'     => '',
						'test'        => $id,
					),
				);
				return $tests;
			}
		);
	}
}
