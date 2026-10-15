<?php
/**
 * URL of the current page for schema ids.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo\Schema\Pieces;

use JOOservices\ThemeCore\Seo\Canonical;
use JOOservices\ThemeCore\Seo\Context;

/**
 * Canonical URL, falling back to the search or date link for pages that have none.
 *
 * @internal
 */
final class PageUrl {

	/**
	 * Canonical service.
	 *
	 * @var Canonical
	 */
	private Canonical $canonical;

	/**
	 * Creates the helper.
	 *
	 * @param Canonical $canonical Canonical service.
	 */
	public function __construct( Canonical $canonical ) {
		$this->canonical = $canonical;
	}

	/**
	 * URL (null when the page has no identity, such as a 404).
	 *
	 * @param Context $context Context.
	 */
	public function of( Context $context ): ?string {
		if ( '404' === $context->type() ) {
			return null;
		}
		$url = $this->canonical->url( $context );
		if ( null !== $url ) {
			return $url;
		}
		if ( 'search' === $context->type() ) {
			return get_search_link( get_search_query( false ) );
		}
		return get_pagenum_link( $context->paged() );
	}
}
