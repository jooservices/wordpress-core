<?php
/**
 * TranslatePress.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Multilingual;

use JOOservices\ThemeCore\Compat\Detector;
use JOOservices\ThemeCore\Compat\Multilingual;
use JOOservices\ThemeCore\Seo\Context;

/**
 * TranslatePress translates by URL, so post ids are not available: translations() is empty and language URLs drive hreflang.
 *
 * @internal
 */
final class TranslatePressAdapter implements Multilingual {

	/**
	 * Detector.
	 *
	 * @var Detector
	 */
	private Detector $detector;

	/**
	 * Creates the adapter.
	 *
	 * @param Detector $detector Detector.
	 */
	public function __construct( Detector $detector ) {
		$this->detector = $detector;
	}

	/**
	 * Active.
	 */
	public function active(): bool {
		return $this->detector->active( 'translatepress' );
	}

	/**
	 * Languages.
	 *
	 * @return list<Lang>
	 */
	public function languages(): array {
		if ( ! $this->active() ) {
			return array();
		}
		$settings = get_option( 'trp_settings', array() );
		$locales  = is_array( $settings ) && isset( $settings['translation-languages'] ) ? (array) $settings['translation-languages'] : array();
		$names    = function_exists( 'trp_get_languages' ) ? (array) trp_get_languages() : array();
		$current  = $this->currentLocale();
		$out      = array();
		foreach ( $locales as $locale ) {
			$locale = (string) $locale;
			$out[]  = new Lang( Context::normaliseLanguage( $locale ), $locale, (string) ( $names[ $locale ] ?? $locale ), $this->urlFor( $locale ), $locale === $current );
		}
		return $out;
	}

	/**
	 * Current language.
	 */
	public function currentLanguage(): string {
		return Context::normaliseLanguage( $this->currentLocale() );
	}

	/**
	 * Default language.
	 */
	public function defaultLanguage(): string {
		$settings = get_option( 'trp_settings', array() );
		$default  = is_array( $settings ) && isset( $settings['default-language'] ) ? (string) $settings['default-language'] : get_locale();
		return Context::normaliseLanguage( $default );
	}

	/**
	 * No post ids with TranslatePress.
	 *
	 * @param int $postId Post id.
	 * @return array<string, int>
	 */
	public function translations( int $postId ): array {
		unset( $postId );
		return array();
	}

	/**
	 * No term ids with TranslatePress.
	 *
	 * @param int    $termId   Term id.
	 * @param string $taxonomy Taxonomy.
	 * @return array<string, int>
	 */
	public function termTranslations( int $termId, string $taxonomy ): array {
		unset( $termId, $taxonomy );
		return array();
	}

	/**
	 * Home URL.
	 *
	 * @param string $code Code.
	 */
	public function homeUrl( string $code ): string {
		foreach ( $this->languages() as $lang ) {
			if ( $lang->code === $code ) {
				return $this->urlFor( $lang->locale, true );
			}
		}
		return home_url( '/' );
	}

	/**
	 * Current locale as TranslatePress reports it.
	 */
	private function currentLocale(): string {
		global $TRP_LANGUAGE; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		return is_string( $TRP_LANGUAGE ) && '' !== $TRP_LANGUAGE ? $TRP_LANGUAGE : get_locale();
	}

	/**
	 * URL of a language (page or home).
	 *
	 * @param string $locale Locale.
	 * @param bool   $home   Use the home URL.
	 */
	private function urlFor( string $locale, bool $home = false ): string {
		if ( function_exists( 'trp_get_url_converter' ) ) {
			$converter = trp_get_url_converter();
			if ( is_object( $converter ) && is_callable( array( $converter, 'get_url_for_language' ) ) ) {
				$url = $converter->get_url_for_language( $locale, $home ? home_url( '/' ) : null );
				return is_string( $url ) ? $url : home_url( '/' );
			}
		}
		return home_url( '/' );
	}
}
