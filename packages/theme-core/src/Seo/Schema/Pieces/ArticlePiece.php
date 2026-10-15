<?php
/**
 * BlogPosting node.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo\Schema\Pieces;

use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Seo\Context;
use JOOservices\ThemeCore\Seo\DescriptionBuilder;
use JOOservices\ThemeCore\Seo\Text;
use JOOservices\ThemeCore\Support\Hooks;
use JOOservices\ThemeCore\Support\ReadingTime;

/**
 * BlogPosting for posts (type adjustable with `joo/seo/schema/article_type`).
 *
 * @internal
 */
final class ArticlePiece extends AbstractPiece {

	/**
	 * Page URL helper.
	 *
	 * @var PageUrl
	 */
	private PageUrl $url;

	/**
	 * Description builder.
	 *
	 * @var DescriptionBuilder
	 */
	private DescriptionBuilder $descriptions;

	/**
	 * Manifest.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Creates the piece.
	 *
	 * @param PageUrl            $url          Page URL helper.
	 * @param DescriptionBuilder $descriptions Description builder.
	 * @param Manifest           $manifest     Manifest.
	 */
	public function __construct( PageUrl $url, DescriptionBuilder $descriptions, Manifest $manifest ) {
		$this->url          = $url;
		$this->descriptions = $descriptions;
		$this->manifest     = $manifest;
	}

	/**
	 * Name.
	 */
	protected function name(): string {
		return 'article';
	}

	/**
	 * Id.
	 *
	 * @param Context $context Context.
	 */
	public function id( Context $context ): string {
		return ( $this->url->of( $context ) ?? '' ) . '#article';
	}

	/**
	 * Needed on public single posts.
	 *
	 * @param Context $context Context.
	 */
	public function isNeeded( Context $context ): bool {
		return 'single' === $context->type() && null !== $context->post() && ! $context->isRestricted() && null !== $this->url->of( $context );
	}

	/**
	 * Node.
	 *
	 * @param Context $context Context.
	 * @return array<string, mixed>
	 */
	protected function build( Context $context ): array {
		$post = $context->post();
		if ( null === $post ) {
			return array();
		}
		$url     = (string) $this->url->of( $context );
		$reading = ReadingTime::forPost( $post->ID );
		$words   = $reading['words'];
		$cats    = 'post' === $post->post_type ? get_the_category( $post->ID ) : array();
		$tags    = get_the_tags( $post->ID );
		$tags    = is_array( $tags ) ? $tags : array();
		$author  = (int) $post->post_author;
		$type    = Hooks::filter( 'joo/seo/schema/article_type', 'post' === $post->post_type ? 'BlogPosting' : 'Article', $context );
		$minute  = $reading['minutes'];
		return array(
			'@type'            => is_string( $type ) ? $type : 'BlogPosting',
			'isPartOf'         => array( '@id' => $url . '#webpage' ),
			'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
			'headline'         => Text::truncate( Text::plain( get_the_title( $post ) ), 110 ),
			'description'      => $this->descriptions->build( $context ),
			'image'            => array( '@id' => $url . '#primaryimage' ),
			'datePublished'    => get_post_time( 'c', true, $post ),
			'dateModified'     => get_post_modified_time( 'c', true, $post ),
			'author'           => SiteNodes::ownerId( $this->manifest ) === $author ? array( '@id' => SiteNodes::personId() ) : array(
				'@type' => 'Person',
				'name'  => Text::plain( get_the_author_meta( 'display_name', $author ) ),
				'url'   => get_author_posts_url( $author ),
			),
			'publisher'        => array( '@id' => SiteNodes::organizationId() ),
			'articleSection'   => array() === $cats ? null : Text::plain( $cats[0]->name ),
			'keywords'         => implode( ', ', array_map( static fn( \WP_Term $t ) => Text::plain( $t->name ), $tags ) ),
			'wordCount'        => $words,
			'timeRequired'     => $minute > 0 ? 'PT' . $minute . 'M' : null,
			'inLanguage'       => $this->language( $context ),
			'commentCount'     => (int) get_comments_number( $post ),
		);
	}

	/**
	 * Priority.
	 */
	public function priority(): int {
		return 5;
	}
}
