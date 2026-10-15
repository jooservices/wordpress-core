<?php
/**
 * WPML.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's documented public filters.

namespace JOOservices\ThemeCore\Compat\Multilingual;

use JOOservices\ThemeCore\Compat\Detector;
use JOOservices\ThemeCore\Compat\Multilingual;
use JOOservices\ThemeCore\Seo\Context;

/**
 * WPML through its documented `wpml_*` filters.
 *
 * @internal
 */
final class WpmlAdapter implements Multilingual {

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
		return $this->detector->active( 'wpml' );
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
		$raw = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $code => $row ) {
			$out[] = new Lang(
				Context::normaliseLanguage( (string) ( $row['code'] ?? $code ) ),
				(string) ( $row['default_locale'] ?? '' ),
				(string) ( $row['native_name'] ?? $code ),
				(string) ( $row['url'] ?? '' ),
				! empty( $row['active'] )
			);
		}
		return $out;
	}

	/**
	 * Current language.
	 */
	public function currentLanguage(): string {
		$code = apply_filters( 'wpml_current_language', null );
		return is_string( $code ) && '' !== $code ? Context::normaliseLanguage( $code ) : $this->defaultLanguage();
	}

	/**
	 * Default language.
	 */
	public function defaultLanguage(): string {
		$code = apply_filters( 'wpml_default_language', null );
		return is_string( $code ) && '' !== $code ? Context::normaliseLanguage( $code ) : 'en';
	}

	/**
	 * Post translations.
	 *
	 * @param int $postId Post id.
	 * @return array<string, int>
	 */
	public function translations( int $postId ): array {
		return $this->elements( $postId, 'post_' . (string) get_post_type( $postId ) );
	}

	/**
	 * Term translations.
	 *
	 * @param int    $termId   Term id.
	 * @param string $taxonomy Taxonomy.
	 * @return array<string, int>
	 */
	public function termTranslations( int $termId, string $taxonomy ): array {
		return $this->elements( $termId, 'tax_' . $taxonomy );
	}

	/**
	 * Home URL.
	 *
	 * @param string $code Code.
	 */
	public function homeUrl( string $code ): string {
		$url = apply_filters( 'wpml_permalink', home_url( '/' ), $code );
		return is_string( $url ) && '' !== $url ? $url : home_url( '/' );
	}

	/**
	 * Translations of an element through its trid.
	 *
	 * @param int    $id   Element id.
	 * @param string $type WPML element type.
	 * @return array<string, int>
	 */
	private function elements( int $id, string $type ): array {
		$trid  = apply_filters( 'wpml_element_trid', null, $id, $type );
		$items = $trid ? apply_filters( 'wpml_get_element_translations', null, $trid, $type ) : null;
		$out   = array();
		foreach ( is_array( $items ) ? $items : array() as $code => $row ) {
			if ( is_object( $row ) && isset( $row->element_id ) ) {
				$out[ Context::normaliseLanguage( (string) $code ) ] = (int) $row->element_id;
			}
		}
		return $out;
	}
}
