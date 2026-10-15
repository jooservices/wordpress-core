<?php
/**
 * Contract every core module must satisfy.
 *
 * @package JOOservices\TestKit
 */

declare(strict_types=1);

namespace JOOservices\TestKit;

use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Kernel;
use JOOservices\ThemeCore\Module\AbstractModule;
use JOOservices\ThemeCore\Module\ModuleInterface;
use JOOservices\ThemeCore\Module\ModuleRegistry;
use JOOservices\ThemeCore\Support\Logger;

/**
 * Pure checks returning violations (empty = contract met), so the same rules test good and deliberately bad modules.
 * Static checks read the module source; dynamic checks need WordPress.
 */
final class ModuleContract {

	/**
	 * Static checks: id format, source settings keys and hook names.
	 *
	 * @param string       $class      Module class.
	 * @param list<string> $schemaKeys Setting keys of settings.schema.json.
	 * @param list<string> $hooks      Hook patterns of the naming registry.
	 * @return list<string>
	 */
	public static function staticViolations( string $class, array $schemaKeys, array $hooks ): array {
		$out = array();
		if ( ! class_exists( $class ) || ! is_subclass_of( $class, ModuleInterface::class ) ) {
			return array( $class . ' is not a module class' );
		}
		$id = $class::id();
		if ( 1 !== preg_match( '/^[a-z][a-z0-9-]*$/', $id ) ) {
			$out[] = sprintf( 'id "%s" is not lowercase letters, digits and hyphens', $id );
		}
		if ( ! is_subclass_of( $class, AbstractModule::class ) ) {
			$out[] = 'module does not extend AbstractModule (boot() would not be idempotent)';
		} elseif ( ( new \ReflectionMethod( $class, 'boot' ) )->getDeclaringClass()->getName() !== AbstractModule::class ) {
			$out[] = 'module overrides boot(); override doBoot() instead';
		}
		$dir = dirname( (string) ( new \ReflectionClass( $class ) )->getFileName() );
		foreach ( self::phpFiles( $dir ) as $file ) {
			$source = (string) file_get_contents( $file );
			if ( preg_match_all( "/settings(?:\\(\\))?->get\\(\\s*'([a-z0-9_]+\\.[a-z0-9_.]+)'/", $source, $m ) ) {
				foreach ( array_unique( $m[1] ) as $key ) {
					if ( ! str_ends_with( $key, '.' ) && ! in_array( $key, $schemaKeys, true ) ) {
						$out[] = sprintf( 'setting "%s" (%s) is not in settings.schema.json', $key, basename( $file ) );
					}
				}
			}
			// Hooks have at least three segments (joo/module/event); block names (joo/slug) and the REST namespace (joo/v1) are not hooks.
			if ( preg_match_all( "/'(joo\\/(?!v1\\b)[a-z0-9_-]+\\/[a-z0-9_\\/-]*[a-z0-9_-])'/", $source, $h ) ) {
				foreach ( array_unique( $h[1] ) as $hook ) {
					if ( ! self::hookKnown( $hook, $hooks ) ) {
						$out[] = sprintf( 'hook "%s" (%s) is not in the naming registry', $hook, basename( $file ) );
					}
				}
			}
		}
		return $out;
	}

	/**
	 * Dependencies exist and have no cycle (checked by resolving all given modules).
	 *
	 * @param list<string> $classes All module classes.
	 * @return list<string>
	 */
	public static function dependencyViolations( array $classes ): array {
		$registry = new ModuleRegistry( new Logger( static fn() => false ) );
		$ids      = array();
		try {
			foreach ( $classes as $class ) {
				$registry->add( $class );
				$ids[] = $class::id();
			}
			if ( count( $ids ) !== count( array_unique( $ids ) ) ) {
				return array( 'duplicate module ids: ' . implode( ', ', array_diff_assoc( $ids, array_unique( $ids ) ) ) );
			}
			$registry->resolve( $ids );
		} catch ( \Throwable $error ) {
			return array( $error->getMessage() );
		}
		return array();
	}

