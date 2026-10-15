<?php
/**
 * Polylang.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Multilingual;

use JOOservices\ThemeCore\Compat\Detector;
use JOOservices\ThemeCore\Compat\Multilingual;
use JOOservices\ThemeCore\Seo\Context;

/**
 * Guarded calls into Polylang's public API.
 *
 * @internal
 */
final class PolylangAdapter implements Multilingual {

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
		return $this->detector->active( 'polylang' ) && function_exists( 'pll_the_languages' );
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
		$raw = pll_the_languages(
			array(
				'raw'           => 1,
				'hide_if_empty' => 0,
			)
		);
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $row ) {
			$out[] = new Lang(
				Context::normaliseLanguage( (string) ( $row['slug'] ?? '' ) ),
				(string) ( $row['locale'] ?? '' ),
				(string) ( $row['name'] ?? '' ),
				(string) ( $row['url'] ?? '' ),
				! empty( $row['current_lang'] )
			);
		}
		return $out;
	}

	/**
	 * Current language.
	 */
	public function currentLanguage(): string {
		$code = function_exists( 'pll_current_language' ) ? pll_current_language() : '';
		return is_string( $code ) && '' !== $code ? Context::normaliseLanguage( $code ) : $this->defaultLanguage();
	}

	/**
	 * Default language.
	 */
	public function defaultLanguage(): string {
		$code = function_exists( 'pll_default_language' ) ? pll_default_language() : '';
		return is_string( $code ) && '' !== $code ? Context::normaliseLanguage( $code ) : 'en';
	}

	/**
	 * Post translations.
	 *
	 * @param int $postId Post id.
	 * @return array<string, int>
	 */
	public function translations( int $postId ): array {
		return function_exists( 'pll_get_post_translations' ) ? $this->normalise( pll_get_post_translations( $postId ) ) : array();
	}

	/**
	 * Term translations.
	 *
	 * @param int    $termId   Term id.
	 * @param string $taxonomy Taxonomy.
	 * @return array<string, int>
	 */
	public function termTranslations( int $termId, string $taxonomy ): array {
		unset( $taxonomy );
		return function_exists( 'pll_get_term_translations' ) ? $this->normalise( pll_get_term_translations( $termId ) ) : array();
	}

	/**
	 * Home URL.
	 *
	 * @param string $code Code.
	 */
	public function homeUrl( string $code ): string {
		$url = function_exists( 'pll_home_url' ) ? pll_home_url( $code ) : '';
		return '' !== $url ? $url : home_url( '/' );
	}

	/**
	 * Normalises a code => id map.
	 *
	 * @param mixed $map Raw map.
	 * @return array<string, int>
	 */
	private function normalise( $map ): array {
		$out = array();
		foreach ( is_array( $map ) ? $map : array() as $code => $id ) {
			$out[ Context::normaliseLanguage( (string) $code ) ] = (int) $id;
		}
		return $out;
	}
}
