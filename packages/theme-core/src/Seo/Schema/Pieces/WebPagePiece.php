<?php
/**
 * WebPage family node.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo\Schema\Pieces;

use JOOservices\ThemeCore\Seo\Context;
use JOOservices\ThemeCore\Seo\TitleBuilder;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * WebPage, CollectionPage, ProfilePage or SearchResultsPage.
 *
 * @internal
 */
final class WebPagePiece extends AbstractPiece {

	/**
	 * Page URL helper.
	 *
	 * @var PageUrl
	 */
	private PageUrl $url;

	/**
	 * Title builder.
	 *
	 * @var TitleBuilder
	 */
	private TitleBuilder $titles;

	/**
	 * Creates the piece.
	 *
	 * @param PageUrl      $url    Page URL helper.
	 * @param TitleBuilder $titles Title builder.
	 */
	public function __construct( PageUrl $url, TitleBuilder $titles ) {
		$this->url    = $url;
		$this->titles = $titles;
	}

	/**
	 * Name.
	 */
	protected function name(): string {
		return 'webpage';
	}

	/**
	 * Id.
	 *
	 * @param Context $context Context.
	 */
	public function id( Context $context ): string {
		return ( $this->url->of( $context ) ?? '' ) . '#webpage';
	}

	/**
	 * Needed whenever the page has a URL.
	 *
	 * @param Context $context Context.
	 */
	public function isNeeded( Context $context ): bool {
		return null !== $this->url->of( $context );
	}

	/**
	 * Schema type of the page.
	 *
	 * @param Context $context Context.
	 */
	public function type( Context $context ): string {
		$profile = (bool) Hooks::filter( 'joo/seo/profile_page', false, $context );
		if ( 'author' === $context->type() || $profile ) {
			return 'ProfilePage';
		}
		if ( 'search' === $context->type() ) {
			return 'SearchResultsPage';
		}
		return $context->isSingular() ? 'WebPage' : 'CollectionPage';
	}

	/**
	 * Node.
	 *
	 * @param Context $context Context.
	 * @return array<string, mixed>
	 */
	protected function build( Context $context ): array {
		$url  = (string) $this->url->of( $context );
		$post = $context->post();
		$type = $this->type( $context );
		$node = array(
			'@type'      => $type,
			'url'        => $url,
			'name'       => $this->titles->build( $context ),
			'isPartOf'   => array( '@id' => SiteNodes::websiteId() ),
			'inLanguage' => $this->language( $context ),
			'breadcrumb' => array( '@id' => $url . '#breadcrumb' ),
		);
		if ( 'ProfilePage' === $type ) {
			$node['mainEntity'] = array( '@id' => SiteNodes::personId() );
		}
		if ( null !== $post && ! $context->isRestricted() ) {
			$node['datePublished']      = get_post_time( 'c', true, $post );
			$node['dateModified']       = get_post_modified_time( 'c', true, $post );
			$node['primaryImageOfPage'] = array( '@id' => $url . '#primaryimage' );
		}
		return $node;
	}

	/**
	 * Priority.
	 */
	public function priority(): int {
		return 4;
	}
}
