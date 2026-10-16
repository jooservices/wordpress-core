<?php
/**
 * Content provider and public content routes (real WordPress, JOOservices Resume loaded).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\Resume\PostTypes\Company;
use JOOservices\TestKit\Factories\AttachmentFactory;
use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Content\ContentProviderInterface;
use JOOservices\ThemeCore\Content\NullProvider;
use JOOservices\ThemeCore\Content\WpContentProvider;
use JOOservices\ThemeCore\Kernel;

final class ContentTest extends IntegrationTestCase {

	private const MANIFEST = '/workspace/packages/theme-core/tests/Fixtures/manifests/perf.json';

	public function set_up(): void {
		parent::set_up();
		Kernel::reset();
		Notices::reset();
		$GLOBALS['wp_rest_server'] = null;
		$this->set_permalink_structure( '/%postname%/' );
		create_initial_taxonomies();
		$GLOBALS['wp_rewrite']->flush_rules();
		wp_cache_flush();
	}

	public function tear_down(): void {
		Kernel::reset();
		$GLOBALS['wp_rest_server'] = null;
		if ( ! post_type_exists( 'joo_company' ) ) {
			( new Company() )->register();
		}
		parent::tear_down();
	}

	private function boot(): ContentProviderInterface {
		Kernel::boot( self::MANIFEST );
		do_action( 'after_setup_theme' );
		return Kernel::container()->get( ContentProviderInterface::class );
	}

	private function company( string $name, string $from, string $to = '', bool $pinned = false, string $status = 'publish' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'joo_company',
				'post_title'   => $name,
				'post_status'  => $status,
				'post_excerpt' => $name . ' description',
			)
		);
		update_post_meta( $id, '_joo_company_role', 'Engineer at ' . $name );
		update_post_meta( $id, '_joo_company_from', $from );
		update_post_meta( $id, '_joo_company_to', $to );
		update_post_meta( $id, '_joo_company_url', 'https://' . strtolower( $name ) . '.test' );
		if ( $pinned ) {
			update_post_meta( $id, '_joo_company_pinned', true );
		}
		return $id;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get( string $route, array $params = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'GET', '/joo/v1' . $route );
		$request->set_query_params( $params );
		return rest_do_request( $request );
	}

	public function test_the_wp_provider_is_used_when_the_companion_plugin_registered_its_type(): void {
		$this->assertInstanceOf( WpContentProvider::class, $this->boot() );
	}

	public function test_without_the_plugin_type_the_null_provider_returns_empty_answers_everywhere(): void {
		unregister_post_type( 'joo_company' );
		$provider = $this->boot();
		$this->assertInstanceOf( NullProvider::class, $provider );
		$this->assertNull( $provider->profile() );
		$this->assertNull( $provider->pinnedCompany() );
		$this->assertSame( 0, $provider->companies( 1, 5 )->total );
		$this->assertSame( array(), $provider->categoryTree() );
		$this->assertSame( array(), $provider->topics( 4, 'count' ) );
		$this->assertSame( array(), $provider->featured( 5 ) );
		$this->assertSame( array(), $provider->related( 1, 3 ) );
		foreach ( array( '/companies', '/categories/tree', '/topics', '/featured', '/related/1' ) as $route ) {
			$response = $this->get( $route );
			$this->assertSame( 200, $response->get_status(), $route );
			$this->assertSame( array(), $response->get_data(), $route );
		}
		$this->assertSame( array(), $this->get( '/profile' )->get_data() );
	}

	public function test_companies_are_published_only_newest_first_paginated_and_the_pinned_one_is_separate(): void {
		$provider = $this->boot();
		$old      = $this->company( 'Oldco', '2015-01', '2018-06' );
		$mid      = $this->company( 'Midco', '2018-07', '2021-12' );
		$new      = $this->company( 'Newco', '2022-03', '', true );
		$this->company( 'Draftco', '2023-01', '', false, 'draft' );
		$this->company( 'Privateco', '2024-01', '', false, 'private' );
		$this->assertSame( $new, $provider->pinnedCompany()?->id );
		$page = $provider->companies( 1, 10 );
		$this->assertSame( array( $mid, $old ), array_map( static fn( $c ) => $c->id, $page->items ), 'pinned excluded, newest start first' );
		$this->assertSame( 2, $page->total );
		$all = $provider->companies( 1, 10, false );
		$this->assertSame( array( $new, $mid, $old ), array_map( static fn( $c ) => $c->id, $all->items ) );
		$first = $provider->companies( 1, 1, false );
		$this->assertSame( 3, $first->total );
		$this->assertSame( 3, $first->totalPages );
		$this->assertSame( array( $mid ), array_map( static fn( $c ) => $c->id, $provider->companies( 2, 1, false )->items ) );
		$this->assertSame( array(), $provider->companies( 9, 1, false )->items, 'out of range gives an empty page' );
		$this->assertTrue( $all->items[0]->current );
		$this->assertFalse( $all->items[1]->current );
		$this->assertSame( 'https://newco.test', $all->items[0]->url );
	}

	public function test_profile_combines_user_fields_and_the_pinned_company(): void {
		$provider = $this->boot();
		wp_update_user(
			array(
				'ID'           => 1,
				'display_name' => 'Viet Vu',
				'description'  => 'A long biography that is only used when no short bio exists.',
			)
		);
		update_user_meta( 1, 'joo_title', 'Software Engineer' );
		update_user_meta(
			1,
			'joo_socials',
			array(
				array(
					'label' => 'GitHub',
					'url'   => 'https://github.com/example',
				),
			)
		);
		$this->company( 'Newco', '2022-03', '', true );
		wp_cache_flush();
		$profile = $this->boot_fresh()->profile();
		$this->assertNotNull( $profile );
		$this->assertSame( 'Viet Vu', $profile->name );
		$this->assertSame( 'Software Engineer', $profile->title );
		$this->assertStringStartsWith( 'A long biography', $profile->shortBio, 'falls back to the user description' );
		$this->assertSame( 'GitHub', $profile->socials[0]['label'] );
		$this->assertSame( 'Newco', $profile->pinnedCompany?->name );
		update_user_meta( 1, 'joo_short_bio', 'Short one.' );
		wp_cache_flush();
		$this->assertSame( 'Short one.', $this->boot_fresh()->profile()?->shortBio );
		unset( $provider );
	}

	private function boot_fresh(): ContentProviderInterface {
		Kernel::reset();
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_joo:%' OR option_name LIKE '\\_transient\\_timeout\\_joo:%'" );
		wp_cache_flush();
		return $this->boot();
	}

	public function test_category_tree_has_cumulative_counts_and_short_description_fallbacks(): void {
		$rd  = self::factory()->category->create(
			array(
				'name'        => 'Research',
				'description' => 'Long research description that is quite long indeed.',
			)
		);
		$lab = self::factory()->category->create(
			array(
				'name'   => 'Homelab',
				'parent' => $rd,
			)
		);
		self::factory()->category->create( array( 'name' => 'Empty' ) );
		self::factory()->post->create_many( 2, array( 'post_category' => array( $lab ) ) );
		self::factory()->post->create( array( 'post_category' => array( $rd ) ) );
		self::factory()->post->create(
			array(
				'post_status'   => 'draft',
				'post_category' => array( $lab ),
			)
		);
		update_term_meta( $lab, 'joo_short_description', 'Servers at home' );
		$tree  = $this->boot_fresh()->categoryTree();
		$names = array_map( static fn( $n ) => $n->name, $tree );
		$this->assertContains( 'Research', $names );
		$this->assertNotContains( 'Empty', $names );
		$research = $tree[ array_search( 'Research', $names, true ) ];
		$this->assertSame( 3, $research->count );
		$this->assertSame( 'Homelab', $research->children[0]->name );
		$this->assertSame( 2, $research->children[0]->count, 'drafts are not counted' );
		$this->assertSame( 'Servers at home', $research->children[0]->shortDescription );
		$this->assertStringStartsWith( 'Long research', $research->shortDescription );
		$withEmpty = $this->boot_fresh()->categoryTree( false );
		$this->assertContains( 'Empty', array_map( static fn( $n ) => $n->name, $withEmpty ) );
	}

	public function test_topics_by_count_and_manual_with_image_and_intro_fallbacks(): void {
		$big   = self::factory()->tag->create(
			array(
				'name'        => 'Big',
				'description' => 'Big topic description.',
			)
		);
		$small = self::factory()->tag->create( array( 'name' => 'Small' ) );
		self::factory()->tag->create( array( 'name' => 'Unused' ) );
		$thumb = AttachmentFactory::image( 400, 300 );
		$posts = self::factory()->post->create_many( 3 );
		foreach ( $posts as $id ) {
			wp_set_post_tags( $id, array( 'Big' ) );
		}
		wp_set_post_tags( $posts[0], array( 'Big', 'Small' ) );
		set_post_thumbnail( $posts[2], $thumb );
		update_term_meta( $small, 'joo_intro', 'Hand written intro' );
		$topics = $this->boot_fresh()->topics( 5, 'count' );
		$this->assertSame( array( 'Big', 'Small' ), array_map( static fn( $t ) => $t->name, $topics ), 'by count, empty tags hidden' );
		$this->assertSame( 3, $topics[0]->count );
		$this->assertSame( 'Big topic description.', $topics[0]->intro, 'falls back to the description' );
		$this->assertNotNull( $topics[0]->image, 'falls back to the newest post image' );
		$this->assertSame( 'Hand written intro', $topics[1]->intro );
		$this->assertCount( 1, $this->boot_fresh()->topics( 1, 'count' ) );

		add_filter( 'joo/settings/value', static fn( $v, $key ) => 'content.topics_manual' === $key ? array( $small, $big ) : $v, 10, 2 );
		$manual = $this->boot_fresh()->topics( 5, 'manual' );
		$this->assertSame( array( 'Small', 'Big' ), array_map( static fn( $t ) => $t->name, $manual ), 'manual order' );
	}

	public function test_featured_orders_by_flag_order_and_hides_unpublished_and_protected_posts(): void {
		$a     = self::factory()->post->create(
			array(
				'post_title' => 'A',
				'post_date'  => '2024-01-01 00:00:00',
			)
		);
		$b     = self::factory()->post->create(
			array(
				'post_title' => 'B',
				'post_date'  => '2024-02-01 00:00:00',
			)
		);
		$c     = self::factory()->post->create(
			array(
				'post_title' => 'C',
				'post_date'  => '2024-03-01 00:00:00',
			)
		);
		$draft = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$pw    = self::factory()->post->create( array( 'post_password' => 'x' ) );
		foreach ( array(
			$a     => 2,
			$b     => 1,
			$c     => 1,
			$draft => 0,
			$pw    => 0,
		) as $id => $order ) {
			update_post_meta( $id, '_joo_featured', true );
			update_post_meta( $id, '_joo_featured_order', $order );
		}
		$provider = $this->boot_fresh();
		$titles   = array_map( static fn( $p ) => $p->title, $provider->featured( 10 ) );
		$this->assertSame( array( 'C', 'B', 'A' ), $titles, 'order asc then newest first; draft and password excluded' );
		$this->assertCount( 2, $this->boot_fresh()->featured( 2 ) );
		$this->assertLessThanOrEqual( 12, count( $this->boot_fresh()->featured( 50 ) ), 'limit is capped' );
	}

	public function test_featured_falls_back_to_sticky_posts_then_to_nothing(): void {
		$one = self::factory()->post->create( array( 'post_title' => 'Sticky' ) );
		$this->assertSame( array(), $this->boot_fresh()->featured( 5 ) );
		stick_post( $one );
		$this->assertSame( array( 'Sticky' ), array_map( static fn( $p ) => $p->title, $this->boot_fresh()->featured( 5 ) ) );
	}

	public function test_related_posts_are_scored_deterministically_and_exclude_self_and_unpublished(): void {
		$cat   = self::factory()->category->create( array( 'name' => 'Main' ) );
		$other = self::factory()->category->create( array( 'name' => 'Other' ) );
		$src   = self::factory()->post->create(
			array(
				'post_category' => array( $cat ),
				'post_title'    => 'Source',
			)
		);
		wp_set_post_tags( $src, array( 'x', 'y' ) );
		$sameCat  = self::factory()->post->create(
			array(
				'post_category' => array( $cat ),
				'post_title'    => 'SameCat',
				'post_date'     => '2024-01-01 00:00:00',
			)
		);
		$sameTags = self::factory()->post->create(
			array(
				'post_category' => array( $other ),
				'post_title'    => 'SameTags',
				'post_date'     => '2023-01-01 00:00:00',
			)
		);
		wp_set_post_tags( $sameTags, array( 'x', 'y' ) );
		$draft  = self::factory()->post->create(
			array(
				'post_category' => array( $cat ),
				'post_status'   => 'draft',
			)
		);
		$unrel  = self::factory()->post->create(
			array(
				'post_category' => array( $other ),
				'post_title'    => 'Unrelated',
			)
		);
		$cards  = $this->boot_fresh()->related( $src, 5 );
		$titles = array_map( static fn( $p ) => $p->title, $cards );
		$this->assertSame( array( 'SameCat', 'SameTags' ), $titles, 'both score 4; the newer one first; draft and unrelated absent' );
		$this->assertNotContains( 'Source', $titles );
		$this->assertSame( $titles, array_map( static fn( $p ) => $p->title, $this->boot_fresh()->related( $src, 5 ) ) );
		$this->assertSame( array(), $this->boot_fresh()->related( $draft, 3 ), 'unpublished source' );
		unset( $sameCat, $unrel );
	}

	public function test_post_cards_describe_the_post(): void {
		$cat   = self::factory()->category->create( array( 'name' => 'Cards' ) );
		$image = AttachmentFactory::image( 800, 600 );
		$post  = self::factory()->post->create(
			array(
				'post_title'    => 'Card post',
				'post_category' => array( $cat ),
				'post_content'  => '<p>' . str_repeat( 'word ', 450 ) . '</p>',
			)
		);
		set_post_thumbnail( $post, $image );
		update_post_meta( $post, '_joo_featured', true );
		$card = $this->boot_fresh()->featured( 1 )[0];
		$this->assertSame( 'Card post', $card->title );
		$this->assertSame( 'Cards', $card->category );
		$this->assertSame( 3, $card->readingMinutes );
		$this->assertNotNull( $card->image );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T/', $card->date );
		$this->assertSame( get_permalink( $post ), $card->url );
	}

	public function test_results_are_cached_and_invalidated_by_tag(): void {
		$this->company( 'Cacheco', '2020-01' );
		$provider = $this->boot_fresh();
		$provider->companies( 1, 5, false );
		global $wpdb;
		$before = $wpdb->num_queries;
		$provider->companies( 1, 5, false );
		$this->assertLessThanOrEqual( 3, $wpdb->num_queries - $before, 'second call is served from the cache' );
		$this->company( 'Another', '2021-01' );
		$this->assertSame( 2, $provider->companies( 1, 5, false )->total, 'a new company invalidates the cache' );
	}

	// --- REST -------------------------------------------------------------------------------------------

	public function test_the_routes_are_registered(): void {
		$this->boot();
		$routes = rest_get_server()->get_routes();
		foreach ( array( '/joo/v1/companies', '/joo/v1/categories/tree', '/joo/v1/topics', '/joo/v1/featured', '/joo/v1/related/(?P<post_id>\d+)', '/joo/v1/profile' ) as $route ) {
			$this->assertArrayHasKey( $route, $routes, $route );
		}
	}

	public function test_companies_route_has_the_documented_shape_and_pagination_headers(): void {
		$this->boot();
		$this->company( 'Newco', '2022-03', '', true );
		$this->company( 'Midco', '2018-07', '2021-12' );
		$this->company( 'Oldco', '2015-01', '2018-06' );
		$response = $this->get( '/companies', array( 'per_page' => 1 ) );
		$this->assertSame( 200, $response->get_status() );
		$headers = $response->get_headers();
		$this->assertSame( '2', (string) $headers['X-WP-Total'] );
		$this->assertSame( '2', (string) $headers['X-WP-TotalPages'] );
		$item = $response->get_data()[0];
		foreach ( array( 'id', 'name', 'role', 'from', 'to', 'current', 'pinned', 'description', 'url', 'logo' ) as $key ) {
			$this->assertArrayHasKey( $key, $item, $key );
		}
		$this->assertSame( 'Midco', $item['name'] );
		$with = $this->get(
			'/companies',
			array(
				'exclude_pinned' => 'false',
				'per_page'       => 5,
			)
		);
		$this->assertSame( 'Newco', $with->get_data()[0]['name'] );
		$beyond = $this->get( '/companies', array( 'page' => 99 ) );
		$this->assertSame( 200, $beyond->get_status() );
		$this->assertSame( array(), $beyond->get_data() );
		$this->assertSame( '2', (string) $beyond->get_headers()['X-WP-Total'] );
	}

	public function test_argument_validation_returns_400(): void {
		$this->boot();
		$this->assertSame( 400, $this->get( '/companies', array( 'per_page' => 50 ) )->get_status() );
		$this->assertSame( 400, $this->get( '/companies', array( 'page' => 0 ) )->get_status() );
		$this->assertSame( 400, $this->get( '/topics', array( 'limit' => 99 ) )->get_status() );
		$this->assertSame( 400, $this->get( '/topics', array( 'strategy' => 'random' ) )->get_status() );
		$this->assertSame( 400, $this->get( '/featured', array( 'limit' => 13 ) )->get_status() );
		$this->assertSame( 400, $this->get( '/related/1', array( 'limit' => 9 ) )->get_status() );
		$this->assertSame( 400, $this->get( '/companies', array( 'per_page' => 'abc' ) )->get_status() );
	}

	public function test_published_only_across_every_route(): void {
		$this->boot();
		$this->company( 'Hiddenco', '2024-01', '', false, 'draft' );
		$cat   = self::factory()->category->create( array( 'name' => 'Secret cat' ) );
		$draft = self::factory()->post->create(
			array(
				'post_title'    => 'Draft post',
				'post_status'   => 'draft',
				'post_category' => array( $cat ),
			)
		);
		$priv  = self::factory()->post->create(
			array(
				'post_title'  => 'Private post',
				'post_status' => 'private',
			)
		);
		$prot  = self::factory()->post->create(
			array(
				'post_title'    => 'Protected post',
				'post_password' => 'pw',
			)
		);
		foreach ( array( $draft, $priv, $prot ) as $id ) {
			update_post_meta( $id, '_joo_featured', true );
		}
		wp_set_post_tags( $draft, array( 'hidden-tag' ) );
		wp_set_post_tags( $priv, array( 'hidden-tag' ) );
		$bodies = '';
		foreach ( array( '/companies', '/categories/tree', '/topics', '/featured', '/related/' . $draft, '/profile' ) as $route ) {
			$bodies .= wp_json_encode( $this->get( $route, array( 'exclude_pinned' => false ) )->get_data() );
		}
		foreach ( array( 'Hiddenco', 'Draft post', 'Private post', 'Protected post', 'Secret cat', 'hidden-tag' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $bodies, $secret );
		}
	}

	public function test_cache_headers_and_conditional_requests(): void {
		$this->boot();
		$this->company( 'Cachedco', '2020-01' );
		$server   = rest_get_server();
		$request  = new \WP_REST_Request( 'GET', '/joo/v1/companies' );
		$response = $server->dispatch( $request );
		$response = apply_filters( 'rest_post_dispatch', $response, $server, $request );
		$headers  = $response->get_headers();
		$this->assertStringContainsString( 'public, max-age=300, stale-while-revalidate=600', $headers['Cache-Control'] );
		$this->assertStringStartsWith( 'W/"', $headers['ETag'] );
		$again = new \WP_REST_Request( 'GET', '/joo/v1/companies' );
		$again->set_header( 'If-None-Match', $headers['ETag'] );
		$second = apply_filters( 'rest_post_dispatch', $server->dispatch( $again ), $server, $again );
		$this->assertSame( 304, $second->get_status() );
	}

	public function test_each_endpoint_stays_within_its_query_budget(): void {
		$this->boot();
		$cat = self::factory()->category->create( array( 'name' => 'Budget' ) );
		foreach ( range( 1, 6 ) as $i ) {
			$id = self::factory()->post->create(
				array(
					'post_title'    => 'P' . $i,
					'post_category' => array( $cat ),
				)
			);
			update_post_meta( $id, '_joo_featured', true );
			wp_set_post_tags( $id, array( 'tag' . ( $i % 3 ) ) );
			$this->company( 'Co' . $i, '20' . ( 10 + $i ) . '-01' );
		}
		$one = self::factory()->post->get_object_by_id(
			(int) get_posts(
				array(
					'fields'      => 'ids',
					'numberposts' => 1,
				)
			)[0]
		);
		global $wpdb;
		// Cold cache: measured 24/8/14/23/34/11; the budgets leave a margin. Warm cache must stay within 6 queries.
		$budget = array(
			'/companies'           => 30,
			'/categories/tree'     => 10,
			'/topics'              => 18,
			'/featured'            => 30,
			'/related/' . $one->ID => 45,
			'/profile'             => 16,
		);
		foreach ( $budget as $route => $max ) {
			wp_cache_flush();
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_joo:%'" );
			$before = $wpdb->num_queries;
			$this->get( $route );
			$used = $wpdb->num_queries - $before;
			$this->assertLessThanOrEqual( $max, $used, $route . ' cold-cache query count' );
			wp_cache_flush();
			$before = $wpdb->num_queries;
			$this->get( $route );
			$this->assertLessThanOrEqual( 6, $wpdb->num_queries - $before, $route . ' warm-cache query count' );
		}
	}

	public function test_featured_posts_are_left_out_of_the_blog_home_but_not_of_other_views(): void {
		$featured = self::factory()->post->create( array( 'post_title' => 'Featured one' ) );
		$normal   = self::factory()->post->create( array( 'post_title' => 'Normal one' ) );
		update_post_meta( $featured, '_joo_featured', '1' );
		update_post_meta( $featured, '_joo_featured_order', 1 );
		$this->boot_fresh();
		$this->go_to( '/' );
		$ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
		$this->assertContains( $normal, $ids );
		$this->assertNotContains( $featured, $ids, 'the slider already shows it' );
		$this->go_to( '/?s=Featured' );
		$this->assertContains( $featured, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ), 'search still finds it' );
	}

	public function test_the_exclusion_can_be_switched_off_in_settings(): void {
		$featured = self::factory()->post->create( array( 'post_title' => 'Featured two' ) );
		update_post_meta( $featured, '_joo_featured', '1' );
		update_post_meta( $featured, '_joo_featured_order', 1 );
		add_filter( 'joo/settings/value', static fn( $v, $key ) => 'features.hide_featured_in_list' === $key ? false : $v, 10, 2 );
		$this->boot_fresh();
		$this->go_to( '/' );
		$this->assertContains( $featured, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}
}
