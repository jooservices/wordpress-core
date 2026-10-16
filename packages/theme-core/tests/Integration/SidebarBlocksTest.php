<?php
/**
 * Sidebar blocks (real WordPress, JOOservices Resume loaded).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\Factories\AttachmentFactory;
use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Blocks\Blocks\Companies;
use JOOservices\ThemeCore\Blocks\Blocks\TagList;
use JOOservices\ThemeCore\Blocks\BlockRegistrar;
use JOOservices\ThemeCore\Kernel;

final class SidebarBlocksTest extends IntegrationTestCase {

	private const MANIFEST = '/workspace/packages/theme-core/tests/Fixtures/manifests/perf.json';

	private const BLOCKS = array( 'joo/profile-card', 'joo/companies', 'joo/category-tree', 'joo/top-topics', 'joo/tag-list' );

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
		foreach ( array_merge( self::BLOCKS, array( 'joo/breadcrumbs', 'joo/post-meta', 'joo/featured-slider', 'joo/toc', 'joo/reading-progress', 'joo/related-posts', 'joo/author-box', 'joo/theme-toggle', 'joo/lang-switcher', 'joo/search-trigger' ) ) as $name ) {
			if ( \WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}
		unset( $_GET[ Companies::QUERY_VAR ] );
		Kernel::reset();
		$GLOBALS['wp_rest_server'] = null;
		parent::tear_down();
	}

	private function boot( array $settings = array() ): void {
		if ( array() !== $settings ) {
			add_filter( 'joo/settings/value', static fn( $v, $key ) => array_key_exists( $key, $settings ) ? $settings[ $key ] : $v, 10, 2 );
		}
		Kernel::boot( self::MANIFEST );
		do_action( 'after_setup_theme' );
		Kernel::container()->get( BlockRegistrar::class )->register();
	}

	private function company( string $name, string $from, string $to = '', bool $pinned = false ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'joo_company',
				'post_title'   => $name,
				'post_excerpt' => $name . ' desc',
				'post_status'  => 'publish',
			)
		);
		update_post_meta( $id, '_joo_company_role', 'Role at ' . $name );
		update_post_meta( $id, '_joo_company_from', $from );
		update_post_meta( $id, '_joo_company_to', $to );
		update_post_meta( $id, '_joo_company_url', 'https://' . strtolower( $name ) . '.test' );
		if ( $pinned ) {
			update_post_meta( $id, '_joo_company_pinned', true );
		}
		return $id;
	}

	/**
	 * The HTML of the deferred fragment (the noscript copy) or the block itself.
	 */
	private function inline( string $html ): string {
		return 1 === preg_match( '#<noscript>(.*)</noscript>#s', $html, $m ) ? trim( $m[1] ) : trim( $html );
	}

	private function fragment( string $name, array $params ): string {
		$request = new \WP_REST_Request( 'GET', '/joo/v1/fragment/' . $name );
		$request->set_query_params( $params );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status(), $name );
		return trim( $response->get_data()['html'] );
	}

	public function test_all_sidebar_blocks_are_registered(): void {
		$this->boot();
		foreach ( self::BLOCKS as $name ) {
			$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( $name ), $name );
		}
	}

	public function test_profile_card_links_to_about_when_it_exists_else_the_author_archive(): void {
		$this->boot();
		wp_update_user(
			array(
				'ID'           => 1,
				'display_name' => 'Viet <b>Vu</b>',
				'description'  => 'Bio text',
			)
		);
		update_user_meta( 1, 'joo_title', 'Engineer · Founder' );
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
		wp_cache_flush();
		$html = do_blocks( '<!-- wp:joo/profile-card /-->' );
		$this->assertStringContainsString( 'Viet Vu', $html );
		$this->assertStringContainsString( 'Engineer · Founder', $html );
		$this->assertStringContainsString( 'href="' . esc_url( get_author_posts_url( 1 ) ) . '"', $html, 'no About page: author archive' );
		$this->assertMatchesRegularExpression( '/<img[^>]*width="64"[^>]*height="64"/', $html );
		$this->assertMatchesRegularExpression( '/alt="Viet Vu"/', $html );
		$this->assertStringContainsString( 'rel="me noopener"', $html );
		$this->assertStringNotContainsString( '<b>', $html );

		$about = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_name'  => 'about',
				'post_title' => 'About',
			)
		);
		wp_cache_flush();
		$this->assertStringContainsString( 'href="' . esc_url( get_permalink( $about ) ) . '"', do_blocks( '<!-- wp:joo/profile-card /-->' ) );
		$this->assertStringContainsString( 'href="' . esc_url( get_author_posts_url( 1 ) ) . '"', do_blocks( '<!-- wp:joo/profile-card {"linkTo":"author"} /-->' ) );
		$small = do_blocks( '<!-- wp:joo/profile-card {"avatarSize":96,"showBio":false,"showSocials":false,"title":"About me","titleLevel":3} /-->' );
		$this->assertStringContainsString( 'width="96"', $small );
		$this->assertStringNotContainsString( 'joo-profile__bio', $small );
		$this->assertStringNotContainsString( 'joo-profile__socials', $small );
		$this->assertStringContainsString( '<h3 class="joo-block-title">About me</h3>', $small );
	}

	public function test_profile_card_renders_nothing_without_the_resume_plugin(): void {
		unregister_post_type( 'joo_company' );
		$this->boot();
		$this->assertSame( '', trim( do_blocks( '<!-- wp:joo/profile-card /-->' ) ) );
		( new \JOOservices\Resume\PostTypes\Company() )->register();
	}

	public function test_companies_show_the_pinned_one_separately_and_page_the_rest(): void {
		$this->boot();
		$this->company( 'Newco', '2022-03', '', true );
		foreach ( range( 1, 5 ) as $i ) {
			$this->company( 'Old' . $i, '20' . ( 10 + $i ) . '-01', '20' . ( 10 + $i ) . '-12' );
		}
		wp_cache_flush();
		$html = $this->inline( do_blocks( '<!-- wp:joo/companies {"perPage":2} /-->' ) );
		$this->assertStringContainsString( 'joo-company--pinned', $html );
		$this->assertStringContainsString( 'Current company', $html );
		$this->assertSame( 2, substr_count( $html, 'class="joo-company"' ) );
		$this->assertStringContainsString( 'Jan 2015 – Dec 2015', $html );
		$this->assertStringContainsString( 'data-total-pages="3"', $html );
		$this->assertStringContainsString( 'href="?joo_companies_page=2#companies"', $html );
		$this->assertStringContainsString( 'rel="next"', $html );
		$this->assertStringNotContainsString( 'rel="prev"', $html );
		$this->assertStringContainsString( 'aria-current="page"', $html );
	}

	public function test_companies_server_side_page_two_clamping_and_fragment_equality(): void {
		$this->boot();
		foreach ( range( 1, 5 ) as $i ) {
			$this->company( 'Co' . $i, '20' . ( 10 + $i ) . '-01' );
		}
		wp_cache_flush();
		$_GET[ Companies::QUERY_VAR ] = '2';
		$two                          = $this->inline( do_blocks( '<!-- wp:joo/companies {"perPage":2} /-->' ) );
		$this->assertStringContainsString( 'data-page="2"', $two );
		$this->assertStringContainsString( 'rel="prev"', $two );
		$this->assertStringContainsString( 'rel="next"', $two );
		$this->assertStringNotContainsString( 'Co5', $two, 'page 2 holds Co3 and Co2 (newest first)' );
		$this->assertStringContainsString( 'Co3', $two );
		$_GET[ Companies::QUERY_VAR ] = '99';
		$this->assertStringContainsString( 'data-page="3"', $this->inline( do_blocks( '<!-- wp:joo/companies {"perPage":2} /-->' ) ), 'out of range is clamped to the last page' );
		$_GET[ Companies::QUERY_VAR ] = '2';
		$inline                       = $this->inline( do_blocks( '<!-- wp:joo/companies {"perPage":2} /-->' ) );
		$this->assertSame(
			$inline,
			$this->fragment(
				'companies',
				array(
					'page'   => '2',
					'per'    => '2',
					'desc'   => '1',
					'pinned' => '',
					'list'   => '',
					'title'  => '',
				)
			)
		);
	}

	public function test_companies_with_one_page_have_no_pager_and_empty_data_hides_the_block(): void {
		$this->boot();
		$this->assertSame( '', trim( do_blocks( '<!-- wp:joo/companies /-->' ) ) );
		$this->company( 'Solo', '2020-01' );
		wp_cache_flush();
		$html = $this->inline( do_blocks( '<!-- wp:joo/companies {"perPage":5,"showDescription":false} /-->' ) );
		$this->assertStringNotContainsString( 'joo-companies__pager', $html );
		$this->assertStringNotContainsString( 'joo-company__desc', $html );
		$this->assertStringContainsString( 'Jan 2020 – Present', $html );
		$this->assertStringContainsString( 'id="companies"', $html );
	}

	public function test_company_text_is_escaped_and_links_are_https_only(): void {
		$this->boot();
		$id = $this->company( 'Evil <script>x</script>', '2020-01' );
		update_post_meta( $id, '_joo_company_role', '"><img src=x onerror=alert(1)>' );
		wp_cache_flush();
		$html = $this->inline( do_blocks( '<!-- wp:joo/companies /-->' ) );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function test_category_tree_markup_current_branch_depth_and_escaping(): void {
		$this->boot();
		$rd    = self::factory()->category->create(
			array(
				'name'        => 'Research & Development',
				'description' => 'Experiments and lessons.',
			)
		);
		$lab   = self::factory()->category->create(
			array(
				'name'   => 'Homelab',
				'parent' => $rd,
			)
		);
		$rack  = self::factory()->category->create(
			array(
				'name'   => 'Rack',
				'parent' => $lab,
			)
		);
		$other = self::factory()->category->create( array( 'name' => 'Other' ) );
		$post  = self::factory()->post->create( array( 'post_category' => array( $rack ) ) );
		self::factory()->post->create( array( 'post_category' => array( $other ) ) );
		update_term_meta( $lab, 'joo_short_description', str_repeat( 'x', 150 ) );
		wp_cache_flush();
		$this->go_to( get_category_link( $rack ) );
		$html = $this->inline( do_blocks( '<!-- wp:joo/category-tree {"expandedDepth":0} /-->' ) );
		$this->assertStringContainsString( '<joo-tree data-expanded-depth="0">', $html );
		$this->assertStringContainsString( 'Research &amp; Development', $html );
		$this->assertSame( 2, substr_count( $html, 'class="joo-tree__toggle"' ), 'only categories with children get a toggle (R&D, Homelab)' );
		$this->assertStringContainsString( 'hidden></button>', $html, 'toggles are hidden until JS runs' );
		$this->assertSame( 1, substr_count( $html, 'aria-current="page"' ) );
		$this->assertMatchesRegularExpression( '/data-id="' . $rd . '"[^>]*data-open="1"/', $html, 'ancestor of the current category is open' );
		$this->assertDoesNotMatchRegularExpression( '/data-id="' . $other . '"[^>]*data-open="1"/', $html );
		$this->assertStringContainsString( 'Experiments and lessons.', $html );
		$this->assertStringContainsString( '…', $html, 'long descriptions are truncated' );
		$this->assertStringContainsString( 'aria-controls="joo-tree-' . $lab . '"', $html );
		$this->assertStringNotContainsString( 'class="joo-tree__count"', do_blocks( '<!-- wp:joo/category-tree {"showCounts":false} /-->' ) );
		$flat = $this->inline( do_blocks( '<!-- wp:joo/category-tree {"maxDepth":1} /-->' ) );
		$this->assertStringNotContainsString( 'Homelab', $flat, 'levels beyond the maximum depth are not rendered' );
		$this->go_to( get_permalink( $post ) );
		$this->assertStringContainsString( 'aria-current="page"', $this->inline( do_blocks( '<!-- wp:joo/category-tree /-->' ) ), 'a post highlights its category' );
	}

	public function test_category_tree_hides_empty_categories_and_matches_the_fragment(): void {
		$this->boot();
		$used = self::factory()->category->create( array( 'name' => 'Used' ) );
		self::factory()->category->create( array( 'name' => 'Empty one' ) );
		self::factory()->post->create( array( 'post_category' => array( $used ) ) );
		wp_cache_flush();
		$this->go_to( '/' );
		$html = $this->inline( do_blocks( '<!-- wp:joo/category-tree /-->' ) );
		$this->assertStringContainsString( 'Used', $html );
		$this->assertStringNotContainsString( 'Empty one', $html );
		$this->assertStringContainsString( 'Empty one', $this->inline( do_blocks( '<!-- wp:joo/category-tree {"hideEmpty":false} /-->' ) ) );
		$this->assertSame(
			$html,
			$this->fragment(
				'category-tree',
				array(
					'counts'  => '1',
					'desc'    => '1',
					'open'    => '0',
					'empty'   => '1',
					'depth'   => '3',
					'current' => '0',
					'title'   => '',
				)
			)
		);
	}

	public function test_top_topics_follow_the_settings_strategy_with_image_and_intro_fallbacks(): void {
		$this->boot();
		$big   = self::factory()->tag->create(
			array(
				'name'        => 'Laravel',
				'description' => 'PHP framework notes.',
			)
		);
		$small = self::factory()->tag->create( array( 'name' => 'Docker' ) );
		$posts = self::factory()->post->create_many( 3 );
		foreach ( $posts as $id ) {
			wp_set_post_tags( $id, array( 'Laravel' ) );
		}
		wp_set_post_tags( $posts[0], array( 'Laravel', 'Docker' ) );
		update_term_meta( $small, 'joo_image_id', AttachmentFactory::image( 300, 300 ) );
		update_term_meta( $small, 'joo_intro', 'Containers & compose' );
		wp_cache_flush();
		$html = $this->inline( do_blocks( '<!-- wp:joo/top-topics /-->' ) );
		$this->assertLessThan( strpos( $html, 'Docker' ), strpos( $html, 'Laravel' ), 'most posts first' );
		$this->assertStringContainsString( '<i>3</i>', $html );
		$this->assertStringContainsString( 'PHP framework notes.', $html, 'intro falls back to the description' );
		$this->assertStringContainsString( 'Containers &amp; compose', $html );
		$this->assertStringContainsString( 'joo-topic__ph', $html, 'a topic without image gets a placeholder' );
		$this->assertMatchesRegularExpression( '/<img[^>]*width="44"[^>]*height="44"/', $html );
		$this->assertSame( 1, preg_match_all( '/<li>/', $this->inline( do_blocks( '<!-- wp:joo/top-topics {"limit":1} /-->' ) ) ) );
		$this->assertStringNotContainsString( 'joo-topic__intro', do_blocks( '<!-- wp:joo/top-topics {"showIntro":false} /-->' ) );
		$this->assertStringNotContainsString( 'joo-topic__img', $this->inline( do_blocks( '<!-- wp:joo/top-topics {"showImage":false} /-->' ) ) );
	}

	public function test_top_topics_manual_strategy_honours_the_owner_order_and_the_settings_limit(): void {
		$a = self::factory()->tag->create( array( 'name' => 'Alpha' ) );
		$b = self::factory()->tag->create( array( 'name' => 'Beta' ) );
		self::factory()->post->create_many( 3, array( 'tags_input' => array( 'Alpha' ) ) );
		self::factory()->post->create( array( 'tags_input' => array( 'Beta' ) ) );
		$this->boot(
			array(
				'content.topics_strategy' => 'manual',
				'content.topics_manual'   => array( $b, $a ),
				'content.topics_limit'    => 2,
			)
		);
		wp_cache_flush();
		$html = $this->inline( do_blocks( '<!-- wp:joo/top-topics /-->' ) );
		$this->assertLessThan( strpos( $html, 'Alpha' ), strpos( $html, 'Beta' ), 'curated order wins over counts' );
		$explicit = $this->inline( do_blocks( '<!-- wp:joo/top-topics {"strategy":"count"} /-->' ) );
		$this->assertLessThan( strpos( $explicit, 'Beta' ), strpos( $explicit, 'Alpha' ), 'an explicit strategy overrides the setting' );
		$this->assertSame(
			$html,
			$this->fragment(
				'top-topics',
				array(
					'limit'    => '2',
					'strategy' => 'manual',
					'image'    => '1',
					'intro'    => '1',
					'title'    => '',
				)
			)
		);
	}

	public function test_tag_list_orders_limits_weights_and_invalidates_the_cache(): void {
		$this->boot();
		self::factory()->post->create_many( 5, array( 'tags_input' => array( 'big' ) ) );
		self::factory()->post->create( array( 'tags_input' => array( 'small' ) ) );
		self::factory()->post->create( array( 'tags_input' => array( 'medium' ) ) );
		self::factory()->post->create( array( 'tags_input' => array( 'medium' ) ) );
		wp_cache_flush();
		$html = $this->inline( do_blocks( '<!-- wp:joo/tag-list {"limit":5,"style":"cloud"} /-->' ) );
		$this->assertLessThan( strpos( $html, '#small' ), strpos( $html, '#big' ) );
		$this->assertStringContainsString( 'rel="tag"', $html );
		$this->assertStringContainsString( 'joo-tags--cloud', $html );
		preg_match_all( '/--joo-tag-weight:([0-9.]+)/', $html, $w );
		$this->assertNotEmpty( $w[1] );
		foreach ( $w[1] as $weight ) {
			$this->assertGreaterThanOrEqual( TagList::MIN_WEIGHT, (float) $weight );
			$this->assertLessThanOrEqual( TagList::MAX_WEIGHT, (float) $weight );
		}
		$this->assertStringContainsString( '<sup>5</sup>', $html );
		$this->assertStringNotContainsString( '<sup>', do_blocks( '<!-- wp:joo/tag-list {"showCounts":false} /-->' ) );
		$this->assertStringNotContainsString( '--joo-tag-weight', $this->inline( do_blocks( '<!-- wp:joo/tag-list /-->' ) ), 'chips are uniform' );
		$byName = $this->inline( do_blocks( '<!-- wp:joo/tag-list {"orderby":"name"} /-->' ) );
		$this->assertLessThan( strpos( $byName, '#medium' ), strpos( $byName, '#big' ) );

		self::factory()->post->create( array( 'tags_input' => array( 'brandnew' ) ) );
		$this->assertStringContainsString( '#brandnew', $this->inline( do_blocks( '<!-- wp:joo/tag-list {"orderby":"name"} /-->' ) ), 'a new tag invalidates the cached list' );
		$this->assertSame(
			$this->inline( do_blocks( '<!-- wp:joo/tag-list {"orderby":"name"} /-->' ) ),
			$this->fragment(
				'tag-list',
				array(
					'limit'   => '24',
					'orderby' => 'name',
					'counts'  => '1',
					'style'   => 'chips',
					'title'   => '',
				)
			)
		);
	}

	public function test_tag_weights_are_bounded(): void {
		$this->assertSame( 1.0, TagList::weight( 5, 5, 5 ) );
		$this->assertSame( 0.85, TagList::weight( 1, 1, 9 ) );
		$this->assertSame( 1.15, TagList::weight( 9, 1, 9 ) );
		$this->assertSame( 1.0, TagList::weight( 5, 1, 9 ) );
		$this->assertSame( 1.15, TagList::weight( 99, 1, 9 ), 'values above the maximum are clamped' );
	}

	public function test_sidebar_fragments_are_deferred_with_a_noscript_copy_when_lazy_is_on(): void {
		$this->boot();
		self::factory()->post->create( array( 'tags_input' => array( 'lazy-tag' ) ) );
		wp_cache_flush();
		$html = do_blocks( '<!-- wp:joo/tag-list /-->' );
		$this->assertStringContainsString( '<joo-lazy ', $html );
		$this->assertStringContainsString( 'name="tag-list"', $html );
		$this->assertMatchesRegularExpression( '#<noscript>.*lazy-tag.*</noscript>#s', $html );
	}
}
