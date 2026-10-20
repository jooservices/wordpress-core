<?php
/**
 * Block joo/profile-card.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\DynamicBlock;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Content\ContentProviderInterface;

/**
 * "About me": avatar, name, title, short bio and a link to the About page (or the author archive).
 *
 * @internal
 */
final class ProfileCard extends DynamicBlock {

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/profile-card';
	}

	/**
	 * Data.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @param \WP_Block            $block Block.
	 * @return array<string, mixed>
	 */
	protected function data( array $attrs, \WP_Block $block ): array {
		unset( $block );
		/** @var ContentProviderInterface $provider */
		$provider = $this->container->get( ContentProviderInterface::class );
		$profile  = $provider->profile();
		if ( null === $profile ) {
			return array( 'profile' => null );
		}
		/** @var Manifest $manifest */
		$manifest  = $this->container->get( Manifest::class );
		$aboutPath = (string) $manifest->get( 'content.profilePagePath', '' );
		$about     = 'author' === ( $attrs['linkTo'] ?? 'about' ) || '' === $aboutPath ? null : get_page_by_path( $aboutPath );
		$url       = $about instanceof \WP_Post && 'publish' === $about->post_status ? (string) get_permalink( $about ) : $profile->url;
		return array(
			'profile'     => $profile,
			'url'         => $url,
			'showBio'     => ! array_key_exists( 'showBio', $attrs ) || (bool) $attrs['showBio'],
			'showSocials' => ! array_key_exists( 'showSocials', $attrs ) || (bool) $attrs['showSocials'],
			'size'        => max( 48, min( 128, (int) ( $attrs['avatarSize'] ?? 64 ) ) ),
			'title'       => (string) ( $attrs['title'] ?? '' ),
			'level'       => max( 2, min( 4, (int) ( $attrs['titleLevel'] ?? 2 ) ) ),
			'_class'      => 'joo-profile',
		);
	}
}
