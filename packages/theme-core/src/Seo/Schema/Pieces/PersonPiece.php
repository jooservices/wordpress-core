<?php
/**
 * Person node.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo\Schema\Pieces;

use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Seo\Context;
use JOOservices\ThemeCore\Seo\Text;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * The site owner: posts, the owner's author archive and profile pages.
 *
 * @internal
 */
final class PersonPiece extends AbstractPiece {

	/**
	 * Manifest.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Creates the piece.
	 *
	 * @param Manifest $manifest Manifest.
	 */
	public function __construct( Manifest $manifest ) {
		$this->manifest = $manifest;
	}

	/**
	 * Name.
	 */
	protected function name(): string {
		return 'person';
	}

	/**
	 * Id.
	 *
	 * @param Context $context Context.
	 */
	public function id( Context $context ): string {
		unset( $context );
		return SiteNodes::personId();
	}

	/**
	 * Needed on posts, author archives and profile pages when an owner exists.
	 *
	 * @param Context $context Context.
	 */
	public function isNeeded( Context $context ): bool {
		if ( 0 === SiteNodes::ownerId( $this->manifest ) ) {
			return false;
		}
		if ( 'single' === $context->type() || 'author' === $context->type() ) {
			return true;
		}
		return (bool) Hooks::filter( 'joo/seo/profile_page', false, $context );
	}

	/**
	 * Node.
	 *
	 * @param Context $context Context.
	 * @return array<string, mixed>
	 */
	protected function build( Context $context ): array {
		unset( $context );
		$id   = SiteNodes::ownerId( $this->manifest );
		$user = get_userdata( $id );
		if ( false === $user ) {
			return array();
		}
		$socials = get_user_meta( $id, 'joo_socials', true );
		if ( is_string( $socials ) ) {
			$socials = preg_split( '/\s+/', trim( $socials ) ) ?: array();
		}
		$urls = array();
		foreach ( (array) $socials as $entry ) {
			$url = is_array( $entry ) ? ( $entry['url'] ?? '' ) : $entry; // JOOservices Resume stores {label, url} rows.
			if ( is_string( $url ) && 1 === preg_match( '#^https?://#', $url ) ) {
				$urls[] = $url;
			}
		}
		$socials = $urls;
		$avatar  = get_avatar_url( $id, array( 'size' => 256 ) );
		$url     = Hooks::filter( 'joo/profile/url', get_author_posts_url( $id ), $id );
		return array(
			'@type'       => 'Person',
			'name'        => Text::plain( $user->display_name ),
			'url'         => is_string( $url ) ? $url : get_author_posts_url( $id ),
			'image'       => false === $avatar ? null : array(
				'@type'      => 'ImageObject',
				'@id'        => home_url( '/#personimage' ),
				'url'        => $avatar,
				'contentUrl' => $avatar,
				'width'      => 256,
				'height'     => 256,
			),
			'jobTitle'    => Text::plain( (string) get_user_meta( $id, 'joo_title', true ) ),
			'description' => Text::truncate( Text::plain( (string) $user->description ), 200 ),
			'sameAs'      => $socials,
			'worksFor'    => array( '@id' => SiteNodes::organizationId() ),
		);
	}

	/**
	 * Priority.
	 */
	public function priority(): int {
		return 3;
	}
}
