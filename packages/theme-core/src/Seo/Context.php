<?php
/**
 * Request context for SEO output.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo;

use JOOservices\ThemeCore\Support\Hooks;

/**
 * What kind of page this request is, resolved once after the main query.
 *
 * @internal
 */
final class Context {

	/**
	 * Known types.
	 */
	public const TYPES = array( 'home', 'front', 'single', 'page', 'archive', 'category', 'tag', 'tax', 'author', 'date', 'search', '404', 'attachment' );

	/**
	 * Type.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * Queried object.
	 *
	 * @var object|null
	 */
	private ?object $object;

	/**
	 * Page number (archive page or singular <!--nextpage--> page).
	 *
	 * @var int
	 */
	private int $paged;

	/**
	 * Language code.
	 *
	 * @var string
	 */
	private string $language;

	/**
	 * Creates a context.
	 *
	 * @param string      $type     One of TYPES.
	 * @param object|null $object   Queried object (WP_Post, WP_Term, WP_User).
	 * @param int         $paged    Page number, at least 1.
	 * @param string      $language Language code.
	 */
	public function __construct( string $type, ?object $object = null, int $paged = 1, string $language = 'en' ) {
		$this->type     = in_array( $type, self::TYPES, true ) ? $type : 'archive';
		$this->object   = $object;
		$this->paged    = max( 1, $paged );
		$this->language = $language;
	}

	/**
	 * Resolves the context from the global query.
	 */
	public static function fromGlobals(): self {
		if ( is_404() ) {
			$type = '404';
		} elseif ( is_search() ) {
			$type = 'search';
		} elseif ( is_attachment() ) {
			$type = 'attachment';
		} elseif ( is_author() ) {
			$type = 'author';
		} elseif ( is_category() ) {
			$type = 'category';
		} elseif ( is_tag() ) {
			$type = 'tag';
		} elseif ( is_tax() ) {
			$type = 'tax';
		} elseif ( is_date() ) {
			$type = 'date';
		} elseif ( is_page() ) {
			$type = 'page';
		} elseif ( is_singular() ) {
			$type = 'single';
		} elseif ( is_front_page() ) {
			$type = 'front';
		} elseif ( is_home() ) {
			$type = 'home';
		} else {
			$type = 'archive';
		}
		$object = get_queried_object();
		$paged  = (int) get_query_var( 'paged' );
		if ( $paged < 2 && is_singular() ) {
			$paged = (int) get_query_var( 'page' );
		}
		$language = self::languageOf( is_object( $object ) ? $object : null );
		$context  = new self( $type, is_object( $object ) ? $object : null, $paged, $language );
		$filtered = Hooks::filter( 'joo/seo/context', $context );
		return $filtered instanceof self ? $filtered : $context;
	}

	/**
	 * Language of a post (its `_joo_lang`) or of the site locale.
	 *
	 * @param object|null $object Queried object.
	 */
	public static function languageOf( ?object $object ): string {
		if ( $object instanceof \WP_Post ) {
			$lang = (string) get_post_meta( $object->ID, '_joo_lang', true );
			if ( '' !== $lang ) {
				return self::normaliseLanguage( $lang );
			}
		}
		return self::normaliseLanguage( function_exists( 'determine_locale' ) ? determine_locale() : get_locale() );
	}

	/**
	 * Normalises a locale or tag to `ll` or `ll-RR`.
	 *
	 * @param string $code Code such as en_US, vi, pt-br.
	 */
	public static function normaliseLanguage( string $code ): string {
		$parts = preg_split( '/[-_]/', trim( $code ) ) ?: array();
		$lang  = strtolower( (string) ( $parts[0] ?? '' ) );
		if ( '' === $lang ) {
			return 'en';
		}
		$region = isset( $parts[1] ) && 2 === strlen( $parts[1] ) ? '-' . strtoupper( $parts[1] ) : '';
		return $lang . $region;
	}

	/**
	 * Type.
	 */
	public function type(): string {
		return $this->type;
	}

	/**
	 * Queried object.
	 */
	public function object(): ?object {
		return $this->object;
	}

	/**
	 * Page number.
	 */
	public function paged(): int {
		return $this->paged;
	}

	/**
	 * Language.
	 */
	public function language(): string {
		return $this->language;
	}

	/**
	 * Queried post (null for non-singular contexts).
	 */
	public function post(): ?\WP_Post {
		return $this->object instanceof \WP_Post ? $this->object : null;
	}

	/**
	 * Post id (0 when none).
	 */
	public function postId(): int {
		$post = $this->post();
		return null === $post ? 0 : $post->ID;
	}

	/**
	 * Whether this is a singular context.
	 */
	public function isSingular(): bool {
		return in_array( $this->type, array( 'single', 'page', 'attachment' ), true );
	}

	/**
	 * Whether the post is password protected or not public.
	 */
	public function isRestricted(): bool {
		$post = $this->post();
		return null !== $post && ( '' !== $post->post_password || 'private' === $post->post_status );
	}

	/**
	 * Whether search engines may index this page (default matrix; the post flag is included).
	 */
	public function isIndexable(): bool {
		if ( in_array( $this->type, array( '404', 'search', 'date' ), true ) ) {
			return false;
		}
		if ( 'attachment' === $this->type ) {
			return false;
		}
		$post = $this->post();
		if ( null !== $post ) {
			if ( $this->isRestricted() || (bool) get_post_meta( $post->ID, '_joo_seo_noindex', true ) ) {
				return false;
			}
		}
		return true;
	}
}
