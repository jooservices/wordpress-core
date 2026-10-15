<?php
/**
 * WebSite and Organization nodes.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo\Schema\Pieces;

use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Seo\Context;
use JOOservices\ThemeCore\Seo\Text;

/**
 * WebSite.
 *
 * @internal
 */
final class WebSitePiece extends AbstractPiece {

	/**
	 * Name.
	 */
	protected function name(): string {
		return 'website';
	}

	/**
	 * Id.
	 *
	 * @param Context $context Context.
	 */
	public function id( Context $context ): string {
		unset( $context );
		return SiteNodes::websiteId();
	}

	/**
	 * Everywhere.
	 *
	 * @param Context $context Context.
	 */
	public function isNeeded( Context $context ): bool {
		unset( $context );
		return true;
	}

	/**
	 * Node.
	 *
	 * @param Context $context Context.
	 * @return array<string, mixed>
	 */
	protected function build( Context $context ): array {
		return array(
			'@type'           => 'WebSite',
			'url'             => home_url( '/' ),
			'name'            => Text::plain( get_bloginfo( 'name' ) ),
			'description'     => Text::plain( get_bloginfo( 'description' ) ),
			'inLanguage'      => $this->language( $context ),
			'publisher'       => array( '@id' => SiteNodes::organizationId() ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	/**
	 * Highest priority.
	 */
	public function priority(): int {
		return 1;
	}
}
