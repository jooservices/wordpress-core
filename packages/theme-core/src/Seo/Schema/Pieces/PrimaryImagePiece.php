<?php
/**
 * Primary image node.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo\Schema\Pieces;

use JOOservices\ThemeCore\Seo\Context;
use JOOservices\ThemeCore\Seo\SocialTags;

/**
 * The page image (same chain as the social image).
 *
 * @internal
 */
final class PrimaryImagePiece extends AbstractPiece {

	/**
	 * Page URL helper.
	 *
	 * @var PageUrl
	 */
	private PageUrl $url;

	/**
	 * Social tags (image chain).
	 *
	 * @var SocialTags
	 */
	private SocialTags $social;

	/**
	 * Creates the piece.
	 *
	 * @param PageUrl    $url    Page URL helper.
	 * @param SocialTags $social Social tags.
	 */
	public function __construct( PageUrl $url, SocialTags $social ) {
		$this->url    = $url;
		$this->social = $social;
	}

	/**
	 * Name.
	 */
	protected function name(): string {
		return 'imageobject';
	}

	/**
	 * Id.
	 *
	 * @param Context $context Context.
	 */
	public function id( Context $context ): string {
		return ( $this->url->of( $context ) ?? '' ) . '#primaryimage';
	}

	/**
	 * Needed on public singular pages that have an image.
	 *
	 * @param Context $context Context.
	 */
	public function isNeeded( Context $context ): bool {
		return $context->isSingular() && ! $context->isRestricted() && null !== $this->url->of( $context ) && null !== $this->social->image( $context );
	}

	/**
	 * Node.
	 *
	 * @param Context $context Context.
	 * @return array<string, mixed>
	 */
	protected function build( Context $context ): array {
		$image = $this->social->image( $context );
		if ( null === $image ) {
			return array();
		}
		return array(
			'@type'      => 'ImageObject',
			'url'        => $image['url'],
			'contentUrl' => $image['url'],
			'width'      => $image['width'],
			'height'     => $image['height'],
			'caption'    => $image['alt'],
		);
	}

	/**
	 * Priority (dropped first when the graph is too large).
	 */
	public function priority(): int {
		return 20;
	}
}
