<?php
/**
 * Internationalisation module.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\I18n;

use JOOservices\ThemeCore\Compat\Multilingual\Resolver;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Module\AbstractModule;
use JOOservices\ThemeCore\Performance\FragmentCache;

/**
 * Text domains, the language service, built-in language tagging and the JavaScript string map.
 *
 * @internal
 */
final class I18nModule extends AbstractModule {

	/**
	 * Module id.
	 */
	public static function id(): string {
		return 'i18n';
	}

	/**
	 * Modules that must boot first.
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array( 'compat', 'perf' );
	}

	/**
	 * Binds services.
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->singleton( Languages::class, static fn( Container $c ) => new Languages( $c->get( Manifest::class ), $c->get( Settings::class ), $c->get( Resolver::class ) ) );
		$container->singleton( Translations::class, static fn( Container $c ) => new Translations( $c->get( FragmentCache::class ) ) );
		$container->singleton( LanguageTagging::class, static fn( Container $c ) => new LanguageTagging( $c->get( Languages::class ), $c->get( Translations::class ) ) );
		$container->singleton( JsStrings::class, static fn() => new JsStrings() );
	}

	/**
	 * Loads translations and hooks the services.
	 */
	public function boot(): void {
		if ( null === $this->container ) {
			return;
		}
		$c = $this->container;
		self::loadCore();
		/** @var Manifest $manifest */
		$manifest = $c->get( Manifest::class );
		load_theme_textdomain( $manifest->slug(), get_template_directory() . '/languages' );
		$c->get( Languages::class )->hook();
		$c->get( LanguageTagging::class )->hook();
		$c->get( JsStrings::class )->hook();
	}

	/**
	 * Loads the `joo-core` text domain (exact locale first, then the language, for example vi_VN then vi).
	 */
	public static function loadCore(): bool {
		$locale = determine_locale();
		$dir    = JOO_CORE_DIR . '/languages/joo-core-';
		foreach ( array( $locale, substr( $locale, 0, 2 ) ) as $candidate ) {
			if ( is_readable( $dir . $candidate . '.mo' ) ) {
				return load_textdomain( 'joo-core', $dir . $candidate . '.mo' );
			}
		}
		return false;
	}
}
