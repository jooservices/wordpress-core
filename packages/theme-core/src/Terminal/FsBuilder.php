<?php
/**
 * Virtual file system of the terminal.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Terminal;

use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Content\WpContentProvider;
use JOOservices\ThemeCore\I18n\Languages;
use JOOservices\ThemeCore\Performance\FragmentCache;
use JOOservices\ThemeCore\Seo\Text;
use JOOservices\ThemeCore\Support\Hooks;
use JOOservices\ThemeCore\Support\ReadingTime;

/**
 * Builds the JSON tree of published content: categories are directories, posts are `.md` files in their primary
 * category (links elsewhere), tags are virtual directories of links, pages, authors, README and `.config`.
 * Drafts, private and password protected content never appear.
 *
 * @api
 */
final class FsBuilder {

	public const MAX_BYTES = 400000;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Cache.
	 *
	 * @var FragmentCache
	 */
	private FragmentCache $cache;

	/**
	 * Languages.
	 *
	 * @var Languages
	 */
	private Languages $languages;

	/**
	 * Content provider (owner lookup).
	 *
	 * @var WpContentProvider
	 */
	private WpContentProvider $provider;

	/**
	 * Creates the builder.
	 *
	 * @param Settings          $settings  Settings.
	 * @param FragmentCache     $cache     Cache.
	 * @param Languages         $languages Languages.
	 * @param WpContentProvider $provider  Provider.
	 */
	public function __construct( Settings $settings, FragmentCache $cache, Languages $languages, WpContentProvider $provider ) {
		$this->settings  = $settings;
		$this->cache     = $cache;
		$this->languages = $languages;
		$this->provider  = $provider;
	}

