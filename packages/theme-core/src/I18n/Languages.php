<?php
/**
 * Language service.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\I18n;

use JOOservices\ThemeCore\Compat\Multilingual\Resolver;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Seo\Context;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * Current language, locale, default and configured languages. Resolution order for the current language:
 * multilingual plugin, language tag of the queried post, site locale, default language.
 *
 * @api
 */
final class Languages {

	private const NAMES = array(
		'en' => array( 'en_US', 'English' ),
		'vi' => array( 'vi', 'Tiếng Việt' ),
		'fr' => array( 'fr_FR', 'Français' ),
		'de' => array( 'de_DE', 'Deutsch' ),
		'es' => array( 'es_ES', 'Español' ),
		'ja' => array( 'ja', '日本語' ),
		'ko' => array( 'ko_KR', '한국어' ),
		'zh' => array( 'zh_CN', '中文' ),
		'ar' => array( 'ar', 'العربية' ),
		'he' => array( 'he_IL', 'עברית' ),
	);

	private const RTL = array( 'ar', 'he', 'fa', 'ur' );

	/**
	 * Manifest.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Multilingual resolver.
	 *
	 * @var Resolver
	 */
	private Resolver $resolver;

	/**
	 * Creates the service.
	 *
	 * @param Manifest $manifest Manifest.
	 * @param Settings $settings Settings.
	 * @param Resolver $resolver Multilingual resolver.
	 */
	public function __construct( Manifest $manifest, Settings $settings, Resolver $resolver ) {
		$this->manifest = $manifest;
		$this->settings = $settings;
		$this->resolver = $resolver;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		if ( is_admin() ) {
			return;
		}
		add_filter( 'language_attributes', array( $this, 'htmlAttributes' ), 10, 2 );
		add_action( 'wp', array( $this, 'maybeSwitchLocale' ), 5 );
	}

	/**
	 * Default language code.
	 */
	public function default(): string {
		$codes  = $this->codes();
		$wanted = Context::normaliseLanguage( (string) $this->settings->get( 'i18n.default', (string) $this->manifest->get( 'i18n.default', 'en' ) ) );
		return in_array( $wanted, $codes, true ) ? $wanted : $codes[0];
	}

	/**
	 * Configured language codes (at least the default).
	 *
	 * @return list<string>
	 */
	public function codes(): array {
		$raw   = (array) $this->settings->get( 'i18n.languages', (array) $this->manifest->get( 'i18n.languages', array( 'en' ) ) );
		$codes = array();
		foreach ( $raw as $code ) {
			if ( is_string( $code ) && 1 === preg_match( '/^[a-zA-Z]{2,3}([-_][a-zA-Z]{2})?$/', $code ) ) {
				$codes[] = Context::normaliseLanguage( $code );
			}
		}
		$codes = array_values( array_unique( $codes ) );
		return array() === $codes ? array( 'en' ) : $codes;
	}

	/**
	 * Configured languages.
	 *
	 * @return list<Language>
	 */
	public function all(): array {
		$out = array();
		foreach ( $this->codes() as $code ) {
			$out[] = $this->language( $code );
		}
		return $out;
	}

	/**
	 * Language value for a code (names and locales extend through `joo/i18n/locales`).
	 *
	 * @param string $code Code.
	 */
	public function language( string $code ): Language {
		$base  = strtolower( explode( '-', $code )[0] );
		$known = self::NAMES;
		$extra = Hooks::filter( 'joo/i18n/locales', array() );
		foreach ( is_array( $extra ) ? $extra : array() as $key => $row ) {
			if ( is_array( $row ) && isset( $row['locale'], $row['name'] ) ) {
				$known[ (string) $key ] = array( (string) $row['locale'], (string) $row['name'] );
			}
		}
		$row    = $known[ $code ] ?? $known[ $base ] ?? array( $base, strtoupper( $base ) );
		$locale = 1 === preg_match( '/^([a-z]{2})-([A-Z]{2})$/', $code, $m ) ? $m[1] . '_' . $m[2] : $row[0];
		return new Language( $code, $locale, $row[1], in_array( $base, self::RTL, true ) );
	}

	/**
	 * Whether more than one language is configured.
	 */
	public function isMultilingual(): bool {
		return count( $this->codes() ) > 1;
	}

	/**
	 * Current language code.
	 */
	public function current(): string {
		if ( $this->resolver->isPlugin() ) {
			return $this->resolver->get()->currentLanguage();
		}
		$object = is_singular() ? get_queried_object() : null;
		if ( $object instanceof \WP_Post ) {
			$tag = (string) get_post_meta( $object->ID, '_joo_lang', true );
			if ( '' !== $tag && in_array( Context::normaliseLanguage( $tag ), $this->codes(), true ) ) {
				return Context::normaliseLanguage( $tag );
			}
		}
		$site = Context::normaliseLanguage( determine_locale() );
		$base = explode( '-', $site )[0];
		foreach ( array( $site, $base ) as $candidate ) {
			if ( in_array( $candidate, $this->codes(), true ) ) {
				return $candidate;
			}
		}
		return $this->default();
	}

	/**
	 * Current locale (`vi_VN`).
	 */
	public function locale(): string {
		return $this->language( $this->current() )->locale;
	}

	/**
	 * `lang` and `dir` of the html tag.
	 *
	 * @param mixed  $output  Existing attributes.
	 * @param string $doctype Doctype.
	 */
	public function htmlAttributes( $output, $doctype = 'html' ): string {
		$output = (string) $output;
		if ( 'html' !== $doctype ) {
			return $output;
		}
		$language = $this->language( $this->current() );
		$output   = (string) preg_replace( '/(^|\s)(lang|dir)="[^"]*"/', '', $output );
		return trim( $output . ' lang="' . esc_attr( $language->code ) . '"' . ( $language->rtl ? ' dir="rtl"' : '' ) );
	}

	/**
	 * Optionally renders core strings in the language of the post (setting `i18n.switch_locale`, default off).
	 */
	public function maybeSwitchLocale(): void {
		if ( ! (bool) $this->settings->get( 'i18n.switch_locale', false ) || ! is_singular() || $this->resolver->isPlugin() ) {
			return;
		}
		$locale = $this->locale();
		if ( determine_locale() !== $locale ) {
			switch_to_locale( $locale );
		}
	}
}
