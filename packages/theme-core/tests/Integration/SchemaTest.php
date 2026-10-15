<?php
/**
 * Structured data, breadcrumbs, sitemaps and HTTP semantics (real WordPress).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\Factories\AttachmentFactory;
use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Kernel;
use JOOservices\ThemeCore\Seo\Breadcrumbs\Crumb;
use JOOservices\ThemeCore\Seo\Breadcrumbs\TrailBuilder;
use JOOservices\ThemeCore\Seo\HeadPrinter;
use JOOservices\ThemeCore\Seo\HttpSemantics;
use JOOservices\ThemeCore\Seo\Schema\GraphPrinter;

final class SchemaTest extends IntegrationTestCase {

	private const MANIFEST = '/workspace/packages/theme-core/tests/Fixtures/manifests/perf.json';

	/**
	 * Owner user id.
	 *
	 * @var int
	 */
	private int $owner = 0;

	public function set_up(): void {
		parent::set_up();
		Kernel::reset();
		Notices::reset();
		$this->set_permalink_structure( '/%postname%/' );
		create_initial_taxonomies();
		$GLOBALS['wp_rewrite']->flush_rules();
		update_option( 'blogname', 'Joo Blog' );
		update_option( 'blogdescription', 'Notes on engineering' );
		$this->owner = 1;
		wp_update_user(
			array(
				'ID'           => 1,
				'role'         => 'administrator',
				'display_name' => 'Viet Vu',
				'description'  => 'Engineer and writer.',
			)
		);
		update_user_meta( $this->owner, 'joo_title', 'Software Engineer' );
		update_user_meta( $this->owner, 'joo_socials', "https://github.com/example\nhttps://x.test/profile javascript:alert(1)" );
	}

	public function tear_down(): void {
		Kernel::reset();
		parent::tear_down();
	}

	/**
	 * Boots, visits a URL and returns the decoded JSON-LD document (null when none is printed).
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return array<string, mixed>|null
	 */
	private function graph( string $url, array $settings = array() ): ?array {
		if ( null === Kernel::container() ) {
			if ( array() !== $settings ) {
				add_filter( 'joo/settings/value', static fn( $value, $key ) => array_key_exists( $key, $settings ) ? $settings[ $key ] : $value, 10, 2 );
			}
			Kernel::boot( self::MANIFEST );
			do_action( 'after_setup_theme' );
		}
		$this->go_to( $url );
		do_action( 'wp' );
		$html = $this->capture( 'wp_head' );
		if ( 1 !== preg_match( '#<script type="application/ld\+json" class="joo-schema">(.*?)</script>#s', $html, $m ) ) {
			return null;
		}
		$this->assertSame( 1, substr_count( $html, 'application/ld+json' ), 'exactly one JSON-LD block' );
		$decoded = json_decode( $m[1], true );
		$this->assertIsArray( $decoded, 'valid JSON' );
		return $decoded;
	}

	/**
	 * @param array<string, mixed> $doc Document.
	 * @return array<string, array<string, mixed>> Nodes by type.
	 */
	private function byType( array $doc ): array {
		$out = array();
		foreach ( $doc['@graph'] as $node ) {
			$out[ (string) $node['@type'] ] = $node;
		}
		return $out;
	}

	/**
	 * Every `{"@id": X}` reference must resolve and no id may repeat.
	 *
	 * @param array<string, mixed> $doc Document.
	 */
	private function assertConsistent( array $doc ): void {
		$ids = array();
		foreach ( $doc['@graph'] as $node ) {
			$this->assertArrayHasKey( '@id', $node );
			$this->assertNotContains( $node['@id'], $ids, 'duplicate id' );
			$ids[] = $node['@id'];
		}
		array_walk_recursive(
			$doc['@graph'],
			static function () {}
		);
		$refs = array();
		$walk = static function ( $value ) use ( &$walk, &$refs ): void {
			if ( ! is_array( $value ) ) {
				return;
			}
			if ( 1 === count( $value ) && isset( $value['@id'] ) ) {
				$refs[] = $value['@id'];
				return;
			}
			foreach ( $value as $key => $item ) {
				if ( '@id' !== $key ) {
					$walk( $item );
				}
			}
		};
		$walk( $doc['@graph'] );
		foreach ( $refs as $ref ) {
			$this->assertContains( $ref, $ids, 'dangling reference ' . $ref );
		}
	}

	public function test_a_post_gets_one_connected_graph_with_a_complete_blog_posting(): void {
		$image = AttachmentFactory::image( 1600, 900 );
		$cat   = self::factory()->category->create( array( 'name' => 'Performance' ) );
		$post  = self::factory()->post->create(
			array(
				'post_title'    => 'Fast themes',
				'post_author'   => $this->owner,
				'post_category' => array( $cat ),
				'post_content'  => '<p>' . str_repeat( 'word ', 450 ) . '</p>',
			)
		);
		wp_set_post_tags( $post, array( 'php', 'cache' ) );
		set_post_thumbnail( $post, $image );
		$doc = $this->graph( get_permalink( $post ) );
		$this->assertNotNull( $doc );
		$this->assertConsistent( $doc );
		$types = $this->byType( $doc );
		foreach ( array( 'WebSite', 'Organization', 'Person', 'WebPage', 'BlogPosting', 'ImageObject', 'BreadcrumbList' ) as $type ) {
			$this->assertArrayHasKey( $type, $types, $type );
		}
		$article = $types['BlogPosting'];
		foreach ( array( 'headline', 'datePublished', 'dateModified', 'author', 'publisher', 'image', 'mainEntityOfPage', 'inLanguage', 'articleSection', 'keywords', 'wordCount', 'timeRequired' ) as $key ) {
			$this->assertArrayHasKey( $key, $article, $key );
		}
		$this->assertSame( 450, $article['wordCount'] );
		$this->assertSame( 'PT3M', $article['timeRequired'] );
		$this->assertSame( 'Performance', $article['articleSection'] );
		$this->assertSame( array( '@id' => home_url( '/#person' ) ), $article['author'] );
		$person = $types['Person'];
		$this->assertSame( 'Viet Vu', $person['name'] );
		$this->assertSame( 'Software Engineer', $person['jobTitle'] );
		$this->assertSame( array( 'https://github.com/example', 'https://x.test/profile' ), $person['sameAs'], 'non-http(s) values such as javascript: are dropped' );
		$this->assertSame( 'WebSite', $types['WebSite']['@type'] );
		$this->assertSame( home_url( '/?s={search_term_string}' ), $types['WebSite']['potentialAction']['target']['urlTemplate'] );
	}

	public function test_posts_by_other_authors_get_a_nested_person(): void {
		$other = self::factory()->user->create( array( 'display_name' => 'Guest Writer' ) );
		$post  = self::factory()->post->create( array( 'post_author' => $other ) );
		$doc   = $this->graph( get_permalink( $post ) );
		$this->assertConsistent( $doc );
		$author = $this->byType( $doc )['BlogPosting']['author'];
		$this->assertSame( 'Person', $author['@type'] );
		$this->assertSame( 'Guest Writer', $author['name'] );
	}

	public function test_page_type_per_context(): void {
		$post = self::factory()->post->create( array( 'post_author' => $this->owner ) );
		$cat  = self::factory()->category->create( array( 'name' => 'Docs' ) );
		wp_set_post_categories( $post, array( $cat ) );
		$home = $this->graph( '/' );
		$this->assertConsistent( $home );
		$this->assertArrayHasKey( 'CollectionPage', $this->byType( $home ) );
		$this->assertArrayNotHasKey( 'BlogPosting', $this->byType( $home ) );
		$this->assertArrayNotHasKey( 'BreadcrumbList', $this->byType( $home ), 'a one-item trail is not useful' );

		$category = $this->graph( get_category_link( $cat ) );
		$this->assertConsistent( $category );
		$this->assertArrayHasKey( 'CollectionPage', $this->byType( $category ) );

		$author = $this->graph( get_author_posts_url( $this->owner ) );
		$this->assertConsistent( $author );
		$types = $this->byType( $author );
		$this->assertArrayHasKey( 'ProfilePage', $types );
		$this->assertSame( array( '@id' => home_url( '/#person' ) ), $types['ProfilePage']['mainEntity'] );

		$search = $this->graph( '/?s=docs' );
		$this->assertConsistent( $search );
		$this->assertArrayHasKey( 'SearchResultsPage', $this->byType( $search ) );

		$missing = $this->graph( '/definitely-missing/' );
		$this->assertConsistent( $missing );
		$types = $this->byType( $missing );
		$this->assertArrayHasKey( 'WebSite', $types );
		$this->assertArrayNotHasKey( 'WebPage', $types );
		$this->assertArrayNotHasKey( 'CollectionPage', $types );
	}

	public function test_protected_posts_get_no_article_or_image(): void {
		$post = self::factory()->post->create(
			array(
				'post_password' => 'x',
				'post_author'   => $this->owner,
			)
		);
		$doc  = $this->graph( get_permalink( $post ) );
		$this->assertNotNull( $doc );
		$types = $this->byType( $doc );
		$this->assertArrayNotHasKey( 'BlogPosting', $types );
		$this->assertArrayNotHasKey( 'ImageObject', $types );
	}

	public function test_yielding_keeps_only_the_breadcrumb_list(): void {
		add_filter( 'joo/seo/yield', '__return_true' );
		$parent = self::factory()->category->create( array( 'name' => 'Research' ) );
		$post   = self::factory()->post->create(
			array(
				'post_category' => array( $parent ),
				'post_author'   => $this->owner,
			)
		);
		$doc    = $this->graph( get_permalink( $post ) );
		$this->assertNotNull( $doc );
		$this->assertSame( array( 'BreadcrumbList' ), array_keys( $this->byType( $doc ) ) );
	}

	public function test_breadcrumb_owner_plugin_removes_the_list(): void {
		$post = self::factory()->post->create( array( 'post_author' => $this->owner ) );
		$doc  = $this->graph( get_permalink( $post ), array( 'seo.breadcrumbs_owner' => 'plugin' ) );
		$this->assertArrayNotHasKey( 'BreadcrumbList', $this->byType( $doc ) );
	}

	public function test_piece_filter_adjusts_a_node_and_custom_pieces_can_be_added(): void {
		add_filter(
			'joo/seo/schema/website',
			static function ( array $node ) {
				$node['alternateName'] = 'JB';
				return $node;
			}
		);
		$doc = $this->graph( '/' );
		$this->assertSame( 'JB', $this->byType( $doc )['WebSite']['alternateName'] );
	}

	public function test_breadcrumb_trail_follows_the_category_hierarchy_and_schema_matches(): void {
		$rd    = self::factory()->category->create( array( 'name' => 'Research & Development' ) );
		$lab   = self::factory()->category->create(
			array(
				'name'   => 'Homelab',
				'parent' => $rd,
			)
		);
		$post  = self::factory()->post->create(
			array(
				'post_title'    => 'Rack build',
				'post_category' => array( $lab ),
			)
		);
		$doc   = $this->graph( get_permalink( $post ) );
		$list  = $this->byType( $doc )['BreadcrumbList']['itemListElement'];
		$names = array_column( $list, 'name' );
		$this->assertSame( array( 'Home', 'Research & Development', 'Homelab', 'Rack build' ), $names );
		$this->assertSame( array( 1, 2, 3, 4 ), array_column( $list, 'position' ) );
		$this->assertArrayNotHasKey( 'item', $list[3], 'the current page has no item url' );
		$this->assertSame( get_category_link( $lab ), $list[2]['item'] );

		$trails  = Kernel::container()->get( TrailBuilder::class );
		$printer = Kernel::container()->get( HeadPrinter::class );
		$this->assertSame( $names, array_map( static fn( Crumb $c ) => $c->label, $trails->build( $printer->context() ) ) );
	}

	public function test_explicit_primary_category_wins_and_the_filter_can_add_a_crumb(): void {
		$a    = self::factory()->category->create( array( 'name' => 'Alpha' ) );
		$b    = self::factory()->category->create( array( 'name' => 'Beta' ) );
		$post = self::factory()->post->create(
			array(
				'post_title'    => 'P',
				'post_category' => array( $a, $b ),
			)
		);
		update_post_meta( $post, '_joo_primary_category', $b );
		add_filter(
			'joo/breadcrumbs/trail',
			static function ( array $trail ) {
				array_splice( $trail, 1, 0, array( new Crumb( 'Extra', 'https://example.test/extra/' ) ) );
				return $trail;
			}
		);
		$doc = $this->graph( get_permalink( $post ) );
		$this->assertSame( array( 'Home', 'Extra', 'Beta', 'P' ), array_column( $this->byType( $doc )['BreadcrumbList']['itemListElement'], 'name' ) );
	}

	public function test_trails_for_other_contexts(): void {
		$tag  = self::factory()->tag->create( array( 'name' => 'Cache' ) );
		$post = self::factory()->post->create(
			array(
				'post_date'   => '2021-05-10 10:00:00',
				'post_author' => $this->owner,
			)
		);
		wp_set_post_tags( $post, array( 'Cache' ) );
		$page_parent = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Parent',
			)
		);
		$page        = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Child',
				'post_parent' => $page_parent,
			)
		);
		$names       = function ( string $url ): array {
			$this->graph( $url );
			$printer = Kernel::container()->get( HeadPrinter::class );
			$trails  = Kernel::container()->get( TrailBuilder::class );
			return array_map( static fn( Crumb $c ) => $c->label, $trails->build( $printer->context() ) );
		};
		$this->assertSame( array( 'Home', 'Tags', 'Cache' ), $names( get_tag_link( $tag ) ) );
		$this->assertSame( array( 'Home', 'Parent', 'Child' ), $names( get_permalink( $page ) ) );
		$this->assertSame( array( 'Home', 'Authors', 'Viet Vu' ), $names( get_author_posts_url( $this->owner ) ) );
		$this->assertSame( array( 'Home', '2021', 'May' ), $names( '/2021/05/' ) );
		$this->assertSame( array( 'Home', 'Search results for "x"' ), $names( '/?s=x' ) );
		$this->assertSame( array( 'Home', 'Page not found' ), $names( '/no-such-thing/' ) );
		$this->assertSame( array( 'Home' ), $names( '/' ) );
	}

	public function test_display_labels_are_truncated_and_paged_views_get_a_suffix(): void {
		$trails = new TrailBuilder();
		$ctx    = new \JOOservices\ThemeCore\Seo\Context( 'category', null, 2 );
		$out    = $trails->forDisplay( array( new Crumb( 'Home', 'https://x.test/' ), new Crumb( str_repeat( 'Long label ', 10 ) ) ), $ctx );
		$this->assertLessThanOrEqual( 50, mb_strlen( $out[1]->label ) - mb_strlen( ' (Page 2)' ) );
		$this->assertStringEndsWith( '(Page 2)', $out[1]->label );
	}

	public function test_sitemap_excludes_noindex_protected_and_drafts_and_has_lastmod(): void {
		$this->graph( '/' );
		$visible = self::factory()->post->create( array( 'post_title' => 'Visible' ) );
		$noindex = self::factory()->post->create( array( 'post_title' => 'Noindexed' ) );
		update_post_meta( $noindex, '_joo_seo_noindex', '1' );
		$protected = self::factory()->post->create( array( 'post_password' => 'pw' ) );
		$draft     = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$provider  = wp_sitemaps_get_server()->registry->get_provider( 'posts' );
		$urls      = $provider->get_url_list( 1, 'post' );
		$locs      = array_column( $urls, 'loc' );
		$this->assertContains( get_permalink( $visible ), $locs );
		foreach ( array( $noindex, $protected, $draft ) as $id ) {
			$this->assertNotContains( get_permalink( $id ), $locs );
		}
		$entry = $urls[ array_search( get_permalink( $visible ), $locs, true ) ];
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $entry['lastmod'] );
	}

	public function test_sitemap_taxonomies_and_users(): void {
		$this->graph( '/' );
		$cat     = self::factory()->category->create( array( 'name' => 'Mapped' ) );
		$post    = self::factory()->post->create(
			array(
				'post_category' => array( $cat ),
				'post_author'   => $this->owner,
			)
		);
		$noposts = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$server  = wp_sitemaps_get_server();
		$this->assertSame( array( 'category', 'post_tag' ), array_keys( $server->registry->get_provider( 'taxonomies' )->get_object_subtypes() ) );
		$terms = $server->registry->get_provider( 'taxonomies' )->get_url_list( 1, 'category' );
		$hit   = array_values( array_filter( $terms, static fn( $e ) => get_category_link( $cat ) === $e['loc'] ) );
		$this->assertNotEmpty( $hit );
		$this->assertArrayHasKey( 'lastmod', $hit[0] );
		$users = array_column( $server->registry->get_provider( 'users' )->get_url_list( 1 ), 'loc' );
		$this->assertContains( get_author_posts_url( $this->owner ), $users );
		$this->assertNotContains( get_author_posts_url( $noposts ), $users );
		unset( $post );
	}

	public function test_robots_txt_has_exactly_one_sitemap_line(): void {
		$this->graph( '/' );
		$robots = (string) apply_filters( 'robots_txt', "User-agent: *\nDisallow: /wp-admin/\n", true );
		$this->assertSame( 1, preg_match_all( '/^Sitemap:/mi', $robots ), $robots );
		$this->assertStringContainsString( home_url( '/wp-sitemap.xml' ), $robots );
	}

	public function test_blank_search_redirects_to_home_but_real_searches_do_not(): void {
		$this->graph( '/' );
		$http = Kernel::container()->get( HttpSemantics::class );
		$this->go_to( '/?s=' );
		$_GET['s'] = '   ';
		$this->assertSame( home_url( '/' ), $http->blankSearchTarget() );
		$this->go_to( '/?s=real' );
		$_GET['s'] = 'real';
		$this->assertNull( $http->blankSearchTarget() );
		unset( $_GET['s'] );
	}

	public function test_missing_and_non_public_content_is_a_404(): void {
		$this->graph( '/' );
		$draft = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_name'   => 'secret-draft',
			)
		);
		$this->go_to( '/definitely-missing/' );
		$this->assertTrue( is_404() );
		$this->go_to( '/?p=' . $draft );
		$this->assertTrue( is_404(), 'drafts are not public' );
		$private = self::factory()->post->create( array( 'post_status' => 'private' ) );
		wp_set_current_user( 0 );
		$this->go_to( '/?p=' . $private );
		$this->assertTrue( is_404(), 'private posts are 404 for visitors' );
	}

	public function test_comments_feed_discovery_follows_the_site_wide_comment_default(): void {
		$this->graph( '/' );
		$http = Kernel::container()->get( HttpSemantics::class );
		update_option( 'default_comment_status', 'closed' );
		$this->assertFalse( $http->commentsFeed( true ) );
		update_option( 'default_comment_status', 'open' );
		$this->assertTrue( $http->commentsFeed( true ) );
		$this->assertInstanceOf( GraphPrinter::class, Kernel::container()->get( GraphPrinter::class ) );
	}
}
