<?php
/**
 * WordPress backed content provider.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Content;

use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Content\Model\CategoryNode;
use JOOservices\ThemeCore\Content\Model\Company;
use JOOservices\ThemeCore\Content\Model\CompanyPage;
use JOOservices\ThemeCore\Content\Model\ImageRef;
use JOOservices\ThemeCore\Content\Model\PostCard;
use JOOservices\ThemeCore\Content\Model\Profile;
use JOOservices\ThemeCore\Content\Model\Topic;
use JOOservices\ThemeCore\Performance\FragmentCache;
use JOOservices\ThemeCore\Seo\Text;
use JOOservices\ThemeCore\Support\Hooks;
use JOOservices\ThemeCore\Support\ReadingTime;

/**
 * Reads the CPT, term, user and post meta of the companion plugin (by key, without importing its classes), so any
 * plugin that follows the same meta contract works. Results are cached with tag invalidation.
 *
 * @api
 */
final class WpContentProvider implements ContentProviderInterface {

	private const TTL = 300;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Manifest.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Cache.
	 *
	 * @var FragmentCache
	 */
	private FragmentCache $cache;

	/**
	 * Creates the provider.
	 *
	 * @param Settings      $settings Settings.
	 * @param Manifest      $manifest Manifest.
	 * @param FragmentCache $cache    Cache.
	 */
	public function __construct( Settings $settings, Manifest $manifest, FragmentCache $cache ) {
		$this->settings = $settings;
		$this->manifest = $manifest;
		$this->cache    = $cache;
	}