	/**
	 * register() must be free of side effects: no hooks added, no options written.
	 *
	 * @param string $class Module class.
	 * @return list<string>
	 */
	public static function registerViolations( string $class ): array {
		$before  = self::hookSnapshot();
		$writes  = 0;
		$counter = static function () use ( &$writes ): void {
			++$writes;
		};
		add_action( 'added_option', $counter );
		add_action( 'updated_option', $counter );
		$out = array();
		try {
			/** @var AbstractModule $module */
			$module = new $class();
			if ( $module instanceof AbstractModule ) {
				$module->setContainer( new Container() );
			}
			$module->register( new Container() );
		} catch ( \Throwable $error ) {
			$out[] = 'register() threw: ' . $error->getMessage();
		}
		remove_action( 'added_option', $counter );
		remove_action( 'updated_option', $counter );
		$after = self::hookSnapshot();
		foreach ( $after as $hook => $count ) {
			if ( ( $before[ $hook ] ?? 0 ) !== $count ) {
				$out[] = sprintf( 'register() added callbacks to "%s"', $hook );
			}
		}
		if ( $writes > 0 ) {
			$out[] = 'register() wrote options';
		}
		return $out;
	}

	/**
	 * boot() twice must add nothing the second time.
	 *
	 * @param ModuleInterface $module A booted module.
	 * @return list<string>
	 */
	public static function bootIdempotenceViolations( ModuleInterface $module ): array {
		$before = self::hookSnapshot();
		$module->boot();
		$out = array();
		foreach ( self::hookSnapshot() as $hook => $count ) {
			if ( ( $before[ $hook ] ?? 0 ) !== $count ) {
				$out[] = sprintf( 'a second boot() added callbacks to "%s"', $hook );
			}
		}
		return $out;
	}

	/**
	 * Boots the kernel with one module disabled; nothing may fail.
	 *
	 * @param string $manifest Manifest file.
	 * @param string $id       Module id to disable.
	 * @return list<string>
	 */
	public static function disabledViolations( string $manifest, string $id ): array {
		Kernel::reset();
		$filter = static fn( $value, $key ) => 'modules.' . $id . '.enabled' === $key ? false : $value;
		add_filter( 'joo/settings/value', $filter, 10, 2 );
		$out    = array();
		try {
			Kernel::boot( $manifest );
			do_action( 'after_setup_theme' );
			$registry = Kernel::registry();
			foreach ( null === $registry ? array() : $registry->status() as $module => $status ) {
				if ( ModuleRegistry::STATUS_FAILED === $status ) {
					$out[] = sprintf( 'module "%s" failed when "%s" is disabled', $module, $id );
				}
			}
		} catch ( \Throwable $error ) {
			$out[] = 'boot threw: ' . $error->getMessage();
		}
		remove_filter( 'joo/settings/value', $filter, 10 );
		Kernel::reset();
		return $out;
	}

	/**
	 * Count of callbacks per hook.
	 *
	 * @return array<string, int>
	 */
	private static function hookSnapshot(): array {
		global $wp_filter;
		$out = array();
		foreach ( (array) $wp_filter as $hook => $object ) {
			$count = 0;
			foreach ( $object->callbacks ?? array() as $callbacks ) {
				$count += count( $callbacks );
			}
			$out[ (string) $hook ] = $count;
		}
		return $out;
	}

	/**
	 * PHP files below a directory.
	 *
	 * @param string $dir Directory.
	 * @return list<string>
	 */
	private static function phpFiles( string $dir ): array {
		$files = array();
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			if ( str_ends_with( $file->getPathname(), '.php' ) ) {
				$files[] = $file->getPathname();
			}
		}
		return $files;
	}

	/**
	 * Whether a hook name is in the registry (patterns use {placeholder}).
	 *
	 * @param string       $hook  Hook.
	 * @param list<string> $known Registry hooks.
	 */
	private static function hookKnown( string $hook, array $known ): bool {
		foreach ( $known as $pattern ) {
			$regex = '#^' . preg_replace( '/\\\\\{[a-z]+\\\\\}/', '[a-z0-9_-]+', preg_quote( $pattern, '#' ) ) . '$#';
			if ( 1 === preg_match( (string) $regex, $hook ) ) {
				return true;
			}
		}
		return false;
	}
}
