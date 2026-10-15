<?php
/**
 * Breadcrumb trail.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo\Breadcrumbs;

use JOOservices\ThemeCore\Seo\Context;
use JOOservices\ThemeCore\Seo\Text;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * Single source of the trail for the visible block and the BreadcrumbList node.
 *
 * @api
 */
final class TrailBuilder {

	/**
	 * Builds the trail (the last crumb is the current page and has no URL).
	 *
	 * @param Context $context Context.
	 * @return list<Crumb>
	 */
	public function build( Context $context ): array {
		$trail = array( new Crumb( __( 'Home', 'joo-core' ), home_url( '/' ) ) );
		$trail = array_merge( $trail, $this->path( $context ) );
		$last  = array_pop( $trail );
		if ( null !== $last ) {
			$trail[] = new Crumb( $last->label, null );
		}
		$filtered = Hooks::filter( 'joo/breadcrumbs/trail', $trail, $context );
		if ( ! is_array( $filtered ) ) {
			return $trail;
		}
		return array_values( array_filter( $filtered, static fn( $c ) => $c instanceof Crumb ) );
	}

	/**
	 * Visible labels (truncated, page suffix on paged views).
	 *
	 * @param list<Crumb> $trail   Trail.
	 * @param Context     $context Context.
	 * @return list<Crumb>
	 */
	public function forDisplay( array $trail, Context $context ): array {
		$out   = array();
		$count = count( $trail );
		foreach ( $trail as $i => $crumb ) {
			$label = Text::truncate( $crumb->label, 50 );
			if ( $i === $count - 1 && $context->paged() > 1 && ! $context->isSingular() ) {
				/* translators: %d: page number. */
				$label .= ' ' . sprintf( __( '(Page %d)', 'joo-core' ), $context->paged() );
			}
			$out[] = new Crumb( $label, $crumb->url );
		}
		return $out;
	}

	/**
	 * Schema ListItems (positions from 1; the last item has no `item`).
	 *
	 * @param list<Crumb> $trail Trail.
	 * @return list<array<string, mixed>>
	 */
	public function toSchemaList( array $trail ): array {
		$items = array();
		$count = count( $trail );
		foreach ( $trail as $i => $crumb ) {
			$item = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $crumb->label,
			);
			if ( null !== $crumb->url && $i < $count - 1 ) {
				$item['item'] = $crumb->url;
			}
			$items[] = $item;
		}
		return $items;
	}

	/**
	 * Crumbs after Home.
	 *
	 * @param Context $context Context.
	 * @return list<Crumb>
	 */
	private function path( Context $context ): array {
		$object = $context->object();
		$type   = $context->type();
		if ( 'front' === $type ) {
			return array();
		}
		if ( 'home' === $type ) {
			$blog  = (int) get_option( 'page_for_posts' );
			$label = $blog > 0 ? Text::plain( get_the_title( $blog ) ) : '';
			return array( new Crumb( '' !== $label ? $label : __( 'Blog', 'joo-core' ) ) );
		}
		if ( $object instanceof \WP_Post ) {
			return $this->postPath( $object );
		}
		if ( $object instanceof \WP_Term ) {
			return $this->termPath( $object );
		}
		if ( $object instanceof \WP_User ) {
			return array(
				new Crumb( __( 'Authors', 'joo-core' ) ),
				new Crumb( Text::plain( $object->display_name ) ),
			);
		}
		if ( 'date' === $type ) {
			$out = array();
			if ( is_year() || is_month() || is_day() ) {
				$year  = (int) get_query_var( 'year' );
				$month = (int) get_query_var( 'monthnum' );
				$out[] = new Crumb( (string) $year, get_year_link( $year ) );
				if ( $month > 0 ) {
					$out[] = new Crumb( (string) wp_date( 'F', (int) mktime( 0, 0, 0, $month, 1, $year ) ), get_month_link( $year, $month ) );
				}
			}
			$last = array_pop( $out );
			return null === $last ? array( new Crumb( __( 'Archive', 'joo-core' ) ) ) : array_merge( $out, array( $last ) );
		}
		if ( 'search' === $type ) {
			/* translators: %s: search query. */
			return array( new Crumb( sprintf( __( 'Search results for "%s"', 'joo-core' ), Text::plain( get_search_query( false ) ) ) ) );
		}
		if ( '404' === $type ) {
			return array( new Crumb( __( 'Page not found', 'joo-core' ) ) );
		}
		return array( new Crumb( Text::plain( (string) get_the_archive_title() ) ) );
	}

	/**
	 * Post or page ancestry.
	 *
	 * @param \WP_Post $post Post.
	 * @return list<Crumb>
	 */
	private function postPath( \WP_Post $post ): array {
		$out = array();
		if ( 'page' === $post->post_type ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $id ) {
				$out[] = new Crumb( Text::plain( get_the_title( $id ) ), (string) get_permalink( $id ) );
			}
		} elseif ( 'post' === $post->post_type ) {
			$primary = $this->primaryCategory( $post );
			if ( null !== $primary ) {
				$out = $this->termPath( $primary );
			}
		}
		$out[] = new Crumb( Text::plain( get_the_title( $post ) ), (string) get_permalink( $post ) );
		return $out;
	}

	/**
	 * Primary category: explicit meta, else deepest, else first by name.
	 *
	 * @param \WP_Post $post Post.
	 */
	private function primaryCategory( \WP_Post $post ): ?\WP_Term {
		$terms = get_the_category( $post->ID );
		if ( array() === $terms ) {
			return null;
		}
		$explicit = (int) get_post_meta( $post->ID, '_joo_primary_category', true );
		foreach ( $terms as $term ) {
			if ( $term->term_id === $explicit ) {
				return $term;
			}
		}
		$plugin = (int) Hooks::filter( 'joo/seo/primary_category', 0, $post->ID );
		foreach ( $terms as $term ) {
			if ( $plugin > 0 && $term->term_id === $plugin ) {
				return $term;
			}
		}
		$best  = null;
		$depth = -1;
		foreach ( $terms as $term ) {
			$d = count( get_ancestors( $term->term_id, 'category', 'taxonomy' ) );
			if ( $d > $depth || ( $d === $depth && null !== $best && strcasecmp( $term->name, $best->name ) < 0 ) ) {
				$best  = $term;
				$depth = $d;
			}
		}
		return $best;
	}

	/**
	 * Term ancestry (root to leaf). Tags get a label-only "Tags" parent.
	 *
	 * @param \WP_Term $term Term.
	 * @return list<Crumb>
	 */
	private function termPath( \WP_Term $term ): array {
		$out = array();
		if ( 'post_tag' === $term->taxonomy ) {
			$out[] = new Crumb( __( 'Tags', 'joo-core' ) );
		}
		foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $id ) {
			$parent = get_term( $id, $term->taxonomy );
			$link   = $parent instanceof \WP_Term ? get_term_link( $parent ) : null;
			if ( $parent instanceof \WP_Term && is_string( $link ) ) {
				$out[] = new Crumb( Text::plain( $parent->name ), $link );
			}
		}
		$link  = get_term_link( $term );
		$out[] = new Crumb( Text::plain( $term->name ), is_string( $link ) ? $link : null );
		return $out;
	}
}