	/**
	 * The owner's user id: setting, then manifest, then the first administrator (0 when none).
	 */
	public function ownerId(): int {
		foreach ( array( (int) $this->settings->get( 'content.profile_user_id', 0 ), (int) $this->manifest->get( 'seo.person.userId', 0 ) ) as $id ) {
			if ( $id > 0 && false !== get_userdata( $id ) ) {
				return $id;
			}
		}
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => 'ID',
			)
		);
		return array() === $admins ? 0 : (int) $admins[0];
	}

	/**
	 * Owner profile.
	 */
	public function profile(): ?Profile {
		$result = $this->cache->remember(
			'profile',
			self::TTL,
			function (): ?Profile {
				$id   = $this->ownerId();
				$user = $id > 0 ? get_userdata( $id ) : false;
				if ( false === $user ) {
					return null;
				}
				$bio     = (string) get_user_meta( $id, 'joo_short_bio', true );
				$bio     = '' !== $bio ? $bio : Text::truncate( Text::plain( (string) $user->description ), 240 );
				$avatar  = get_avatar_url( $id, array( 'size' => 256 ) );
				$socials = get_user_meta( $id, 'joo_socials', true );
				$url     = Hooks::filter( 'joo/profile/url', get_author_posts_url( $id ), $id );
				return new Profile(
					Text::plain( $user->display_name ),
					Text::plain( (string) get_user_meta( $id, 'joo_title', true ) ),
					$bio,
					false === $avatar ? '' : $avatar,
					is_string( $url ) ? $url : get_author_posts_url( $id ),
					is_array( $socials ) ? array_values( $socials ) : array(),
					$this->pinnedCompany()
				);
			},
			array( 'profile', 'companies' ),
			'content'
		);
		return $result instanceof Profile ? $result : null;
	}

	/**
	 * Pinned company.
	 */
	public function pinnedCompany(): ?Company {
		$result = $this->cache->remember(
			'pinned-company',
			self::TTL,
			function (): ?Company {
				$ids = $this->companyIds(
					array(
						'posts_per_page' => 1,
						'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'   => '_joo_company_pinned',
								'value' => '1',
							),
						),
					)
				);
				return array() === $ids ? null : $this->company( $ids[0] );
			},
			array( 'companies' ),
			'content'
		);
		return $result instanceof Company ? $result : null;
	}

	/**
	 * Companies, newest start first.
	 *
	 * @param int  $page          Page.
	 * @param int  $perPage       Per page.
	 * @param bool $excludePinned Exclude pinned.
	 */
	public function companies( int $page, int $perPage, bool $excludePinned = true ): CompanyPage {
		$page    = max( 1, $page );
		$perPage = max( 1, min( 20, $perPage ) );
		$result  = $this->cache->remember(
			sprintf( 'companies-%d-%d-%d', $page, $perPage, (int) $excludePinned ),
			self::TTL,
			function () use ( $page, $perPage, $excludePinned ): CompanyPage {
				$pinned = $excludePinned ? $this->pinnedCompany() : null;
				$args   = array(
					'posts_per_page' => $perPage,
					'paged'          => $page,
					'no_found_rows'  => false,
				);
				if ( null !== $pinned ) {
					$args['post__not_in'] = array( $pinned->id );
				}
				$query = new \WP_Query( $this->companyArgs( $args ) );
				$items = array();
				foreach ( $this->ids( $query->posts ) as $id ) {
					$items[] = $this->company( $id );
				}
				$total = (int) $query->found_posts;
				$pages = (int) $query->max_num_pages;
				if ( array() === $items && $page > 1 ) {
					// WordPress skips the count for empty pages: ask page 1 for the totals.
					$first = new \WP_Query( $this->companyArgs( array_merge( $args, array( 'paged' => 1 ) ) ) );
					$total = (int) $first->found_posts;
					$pages = (int) $first->max_num_pages;
				}
				return new CompanyPage( $items, $total, $pages );
			},
			array( 'companies' ),
			'content'
		);
		return $result instanceof CompanyPage ? $result : new CompanyPage( array(), 0, 0 );
	}

	/**
	 * Category tree.
	 *
	 * @param bool $hideEmpty Hide empty.
	 * @return list<CategoryNode>
	 */
	public function categoryTree( bool $hideEmpty = true ): array {
		$result = $this->cache->remember(
			'category-tree-' . (int) $hideEmpty,
			self::TTL,
			static function () use ( $hideEmpty ): array {
				$terms = get_terms(
					array(
						'taxonomy'   => 'category',
						'hide_empty' => false,
					)
				);
				$flat  = array();
				foreach ( is_array( $terms ) ? $terms : array() as $term ) {
					if ( ! $term instanceof \WP_Term ) {
						continue;
					}
					$link   = get_term_link( $term );
					$flat[] = array(
						'id'          => $term->term_id,
						'parent'      => $term->parent,
						'slug'        => $term->slug,
						'name'        => Text::plain( $term->name ),
						'description' => Text::plain( $term->description ),
						'short'       => '' !== (string) get_term_meta( $term->term_id, 'joo_short_description', true )
							? Text::plain( (string) get_term_meta( $term->term_id, 'joo_short_description', true ) )
							: Text::truncate( Text::plain( $term->description ), 120 ),
						'count'       => (int) $term->count,
						'url'         => is_string( $link ) ? $link : '',
					);
				}
				return TreeBuilder::build( $flat, $hideEmpty );
			},
			array( 'terms', 'posts' ),
			'content'
		);
		return is_array( $result ) ? array_values( $result ) : array();
	}

	/**
	 * Topics.
	 *
	 * @param int    $limit    Limit.
	 * @param string $strategy count|manual.
	 * @return list<Topic>
	 */
	public function topics( int $limit, string $strategy ): array {
		$limit  = max( 1, min( 20, $limit ) );
		$manual = array_values( array_filter( array_map( 'intval', (array) $this->settings->get( 'content.topics_manual', array() ) ) ) );
		$result = $this->cache->remember(
			sprintf( 'topics-%s-%d-%s', $strategy, $limit, md5( implode( ',', $manual ) ) ),
			self::TTL,
			function () use ( $limit, $strategy, $manual ): array {
				$args = array(
					'taxonomy'   => 'post_tag',
					'hide_empty' => true,
					'number'     => $limit,
					'orderby'    => 'count',
					'order'      => 'DESC',
				);
				if ( 'manual' === $strategy && array() !== $manual ) {
					$args['include'] = $manual;
					$args['orderby'] = 'include';
					$args['number']  = 0;
				}
				$terms = get_terms( $args );
				if ( 'manual' === $strategy && array() !== $manual && is_array( $terms ) ) {
					$position = array_flip( $manual );
					usort( $terms, static fn( $a, $b ) => ( $position[ $a->term_id ] ?? 0 ) <=> ( $position[ $b->term_id ] ?? 0 ) );
				}
				$out = array();
				foreach ( is_array( $terms ) ? $terms : array() as $term ) {
					if ( $term instanceof \WP_Term ) {
						$out[] = $this->topic( $term );
					}
					if ( count( $out ) >= $limit ) {
						break;
					}
				}
				return $out;
			},
			array( 'terms', 'posts', 'settings' ),
			'content'
		);
		return is_array( $result ) ? array_values( $result ) : array();
	}

	/**
	 * Featured posts (sticky posts when nothing is featured).
	 *
	 * @param int $limit Limit.
	 * @return list<PostCard>
	 */
	public function featured( int $limit ): array {
		$limit  = max( 1, min( 12, $limit ) );
		$result = $this->cache->remember(
			'featured-' . $limit,
			self::TTL,
			function () use ( $limit ): array {
				$ids = $this->postIds(
					array(
						'posts_per_page' => $limit,
						'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							'featured' => array(
								'key'   => '_joo_featured',
								'value' => '1',
							),
							'order'    => array(
								'key'     => '_joo_featured_order',
								'type'    => 'NUMERIC',
								'compare' => 'EXISTS',
							),
						),
						'orderby'        => array(
							'order' => 'ASC',
							'date'  => 'DESC',
						),
					)
				);
				if ( array() === $ids ) {
					$ids = $this->featuredWithoutOrder( $limit );
				}
				if ( array() === $ids ) {
					$sticky = array_map( 'intval', (array) get_option( 'sticky_posts', array() ) );
					$ids    = array() === $sticky ? array() : $this->postIds(
						array(
							'posts_per_page' => $limit,
							'post__in'       => $sticky,
							'orderby'        => 'date',
							'order'          => 'DESC',
						)
					);
				}
				return array_map( fn( int $id ) => $this->card( $id ), $ids );
			},
			array( 'posts' ),
			'content'
		);
		return is_array( $result ) ? array_values( $result ) : array();
	}

	/**
	 * Related posts.
	 *
	 * @param int $postId Post id.
	 * @param int $limit  Limit.
	 * @return list<PostCard>
	 */
	public function related( int $postId, int $limit ): array {
		$limit  = max( 1, min( 8, $limit ) );
		$result = $this->cache->remember(
			sprintf( 'related-%d-%d', $postId, $limit ),
			self::TTL,
			function () use ( $postId, $limit ): array {
				$post = get_post( $postId );
				if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
					return array();
				}
				$cats = $this->termIds( $postId, 'category' );
				$tags = $this->termIds( $postId, 'post_tag' );
				if ( array() === $cats && array() === $tags ) {
					return array();
				}
				$ancestors = array();
				foreach ( $cats as $cat ) {
					$ancestors[] = $cat;
					$ancestors   = array_merge( $ancestors, array_map( 'intval', get_ancestors( $cat, 'category', 'taxonomy' ) ) );
				}
				$source     = array(
					'primary'   => $this->primaryCategoryId( $postId, $cats ),
					'tags'      => $tags,
					'ancestors' => array_values( array_unique( $ancestors ) ),
				);
				$ids        = $this->postIds(
					array(
						'posts_per_page' => 60, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded candidate pool.
						'post__not_in'   => array( $postId ),
						'orderby'        => 'date',
						'order'          => 'DESC',
						'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
							'relation' => 'OR',
							array(
								'taxonomy' => 'category',
								'terms'    => array() === $cats ? array( 0 ) : $cats,
							),
							array(
								'taxonomy' => 'post_tag',
								'terms'    => array() === $tags ? array( 0 ) : $tags,
							),
						),
					)
				);
				$candidates = array();
				foreach ( $ids as $id ) {
					$candidates[] = array(
						'id'         => $id,
						'date'       => (int) get_post_timestamp( $id ),
						'categories' => $this->termIds( $id, 'category' ),
						'tags'       => $this->termIds( $id, 'post_tag' ),
					);
				}
				return array_map( fn( int $id ) => $this->card( $id ), RelatedScorer::rank( $source, $candidates, $limit ) );
			},
			array( 'posts', 'terms' ),
			'content'
		);
		return is_array( $result ) ? array_values( $result ) : array();
	}

	/**
	 * Featured posts that have the flag but no order value yet.
	 *
	 * @param int $limit Limit.
	 * @return list<int>
	 */
	private function featuredWithoutOrder( int $limit ): array {
		return $this->postIds(
			array(
				'posts_per_page' => $limit,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_joo_featured',
						'value' => '1',
					),
				),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * Published, non-protected post ids.
	 *
	 * @param array<string, mixed> $args Extra WP_Query args.
	 * @return list<int>
	 */
	private function postIds( array $args ): array {
		$query = new \WP_Query(
			array_merge(
				array(
					'post_type'           => 'post',
					'post_status'         => 'publish',
					'has_password'        => false,
					'fields'              => 'ids',
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
					'suppress_filters'    => false,
				),
				$args
			)
		);
		return $this->ids( $query->posts );
	}

	/**
	 * Published company ids.
	 *
	 * @param array<string, mixed> $args Extra WP_Query args.
	 * @return list<int>
	 */
	private function companyIds( array $args ): array {
		$query = new \WP_Query( $this->companyArgs( $args ) );
		return $this->ids( $query->posts );
	}

	/**
	 * WP_Query args for companies (published only, newest start first).
	 *
	 * @param array<string, mixed> $args Extra args.
	 * @return array<string, mixed>
	 */
	private function companyArgs( array $args ): array {
		return array_merge(
			array(
				'post_type'        => 'joo_company',
				'post_status'      => 'publish',
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'orderby'          => array(
					'from' => 'DESC',
					'ID'   => 'DESC',
				),
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'from' => array(
						'key'     => '_joo_company_from',
						'compare' => 'EXISTS',
					),
				),
			),
			array_filter(
				$args,
				static fn( $key ) => 'meta_query' !== $key,
				ARRAY_FILTER_USE_KEY
			),
			isset( $args['meta_query'] ) ? array(
				'meta_query' => array_merge( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'from' => array(
							'key'     => '_joo_company_from',
							'compare' => 'EXISTS',
						),
					),
					$args['meta_query']
				),
			) : array()
		);
	}

	/**
	 * Company value from a post id.
	 *
	 * @param int $id Post id.
	 */
	private function company( int $id ): Company {
		$to   = (string) get_post_meta( $id, '_joo_company_to', true );
		$logo = (int) get_post_meta( $id, '_joo_company_logo_id', true );
		return new Company(
			$id,
			Text::plain( get_the_title( $id ) ),
			Text::plain( (string) get_post_meta( $id, '_joo_company_role', true ) ),
			(string) get_post_meta( $id, '_joo_company_from', true ),
			$to,
			'' === $to,
			(bool) get_post_meta( $id, '_joo_company_pinned', true ),
			Text::plain( (string) get_post( $id )?->post_excerpt ),
			(string) get_post_meta( $id, '_joo_company_url', true ),
			$logo > 0 ? $this->image( $logo, 'thumbnail' ) : null
		);
	}

	/**
	 * Topic value from a term.
	 *
	 * @param \WP_Term $term Term.
	 */
	private function topic( \WP_Term $term ): Topic {
		$link  = get_term_link( $term );
		$intro = Text::plain( (string) get_term_meta( $term->term_id, 'joo_intro', true ) );
		$intro = '' !== $intro ? $intro : Text::truncate( Text::plain( $term->description ), 140 );
		$image = (int) get_term_meta( $term->term_id, 'joo_image_id', true );
		$ref   = $image > 0 ? $this->image( $image, 'thumbnail' ) : null;
		if ( null === $ref ) {
			$newest = $this->postIds(
				array(
					'posts_per_page' => 1,
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'post_tag',
							'terms'    => array( $term->term_id ),
						),
					),
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_thumbnail_id',
							'compare' => 'EXISTS',
						),
					),
				)
			);
			$thumb  = array() === $newest ? 0 : (int) get_post_thumbnail_id( $newest[0] );
			$ref    = $thumb > 0 ? $this->image( $thumb, 'thumbnail' ) : null;
		}
		return new Topic( $term->term_id, $term->slug, Text::plain( $term->name ), is_string( $link ) ? $link : '', (int) $term->count, $intro, $ref );
	}

	/**
	 * Post card.
	 *
	 * @param int $id Post id.
	 */
	private function card( int $id ): PostCard {
		$post  = get_post( $id );
		$cats  = get_the_category( $id );
		$thumb = (int) get_post_thumbnail_id( $id );
		$words = ReadingTime::words( Text::plain( $post instanceof \WP_Post ? $post->post_content : '' ) );
		$cat   = '';
		$prim  = $this->primaryCategoryId( $id, array_values( array_map( static fn( \WP_Term $t ) => $t->term_id, $cats ) ) );
		foreach ( $cats as $term ) {
			if ( $term->term_id === $prim ) {
				$cat = Text::plain( $term->name );
			}
		}
		$excerpt = $post instanceof \WP_Post ? ( '' !== $post->post_excerpt ? Text::plain( $post->post_excerpt ) : Text::summary( Text::plain( $post->post_content ), 160 ) ) : '';
		return new PostCard(
			$id,
			Text::plain( get_the_title( $id ) ),
			$excerpt,
			(string) get_permalink( $id ),
			(string) get_post_time( 'c', true, $id ),
			(string) get_post_modified_time( 'c', true, $id ),
			$cat,
			ReadingTime::minutes( $words ),
			$thumb > 0 ? $this->image( $thumb, 'joo-card' ) : null
		);
	}

	/**
	 * Integer ids from a WP_Query result.
	 *
	 * @param array<int|\WP_Post> $posts Query posts (ids because of `fields`).
	 * @return list<int>
	 */
	private function ids( array $posts ): array {
		$out = array();
		foreach ( $posts as $post ) {
			$out[] = $post instanceof \WP_Post ? $post->ID : (int) $post;
		}
		return $out;
	}

	/**
	 * Term ids of a post.
	 *
	 * @param int    $postId   Post id.
	 * @param string $taxonomy Taxonomy.
	 * @return list<int>
	 */
	private function termIds( int $postId, string $taxonomy ): array {
		$terms = wp_get_post_terms( $postId, $taxonomy, array( 'fields' => 'ids' ) );
		return is_wp_error( $terms ) ? array() : array_values( array_map( 'intval', $terms ) );
	}

	/**
	 * Primary category id: explicit meta, plugin value, else the first category.
	 *
	 * @param int       $postId Post id.
	 * @param list<int> $cats   Category ids of the post.
	 */
	private function primaryCategoryId( int $postId, array $cats ): int {
		$explicit = (int) get_post_meta( $postId, '_joo_primary_category', true );
		if ( $explicit > 0 && in_array( $explicit, $cats, true ) ) {
			return $explicit;
		}
		$plugin = (int) Hooks::filter( 'joo/seo/primary_category', 0, $postId );
		if ( $plugin > 0 && in_array( $plugin, $cats, true ) ) {
			return $plugin;
		}
		return $cats[0] ?? 0;
	}

	/**
	 * Image reference for an attachment (null when it is not an image).
	 *
	 * @param int          $id   Attachment id.
	 * @param string|array<int, int> $size Size.
	 */
	private function image( int $id, $size ): ?ImageRef {
		if ( ! wp_attachment_is_image( $id ) ) {
			return null;
		}
		$src = wp_get_attachment_image_src( $id, $size );
		if ( ! is_array( $src ) ) {
			return null;
		}
		return new ImageRef( (string) $src[0], (int) $src[1], (int) $src[2], Text::plain( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) );
	}
}
