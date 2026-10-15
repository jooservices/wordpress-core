<?php
/**
 * Document title builder.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo;

use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * Builds the document title from the per-post override or the template of the context.
 *
 * @api
 */
final class TitleBuilder {

	private const HARD_LIMIT = 70;

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
	 * Builds the title.
	 *
	 * @param Context $context Context.
	 */
	public function build( Context $context ): string {
		$post = $context->post();
		if ( null !== $post && ! $context->isRestricted() ) {
			$override = Text::plain( (string) get_post_meta( $post->ID, '_joo_seo_title', true ) );
			if ( '' !== $override ) {
				return $this->finish( Text::truncate( $override, self::HARD_LIMIT ), $context );
			}
		}
		$subject  = $this->subject( $context );
		$template = $this->template( $context );
		$title    = $this->render( $template, $subject, $context );
		if ( mb_strlen( $title, 'UTF-8' ) > self::HARD_LIMIT && '' !== $subject['title'] ) {
			$room  = max( 20, mb_strlen( $subject['title'], 'UTF-8' ) - ( mb_strlen( $title, 'UTF-8' ) - self::HARD_LIMIT ) );
			$title = $this->render( $template, array_merge( $subject, array( 'title' => Text::truncate( $subject['title'], $room ) ) ), $context );
		}
		return $this->finish( Text::truncate( $title, self::HARD_LIMIT ), $context );
	}

	/**
	 * Applies the public filter.
	 *
	 * @param string  $title   Title.
	 * @param Context $context Context.
	 */
	private function finish( string $title, Context $context ): string {
		$title = (string) Hooks::filter( 'joo/seo/title', $title, $context );
		return '' === trim( $title ) ? Text::plain( get_bloginfo( 'name' ) ) : $title;
	}

	/**
	 * Template for a context.
	 *
	 * @param Context $context Context.
	 */
	private function template( Context $context ): string {
		switch ( $context->type() ) {
			case 'front':
			case 'home':
				$key = 'home';
				break;
			case 'single':
			case 'attachment':
				$key = 'single';
				break;
			case 'page':
				$key = 'page';
				break;
			case 'search':
				$key = 'search';
				break;
			case '404':
				$key = '404';
				break;
			default:
				$key = 'archive';
		}
		return (string) $this->settings->get( 'seo.title_template.' . $key, '%title% %sep% %sitename%' );
	}

	/**
	 * Values for the template variables.
	 *
	 * @param Context $context Context.
	 * @return array{title: string, category: string, author: string}
	 */
	private function subject( Context $context ): array {
		$object   = $context->object();
		$title    = '';
		$category = '';
		$author   = '';
		if ( $object instanceof \WP_Post ) {
			$title  = Text::plain( get_the_title( $object ) );
			$author = Text::plain( get_the_author_meta( 'display_name', (int) $object->post_author ) );
			$terms  = 'post' === $object->post_type ? get_the_category( $object->ID ) : array();
			if ( array() !== $terms ) {
				$category = Text::plain( $terms[0]->name );
			}
		} elseif ( $object instanceof \WP_Term ) {
			$title = Text::plain( $object->name );
		} elseif ( $object instanceof \WP_User ) {
			$title  = Text::plain( $object->display_name );
			$author = $title;
		} elseif ( 'search' === $context->type() ) {
			$title = Text::plain( get_search_query( false ) );
		} elseif ( 'date' === $context->type() ) {
			$title = Text::plain( (string) get_the_archive_title() );
		} elseif ( '404' === $context->type() ) {
			$title = 'Page not found';
		} elseif ( 'archive' === $context->type() ) {
			$title = Text::plain( (string) get_the_archive_title() );
		}
		return array(
			'title'    => $title,
			'category' => $category,
			'author'   => $author,
		);
	}

	/**
	 * Replaces variables; unknown ones are removed.
	 *
	 * @param string                                                  $template Template.
	 * @param array{title: string, category: string, author: string} $subject  Values.
	 * @param Context                                                 $context  Context.
	 */
	private function render( string $template, array $subject, Context $context ): string {
		$page = '';
		if ( $context->paged() > 1 ) {
			global $wp_query;
			$total = isset( $wp_query ) && $wp_query->max_num_pages > 0 ? (int) $wp_query->max_num_pages : 0;
			$page  = $total > 0 ? sprintf( 'Page %1$d of %2$d', $context->paged(), $total ) : sprintf( 'Page %d', $context->paged() );
		}
		$map = array(
			'%title%'    => $subject['title'],
			'%sitename%' => Text::plain( get_bloginfo( 'name' ) ),
			'%tagline%'  => Text::plain( get_bloginfo( 'description' ) ),
			'%category%' => $subject['category'],
			'%author%'   => $subject['author'],
			'%page%'     => $page,
			'%sep%'      => (string) $this->settings->get( 'seo.title_separator', '|' ),
		);
		$out = strtr( $template, $map );
		$out = (string) preg_replace( '/%[a-z_]+%/', '', $out );
		$sep = preg_quote( (string) $map['%sep%'], '/' );
		$out = (string) preg_replace( '/\s+/u', ' ', $out );
		// Drop separators left dangling by empty variables ("Title |" or "| Site").
		$out = (string) preg_replace( '/^(?:\s*' . $sep . ')+\s*|\s*(?:' . $sep . '\s*)+$/u', '', $out );
		$out = (string) preg_replace( '/(' . $sep . ')\s*(?:' . $sep . '\s*)+/u', '$1 ', $out );
		return trim( $out );
	}
}
