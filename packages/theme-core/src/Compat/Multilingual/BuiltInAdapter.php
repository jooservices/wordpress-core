<?php
/**
 * Built-in language tagging.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Multilingual;

use JOOservices\ThemeCore\Compat\Multilingual;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Seo\Context;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * No plugin: languages come from the manifest, content language from `_joo_lang`, grouping from `_joo_translation_of`.
 *
 * @internal
 */
final class BuiltInAdapter implements Multilingual {

	/**
	 * Manifest.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Creates the adapter.
	 *
	 * @param Manifest $manifest Manifest.
	 */
	public function __construct( Manifest $manifest ) {
		$this->manifest = $manifest;
	}

	/**
	 * Usable when the manifest lists at least two languages.
	 */
	public function active(): bool {
		return count( $this->codes() ) >= 2;
	}

	/**
	 * Language codes from the manifest.
	 *
	 * @return list<string>
	 */
	private function codes(): array {
		$codes = array();
		foreach ( (array) $this->manifest->get( 'i18n.languages', array() ) as $code ) {
			if ( is_string( $code ) && '' !== $code ) {
				$codes[] = Context::normaliseLanguage( $code );
			}
		}
		return array_values( array_unique( $codes ) );
	}

	/**
	 * Languages (URLs point to the translation of the current post when there is one, else to the home page).
	 *
	 * @return list<Lang>
	 */
	public function languages(): array {
		$names   = $this->names();
		$current = $this->currentLanguage();
		$map     = array();
		$post    = is_singular() ? get_queried_object() : null;
		if ( $post instanceof \WP_Post ) {
			foreach ( $this->translations( $post->ID ) as $code => $id ) {
				$link = get_permalink( $id );
				if ( is_string( $link ) ) {
					$map[ $code ] = $link;
				}
			}
		}
		$out = array();
		foreach ( $this->codes() as $code ) {
			$out[] = new Lang( $code, $names[ $code ]['locale'], $names[ $code ]['name'], $map[ $code ] ?? home_url( '/' ), $code === $current );
		}
		return $out;
	}

	/**
	 * Locale and display name per code (extend with `joo/i18n/locales`).
	 *
	 * @return array<string, array{locale: string, name: string}>
	 */
	public function names(): array {
		$known = array(
			'en' => array(
				'locale' => 'en_US',
				'name'   => 'English',
			),
			'vi' => array(
				'locale' => 'vi_VN',
				'name'   => 'Tiếng Việt',
			),
		);
		$extra = Hooks::filter( 'joo/i18n/locales', array() );
		$known = is_array( $extra ) ? array_merge( $known, $extra ) : $known;
		$out   = array();
		foreach ( $this->codes() as $code ) {
			$out[ $code ] = array(
				'locale' => (string) ( $known[ $code ]['locale'] ?? $code ),
				'name'   => (string) ( $known[ $code ]['name'] ?? strtoupper( $code ) ),
			);
		}
		return $out;
	}

	/**
	 * Current language: the post language on singular views, else the default.
	 */
	public function currentLanguage(): string {
		$object = is_singular() ? get_queried_object() : null;
		$lang   = Context::languageOf( is_object( $object ) ? $object : null );
		$codes  = $this->codes();
		if ( in_array( $lang, $codes, true ) ) {
			return $lang;
		}
		$base = explode( '-', $lang )[0];
		return in_array( $base, $codes, true ) ? $base : $this->defaultLanguage();
	}

	/**
	 * Default language.
	 */
	public function defaultLanguage(): string {
		return Context::normaliseLanguage( (string) $this->manifest->get( 'i18n.default', 'en' ) );
	}

	/**
	 * Published translations of a post (including itself) as code => id.
	 *
	 * @param int $postId Post id.
	 * @return array<string, int>
	 */
	public function translations( int $postId ): array {
		$source = (int) get_post_meta( $postId, '_joo_translation_of', true );
		$root   = $source > 0 ? $source : $postId;
		$ids    = get_posts(
			array(
				'post_type'        => (string) get_post_type( $postId ),
				'post_status'      => 'publish',
				'posts_per_page'   => 20,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_joo_translation_of',
						'value' => (string) $root,
					),
				),
			)
		);
		$out    = array();
		foreach ( array_unique( array_merge( array( $root ), array_map( 'intval', $ids ), array( $postId ) ) ) as $id ) {
			$lang = (string) get_post_meta( $id, '_joo_lang', true );
			if ( '' !== $lang && 'publish' === get_post_status( $id ) ) {
				$out[ Context::normaliseLanguage( $lang ) ] = (int) $id;
			}
		}
		return $out;
	}

	/**
	 * Terms are not translated by the built-in tagging.
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
	 * Home URL (one home for all languages).
	 *
	 * @param string $code Code.
	 */
	public function homeUrl( string $code ): string {
		unset( $code );
		return home_url( '/' );
	}
}
