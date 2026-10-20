<?php
/**
 * Open Graph and Twitter Card tags.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo;

use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * Builds the social meta tags as a list of [attribute, name, content] triples.
 *
 * @internal
 */
final class SocialTags {

	private const LOCALES = array(
		'en' => 'en_US',
		'vi' => 'vi_VN',
		'fr' => 'fr_FR',
		'de' => 'de_DE',
		'es' => 'es_ES',
		'ja' => 'ja_JP',
		'ko' => 'ko_KR',
		'zh' => 'zh_CN',
	);

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Creates the builder.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * `og:locale` value for a language code.
	 *
	 * @param string $language Language (en, vi, pt-BR).
	 */
	public static function locale( string $language ): string {
		if ( 1 === preg_match( '/^([a-z]{2})-([A-Z]{2})$/', $language, $m ) ) {
			return $m[1] . '_' . $m[2];
		}
		return self::LOCALES[ $language ] ?? $language . '_' . strtoupper( $language );
	}

	/**
	 * Tags for a page.
	 *
	 * @param Context              $context    Context.
	 * @param string               $title      Document title.
	 * @param string               $description Meta description.
	 * @param string|null          $canonical  Canonical URL.
	 * @param array<string,string> $alternates Hreflang map.
	 * @return list<array{0: string, 1: string, 2: string}> Attribute (property|name), name, content.
	 */
	public function tags( Context $context, string $title, string $description, ?string $canonical, array $alternates = array() ): array {
		if ( $context->isRestricted() || null === $canonical ) {
			return array();
		}
		$post  = $context->post();
		$type  = 'website';
		$image = $this->image( $context );
		if ( null !== $post && 'page' !== $context->type() ) {
			$type = 'article';
		} elseif ( 'author' === $context->type() ) {
			$type = 'profile';
		}
		$og_title = Text::truncate( $title, 90 );
		$tags     = array(
			array( 'property', 'og:locale', self::locale( $context->language() ) ),
			array( 'property', 'og:type', $type ),
			array( 'property', 'og:title', $og_title ),
			array( 'property', 'og:description', $description ),
			array( 'property', 'og:url', $canonical ),
			array( 'property', 'og:site_name', Text::plain( get_bloginfo( 'name' ) ) ),
		);
		foreach ( $alternates as $lang => $url ) {
			if ( 'x-default' !== $lang && $lang !== $context->language() ) {
				$tags[] = array( 'property', 'og:locale:alternate', self::locale( (string) $lang ) );
			}
			unset( $url );
		}
		if ( null !== $image ) {
			$tags[] = array( 'property', 'og:image', $image['url'] );
			if ( $image['width'] > 0 && $image['height'] > 0 ) {
				$tags[] = array( 'property', 'og:image:width', (string) $image['width'] );
				$tags[] = array( 'property', 'og:image:height', (string) $image['height'] );
			}
			if ( '' !== $image['alt'] ) {
				$tags[] = array( 'property', 'og:image:alt', $image['alt'] );
			}
		}
		if ( 'article' === $type && null !== $post ) {
			$tags[] = array( 'property', 'article:published_time', get_post_time( 'c', true, $post ) );
			$tags[] = array( 'property', 'article:modified_time', get_post_modified_time( 'c', true, $post ) );
			$tags[] = array( 'property', 'article:author', get_author_posts_url( (int) $post->post_author ) );
			$cats   = 'post' === $post->post_type ? get_the_category( $post->ID ) : array();
			if ( array() !== $cats ) {
				$tags[] = array( 'property', 'article:section', Text::plain( $cats[0]->name ) );
			}
			foreach ( array_slice( (array) get_the_tags( $post->ID ), 0, 6 ) as $tag ) {
				if ( $tag instanceof \WP_Term ) {
					$tags[] = array( 'property', 'article:tag', Text::plain( $tag->name ) );
				}
			}
		}
		$card   = null !== $image && $image['width'] >= 600 ? 'summary_large_image' : 'summary';
		$tags[] = array( 'name', 'twitter:card', $card );
		$site   = ltrim( (string) $this->settings->get( 'seo.twitter_site', '' ), '@' );
		if ( '' !== $site ) {
			$tags[] = array( 'name', 'twitter:site', '@' . $site );
		}
		$tags[] = array( 'name', 'twitter:title', $og_title );
		$tags[] = array( 'name', 'twitter:description', $description );
		if ( null !== $image ) {
			$tags[] = array( 'name', 'twitter:image', $image['url'] );
		}
		$filtered = Hooks::filter( 'joo/seo/og', $tags, $context );
		return is_array( $filtered ) ? array_values( $filtered ) : $tags;
	}

