<?php
/**
 * Block joo/author-box.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\DynamicBlock;
use JOOservices\ThemeCore\Content\ContentProviderInterface;
use JOOservices\ThemeCore\Content\WpContentProvider;

/**
 * Author profile below an article: the owner profile for the owner, WordPress user data for everyone else.
 *
 * @internal
 */
final class AuthorBox extends DynamicBlock {

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/author-box';
	}

	/**
	 * Data.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @param \WP_Block            $block Block.
	 * @return array<string, mixed>
	 */
	protected function data( array $attrs, \WP_Block $block ): array {
		unset( $attrs );
		$postId = (int) ( $block->context['postId'] ?? get_the_ID() );
		$post   = $postId > 0 ? get_post( $postId ) : null;
		if ( ! $post instanceof \WP_Post ) {
			return array( 'author' => null );
		}
		$id       = (int) $post->post_author;
		$provider = $this->container->get( ContentProviderInterface::class );
		$profile  = $provider instanceof WpContentProvider && $provider->ownerId() === $id ? $provider->profile() : null;
		if ( null !== $profile ) {
			return array(
				'author' => array(
					'name'    => $profile->name,
					'title'   => $profile->title,
					'bio'     => $profile->shortBio,
					'avatar'  => $profile->avatarUrl,
					'url'     => get_author_posts_url( $id ),
					'socials' => $profile->socials,
				),
				'_class' => 'joo-author joo-cv',
			);
		}
		$avatar = get_avatar_url( $id, array( 'size' => 96 ) );
		return array(
			'author' => array(
				'name'    => (string) get_the_author_meta( 'display_name', $id ),
				'title'   => '',
				'bio'     => wp_trim_words( (string) get_the_author_meta( 'description', $id ), 40 ),
				'avatar'  => false === $avatar ? '' : $avatar,
				'url'     => get_author_posts_url( $id ),
				'socials' => array(),
			),
			'_class' => 'joo-author joo-cv',
		);
	}
}