	/**
	 * The tree for a language (cached).
	 *
	 * @param string $lang Language code ('' = all languages).
	 * @return array<string, mixed>
	 */
	public function build( string $lang = '' ): array {
		$lang = $this->languages->isMultilingual() && in_array( $lang, $this->languages->codes(), true ) ? $lang : '';
		$max  = max( 10, min( 2000, (int) $this->settings->get( 'terminal.max_posts', 500 ) ) );
		$data = $this->cache->remember( sprintf( 'fs-%s-%d', '' === $lang ? 'all' : $lang, $max ), 300, fn(): array => $this->compute( $lang, $max ), array( 'posts', 'terms', 'profile' ), 'terminal' );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Computes the tree.
	 *
	 * @param string $lang Language ('' = all).
	 * @param int    $max  Maximum posts.
	 * @return array<string, mixed>
	 */
	private function compute( string $lang, int $max ): array {
		$dirs  = $this->categoryDirs();
		$nodes = array();
		foreach ( $dirs as $dir ) {
			$nodes[] = array(
				'p'   => $dir['path'],
				't'   => 'd',
				'cat' => $dir['name'],
				'url' => $dir['url'],
			);
		}
		$taken = array();
		$tags  = array();
		foreach ( $this->postIds( $lang, $max ) as $id ) {
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$catIds  = wp_get_post_terms( $id, 'category', array( 'fields' => 'ids' ) );
			$cats    = is_wp_error( $catIds ) ? array() : array_values( array_map( 'intval', $catIds ) );
			$primary = $this->primary( $id, $cats );
			$base    = isset( $dirs[ $primary ] ) ? $dirs[ $primary ]['path'] : '';
			$file    = $this->fileName( $base, $post, $taken );
			$path    = $base . '/' . $file;
			$tagList = array_map( static fn( \WP_Term $t ) => $t->slug, array_filter( (array) get_the_tags( $id ), static fn( $t ) => $t instanceof \WP_Term ) );
			$nodes[] = array(
				'p'     => $path,
				't'     => 'f',
				'id'    => $id,
				'title' => Text::plain( get_the_title( $post ) ),
				'ex'    => $this->excerpt( $post ),
				'd'     => (string) get_post_time( 'Y-m-d', true, $post ),
				'u'     => (string) get_post_modified_time( 'Y-m-d', true, $post ),
				'm'     => max( 1, ReadingTime::forPost( $id )['minutes'] ),
				'tg'    => $tagList,
				'url'   => (string) get_permalink( $post ),
				'c'     => isset( $dirs[ $primary ] ) ? $dirs[ $primary ]['name'] : '',
			);
			foreach ( $cats as $other ) {
				if ( $other !== $primary && isset( $dirs[ $other ] ) ) {
					$nodes[] = array(
						'p'  => $dirs[ $other ]['path'] . '/' . $file,
						't'  => 'l',
						'to' => $path,
					);
				}
			}
			foreach ( $tagList as $slug ) {
				$tags[ $slug ][] = array( $file, $path );
			}
		}
		$nodes   = array_merge( $nodes, $this->tagNodes( $tags ), $this->pageNodes(), $this->authorNodes() );
		$nodes[] = array(
			'p'    => '/README.md',
			't'    => 'f',
			'text' => Text::plain( get_bloginfo( 'name' ) ) . ' - ' . Text::plain( get_bloginfo( 'description' ) ) . "\n\n" . (string) $this->settings->get( 'terminal.banner', 'read-only shell' ) . "\nCategories are directories, posts are .md files, tags are virtual directories of links. Nothing you type leaves this page.",
		);
		$nodes[] = array(
			'p'      => '/.config',
			't'      => 'f',
			'hidden' => true,
			'text'   => "readonly=true\nlang=" . ( '' !== $lang ? $lang : $this->languages->default() ),
		);
		$data    = array(
			'v'         => 1,
			'lang'      => '' !== $lang ? $lang : $this->languages->default(),
			'generated' => gmdate( 'c' ),
			'site'      => $this->site(),
			'nodes'     => $nodes,
		);
		return $this->capSize( $data );
	}

	/**
	 * Site information for the prompt and neofetch.
	 *
	 * @return array<string, string>
	 */
	private function site(): array {
		$owner = $this->provider->ownerId();
		$user  = $owner > 0 ? get_userdata( $owner ) : false;
		$first = false === $user ? '' : strtolower( (string) preg_replace( '/[^a-z0-9]/i', '', explode( ' ', remove_accents( (string) $user->display_name ) )[0] ) );
		$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return array(
			'name' => Text::plain( get_bloginfo( 'name' ) ),
			'home' => home_url( '/' ),
			'user' => '' !== $first ? $first : 'visitor',
			'host' => '' !== $host ? (string) preg_replace( '/^www\./', '', $host ) : 'localhost',
		);
	}

	/**
	 * Category directories by term id: path, name, url (only categories that have published posts below them).
	 *
	 * @return array<int, array{path: string, name: string, url: string}>
	 */
	private function categoryDirs(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
			)
		);
		$terms = is_array( $terms ) ? array_filter( $terms, static fn( $t ) => $t instanceof \WP_Term ) : array();
		$by    = array();
		foreach ( $terms as $term ) {
			$by[ $term->term_id ] = $term;
		}
		$count = static function ( int $id ) use ( &$count, $by ): int {
			$sum = (int) $by[ $id ]->count;
			foreach ( $by as $child ) {
				if ( $child->parent === $id ) {
					$sum += $count( $child->term_id );
				}
			}
			return $sum;
		};
		$out   = array();
		foreach ( $by as $id => $term ) {
			if ( $count( $id ) < 1 ) {
				continue;
			}
			$chain = array( $term->slug );
			for ( $p = $term->parent, $guard = 0; $p > 0 && isset( $by[ $p ] ) && $guard < 10; ++$guard ) {
				array_unshift( $chain, $by[ $p ]->slug );
				$p = $by[ $p ]->parent;
			}
			$link       = get_term_link( $term );
			$out[ $id ] = array(
				'path' => '/' . implode( '/', $chain ),
				'name' => Text::plain( $term->name ),
				'url'  => is_string( $link ) ? $link : '',
			);
		}
		return $out;
	}

	/**
	 * Published post ids (newest first), limited to a language when asked.
	 *
	 * @param string $lang Language ('' = all).
	 * @param int    $max  Maximum.
	 * @return list<int>
	 */
	private function postIds( string $lang, int $max ): array {
		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'has_password'        => false,
			'posts_per_page'      => $max,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'fields'              => 'ids',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		);
		if ( '' !== $lang ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'   => '_joo_lang',
					'value' => $lang,
				),
				array(
					'key'     => '_joo_lang',
					'compare' => 'NOT EXISTS',
				),
			);
		}
		$query = new \WP_Query( $args );
		$ids   = array();
		foreach ( $query->posts as $post ) {
			$ids[] = $post instanceof \WP_Post ? $post->ID : (int) $post;
		}
		return $ids;
	}

	/**
	 * Primary category: explicit meta, plugin value, else the first category.
	 *
	 * @param int       $postId Post id.
	 * @param list<int> $cats   Category ids.
	 */
	private function primary( int $postId, array $cats ): int {
		foreach ( array( (int) get_post_meta( $postId, '_joo_primary_category', true ), (int) Hooks::filter( 'joo/seo/primary_category', 0, $postId ) ) as $candidate ) {
			if ( $candidate > 0 && in_array( $candidate, $cats, true ) ) {
				return $candidate;
			}
		}
		return $cats[0] ?? 0;
	}

	/**
	 * File name `slug.md`; collisions inside a directory become `slug-<id>.md`.
	 *
	 * @param string            $dir   Directory path.
	 * @param \WP_Post          $post  Post.
	 * @param array<string,int> $taken Names used so far (updated).
	 */
	private function fileName( string $dir, \WP_Post $post, array &$taken ): string {
		$slug = '' !== $post->post_name ? $post->post_name : 'post-' . $post->ID;
		$name = $slug . '.md';
		if ( isset( $taken[ $dir . '/' . $name ] ) ) {
			$name = $slug . '-' . $post->ID . '.md';
		}
		$taken[ $dir . '/' . $name ] = $post->ID;
		return $name;
	}

	/**
	 * Plain 200 character excerpt.
	 *
	 * @param \WP_Post $post Post.
	 */
	private function excerpt( \WP_Post $post ): string {
		$source = '' !== $post->post_excerpt ? $post->post_excerpt : $post->post_content;
		return Text::truncate( Text::plain( $source ), 200 );
	}

	/**
	 * `/tags/<slug>/` directories with links to the posts.
	 *
	 * @param array<string, list<array{0: string, 1: string}>> $tags Tag slug => [file, path].
	 * @return list<array<string, mixed>>
	 */
	private function tagNodes( array $tags ): array {
		$nodes = array(
			array(
				'p' => '/tags',
				't' => 'd',
			),
		);
		ksort( $tags );
		foreach ( $tags as $slug => $files ) {
			$term    = get_term_by( 'slug', (string) $slug, 'post_tag' );
			$link    = $term instanceof \WP_Term ? get_term_link( $term ) : '';
			$nodes[] = array(
				'p'   => '/tags/' . $slug,
				't'   => 'd',
				'tag' => (string) $slug,
				'url' => is_string( $link ) ? $link : '',
			);
			foreach ( $files as $row ) {
				$nodes[] = array(
					'p'  => '/tags/' . $slug . '/' . $row[0],
					't'  => 'l',
					'to' => $row[1],
				);
			}
		}
		return $nodes;
	}

	/**
	 * Published top-level pages under `/pages`.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function pageNodes(): array {
		$ids   = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => 'publish',
				'has_password'     => false,
				'post_parent'      => 0,
				'posts_per_page'   => 50, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded.
				'fields'           => 'ids',
				'orderby'          => 'menu_order title',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
		$nodes = array();
		foreach ( $ids as $id ) {
			$page = get_post( (int) $id );
			if ( ! $page instanceof \WP_Post || '' === $page->post_name ) {
				continue;
			}
			$nodes[] = array(
				'p'     => '/pages/' . $page->post_name . '.md',
				't'     => 'f',
				'page'  => $page->post_name,
				'title' => Text::plain( get_the_title( $page ) ),
				'ex'    => $this->excerpt( $page ),
				'url'   => (string) get_permalink( $page ),
			);
		}
		return $nodes;
	}

	/**
	 * Authors with published posts under `/authors`.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function authorNodes(): array {
		$users = get_users(
			array(
				'has_published_posts' => array( 'post' ),
				'number'              => 50,
				'orderby'             => 'display_name',
			)
		);
		$nodes = array();
		foreach ( $users as $user ) {
			$bio     = Text::plain( (string) get_user_meta( $user->ID, 'joo_short_bio', true ) );
			$bio     = '' !== $bio ? $bio : Text::plain( (string) $user->description );
			$nodes[] = array(
				'p'     => '/authors/' . $user->user_nicename . '.md',
				't'     => 'f',
				'page'  => 'author',
				'title' => Text::plain( $user->display_name ),
				'text'  => Text::plain( $user->display_name ) . "\n" . Text::truncate( $bio, 240 ),
				'url'   => get_author_posts_url( $user->ID ),
			);
		}
		return $nodes;
	}

	/**
	 * Drops the oldest posts until the JSON fits the size cap.
	 *
	 * @param array<string, mixed> $data Tree.
	 * @return array<string, mixed>
	 */
	private function capSize( array $data ): array {
		$size = static fn( array $d ): int => strlen( (string) wp_json_encode( $d ) );
		if ( $size( $data ) <= self::MAX_BYTES ) {
			return $data;
		}
		$data['truncated'] = true;
		$posts             = array();
		foreach ( $data['nodes'] as $i => $node ) {
			if ( 'f' === $node['t'] && isset( $node['id'] ) ) {
				$posts[ $i ] = (string) $node['d'];
			}
		}
		asort( $posts );
		foreach ( array_keys( $posts ) as $i ) {
			if ( $size( $data ) <= self::MAX_BYTES ) {
				break;
			}
			$path = $data['nodes'][ $i ]['p'];
			unset( $data['nodes'][ $i ] );
			foreach ( $data['nodes'] as $j => $node ) {
				if ( 'l' === $node['t'] && ( $node['to'] ?? '' ) === $path ) {
					unset( $data['nodes'][ $j ] );
				}
			}
		}
		$data['nodes'] = array_values( $data['nodes'] );
		return $data;
	}
}