	/**
	 * Picks the social image: override, featured, first content image, site default.
	 *
	 * @param Context $context Context.
	 * @return array{url: string, width: int, height: int, alt: string}|null
	 */
	public function image( Context $context ): ?array {
		$post = $context->post();
		$ids  = array();
		if ( null !== $post ) {
			$ids[] = (int) get_post_meta( $post->ID, '_joo_seo_og_image', true );
			$ids[] = (int) get_post_thumbnail_id( $post );
			$ids[] = $this->firstContentImage( $post );
		}
		$ids[] = (int) $this->settings->get( 'seo.default_og_image', 0 );
		foreach ( $ids as $id ) {
			$picked = $id > 0 ? $this->describeAttachment( $id ) : null;
			if ( null !== $picked ) {
				return $picked;
			}
		}
		return null;
	}

	/**
	 * Attachment id of the first core/image block that has one.
	 *
	 * @param \WP_Post $post Post.
	 */
	private function firstContentImage( \WP_Post $post ): int {
		if ( ! has_blocks( $post->post_content ) ) {
			return 0;
		}
		$stack = parse_blocks( $post->post_content );
		while ( array() !== $stack ) {
			$block = array_shift( $stack );
			if ( 'core/image' === ( $block['blockName'] ?? '' ) && ! empty( $block['attrs']['id'] ) ) {
				return (int) $block['attrs']['id'];
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$stack = array_merge( $block['innerBlocks'], $stack );
			}
		}
		return 0;
	}

	/**
	 * Picks the best size (width >= 1200 preferred, else the largest >= 600) and describes it.
	 *
	 * @param int $id Attachment id.
	 * @return array{url: string, width: int, height: int, alt: string}|null
	 */
	private function describeAttachment( int $id ): ?array {
		if ( ! wp_attachment_is_image( $id ) ) {
			return null;
		}
		$meta = wp_get_attachment_metadata( $id );
		$full = wp_get_attachment_image_src( $id, 'full' );
		if ( ! is_array( $full ) ) {
			return null;
		}
		$candidates = array(
			array(
				'url'    => (string) $full[0],
				'width'  => (int) $full[1],
				'height' => (int) $full[2],
			),
		);
		if ( is_array( $meta ) && ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			$dir = trailingslashit( dirname( (string) $full[0] ) );
			foreach ( $meta['sizes'] as $size ) {
				if ( isset( $size['file'], $size['width'], $size['height'] ) ) {
					$candidates[] = array(
						'url'    => $dir . $size['file'],
						'width'  => (int) $size['width'],
						'height' => (int) $size['height'],
					);
				}
			}
		}
		$big = array_filter( $candidates, static fn( array $c ) => $c['width'] >= 1200 );
		if ( array() !== $big ) {
			usort( $big, static fn( array $a, array $b ) => $a['width'] <=> $b['width'] );
			$chosen = $big[0];
		} else {
			usort( $candidates, static fn( array $a, array $b ) => $b['width'] <=> $a['width'] );
			$chosen = $candidates[0];
			if ( $chosen['width'] < 600 ) {
				return null;
			}
		}
		$chosen['url'] = set_url_scheme( $chosen['url'], is_ssl() || 0 === strpos( home_url(), 'https:' ) ? 'https' : null );
		$chosen['alt'] = Text::plain( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		return $chosen;
	}
}
