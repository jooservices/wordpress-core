<?php
/**
 * Content blocks (real WordPress, companion plugin loaded).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\Factories\AttachmentFactory;
use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Blocks\Blocks\RelatedPosts;
use JOOservices\ThemeCore\Blocks\BlockRegistrar;
use JOOservices\ThemeCore\Blocks\HeadingAnchors;
use JOOservices\ThemeCore\Kernel;
use JOOservices\ThemeCore\Lazy\FragmentLazy;
use JOOservices\ThemeCore\Seo\Schema\GraphPrinter;

final class ContentBlocksTest extends IntegrationTestCase {

	private const FIXTURES = '/workspace/packages/theme-core/tests/Fixtures/manifests/';

	/**
	 * Block names registered by the core library.
	 */
	private const BLOCKS = array( 'joo/breadcrumbs', 'joo/post-meta', 'joo/featured-slider', 'joo/toc', 'joo/reading-progress', 'joo/related-posts', 'joo/author-box', 'joo/theme-toggle', 'joo/lang-switcher', 'joo/search-trigger' );

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
		foreach ( self::BLOCKS as $name ) {
			if ( \WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}
		Kernel::reset();
		$GLOBALS['wp_rest_server'] = null;
		parent::tear_down();
	}

	private function boot( string $manifest = 'perf.json' ): BlockRegistrar {
		Kernel::boot( self::FIXTURES . $manifest );
		do_action( 'after_setup_theme' );
		$registrar = Kernel::container()->get( BlockRegistrar::class );
		$registrar->register();
		return $registrar;
	}

	private function visit( int $post ): void {
		$this->go_to( get_permalink( $post ) );
		the_post();
	}

	public function test_every_core_block_is_registered_and_has_its_own_class_or_template(): void {
		$registrar = $this->boot();
		foreach ( self::BLOCKS as $name ) {
			$this->assertContains( $name, $registrar->names(), $name );
			$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( $name ) );
		}
	}

	public function test_breadcrumbs_match_the_json_ld_trail_and_honour_attributes(): void {
		$this->boot();
		$parent = self::factory()->category->create( array( 'name' => 'Research & Development' ) );
		$child  = self::factory()->category->create(
			array(
				'name'   => 'Homelab',
				'parent' => $parent,
			)
		);
		$post   = self::factory()->post->create(
			array(
				'post_title'    => 'Rack <b>build</b>',
				'post_category' => array( $child ),
			)
		);
		$this->go_to( get_permalink( $post ) );
		$html = do_blocks( '<!-- wp:joo/breadcrumbs /-->' );
		preg_match_all( '#<li>(.*?)</li>#s', $html, $m );
		$labels = array_map( static fn( $li ) => html_entity_decode( wp_strip_all_tags( $li ) ), $m[1] );
		$this->assertSame( array( 'Home', 'Research & Development', 'Homelab', 'Rack build' ), $labels );
		$this->assertStringContainsString( '<nav', $html );
		$this->assertStringContainsString( 'aria-label="Breadcrumb"', $html );
		$this->assertSame( 1, substr_count( $html, 'aria-current="page"' ) );
		$this->assertStringContainsString( '<a href="' . esc_url( get_category_link( $child ) ) . '"', $html );
		$this->assertStringContainsString( 'joo-breadcrumbs--slash', $html );
		$this->assertStringContainsString( 'joo-breadcrumbs--chevron', do_blocks( '<!-- wp:joo/breadcrumbs {"separator":"chevron"} /-->' ) );
		$this->assertStringNotContainsString( '>Home<', do_blocks( '<!-- wp:joo/breadcrumbs {"showHome":false} /-->' ) );
		$long = do_blocks( '<!-- wp:joo/breadcrumbs {"maxLabel":20} /-->' );
		$this->assertStringContainsString( 'title="Research &amp; Development"', $long );
		$this->assertStringContainsString( '…', do_blocks( '<!-- wp:joo/breadcrumbs {"maxLabel":20} /-->' ) . '…' );
	}

	public function test_breadcrumb_html_and_json_ld_agree(): void {
		$this->boot();
		$cat  = self::factory()->category->create( array( 'name' => 'Agree' ) );
		$post = self::factory()->post->create(
			array(
				'post_title'    => 'Same trail',
				'post_category' => array( $cat ),
			)
		);
		$this->go_to( get_permalink( $post ) );
		do_action( 'wp' );
		$head = $this->capture( 'wp_head' );
		preg_match( '#<script type="application/ld\+json" class="joo-schema">(.*?)</script>#s', $head, $json );
		$doc   = json_decode( $json[1], true );
		$names = array();
		foreach ( $doc['@graph'] as $node ) {
			if ( 'BreadcrumbList' === $node['@type'] ) {
				$names = array_column( $node['itemListElement'], 'name' );
			}
		}
		preg_match_all( '#<li>(.*?)</li>#s', do_blocks( '<!-- wp:joo/breadcrumbs /-->' ), $m );
		$this->assertSame( $names, array_map( static fn( $li ) => html_entity_decode( wp_strip_all_tags( $li ) ), $m[1] ) );
		$this->assertInstanceOf( GraphPrinter::class, Kernel::container()->get( GraphPrinter::class ) );
	}

	public function test_post_meta_items_dates_and_the_updated_rule(): void {
		$this->boot();
		$cat  = self::factory()->category->create( array( 'name' => 'Meta cat' ) );
		$post = self::factory()->post->create(
			array(
				'post_category' => array( $cat ),
				'post_date'     => '2024-01-05 10:00:00',
				'post_content'  => '<p>' . str_repeat( 'word ', 450 ) . '</p>',
			)
		);
		$this->visit( $post );
		$html = do_blocks( '<!-- wp:joo/post-meta /-->' );
		$this->assertStringContainsString( '>Meta cat</a>', $html );
		$this->assertStringContainsString( '<time class="joo-meta__date" datetime="2024-01-05T10:00:00+00:00">', $html );
		$this->assertStringContainsString( '3 min read', $html );
		$this->assertStringNotContainsString( 'joo-meta__updated', $html, 'not updated yet' );

		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => '2024-03-01 10:00:00',
				'post_modified_gmt' => '2024-03-01 10:00:00',
			),
			array( 'ID' => $post )
		);
		clean_post_cache( $post );
		$this->assertStringContainsString( 'joo-meta__updated', do_blocks( '<!-- wp:joo/post-meta /-->' ) );
		$only = do_blocks( '<!-- wp:joo/post-meta {"items":["reading","bogus","author"],"separator":"slash","linkCategory":false} /-->' );
		$this->assertStringContainsString( 'joo-meta--slash', $only );
		$this->assertStringNotContainsString( 'bogus', $only );
		$this->assertStringContainsString( 'joo-meta__author', $only );
		$this->assertStringNotContainsString( 'joo-meta__category', $only );
		$this->assertStringContainsString( '>Meta cat</span>', do_blocks( '<!-- wp:joo/post-meta {"linkCategory":false} /-->' ) );
	}

	public function test_post_meta_works_in_a_query_loop_context_and_is_empty_without_a_post(): void {
		$this->boot();
		$post  = self::factory()->post->create( array( 'post_title' => 'Loop post' ) );
		$block = new \WP_Block(
			array(
				'blockName' => 'joo/post-meta',
				'attrs'     => array( 'items' => array( 'date' ) ),
			),
			array( 'postId' => $post )
		);
		$this->assertStringContainsString( 'joo-meta__date', $block->render() );
		$this->go_to( '/' );
		$GLOBALS['post'] = null;
		$this->assertSame( '', do_blocks( '<!-- wp:joo/post-meta /-->' ) );
	}

	public function test_reading_time_is_the_same_in_cards_meta_and_json_ld(): void {
		$this->boot();
		$post = self::factory()->post->create(
			array(
				'post_content' => '<p>' . str_repeat( 'word ', 700 ) . '</p>',
				'post_author'  => 1,
			)
		);
		update_post_meta( $post, '_joo_featured', true );
		$minutes = \JOOservices\ThemeCore\Support\ReadingTime::forPost( $post )['minutes'];
		$this->assertSame( 4, $minutes );
		$card = Kernel::container()->get( \JOOservices\ThemeCore\Content\ContentProviderInterface::class )->featured( 1 )[0];
		$this->assertSame( $minutes, $card->readingMinutes );
		$this->visit( $post );
		$this->assertStringContainsString( $minutes . ' min read', do_blocks( '<!-- wp:joo/post-meta /-->' ) );
		do_action( 'wp' );
		$head = $this->capture( 'wp_head' );
		$this->assertMatchesRegularExpression( '/"timeRequired":\s*"PT' . $minutes . 'M"/', $head );
	}

	public function test_featured_slider_markup_eager_first_image_and_empty_state(): void {
		$this->boot();
		$this->assertSame( '', trim( do_blocks( '<!-- wp:joo/featured-slider /-->' ) ), 'nothing featured: nothing printed' );
		$ids = array();
		foreach ( array( 'One', 'Two', 'Three' ) as $title ) {
			$id = self::factory()->post->create(
				array(
					'post_title'   => $title . ' <script>x</script>',
					'post_excerpt' => 'Excerpt ' . $title,
				)
			);
			set_post_thumbnail( $id, AttachmentFactory::image( 900, 400 ) );
			update_post_meta( $id, '_joo_featured', true );
			update_post_meta( $id, '_joo_featured_order', count( $ids ) );
			$ids[] = $id;
		}
		wp_cache_flush();
		Kernel::reset();
		$this->boot();
		$html = do_blocks( '<!-- wp:joo/featured-slider {"limit":3,"interval":4000} /-->' );
		$this->assertSame( 3, substr_count( $html, 'class="joo-slide"' ) );
		$this->assertStringContainsString( 'aria-roledescription="carousel"', $html );
		$this->assertStringContainsString( 'aria-label="1 of 3"', $html );
		$this->assertStringContainsString( 'data-autoplay="1" data-interval="4000"', $html );
		$this->assertSame( 1, substr_count( $html, 'fetchpriority="high"' ) );
		$this->assertSame( 2, substr_count( $html, 'loading="lazy"' ), 'only the first slide image is eager' );
		foreach ( $ids as $id ) {
			$this->assertStringContainsString( 'href="' . esc_url( get_permalink( $id ) ) . '"', $html, 'every slide links to its post' );
		}
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'joo-slider__controls', $html );
		$this->assertStringContainsString( 'data-autoplay="0"', do_blocks( '<!-- wp:joo/featured-slider {"autoplay":false} /-->' ) );
	}

	public function test_a_single_featured_post_has_no_controls_or_autoplay(): void {
		$id = self::factory()->post->create();
		update_post_meta( $id, '_joo_featured', true );
		$this->boot();
		$html = do_blocks( '<!-- wp:joo/featured-slider /-->' );
		$this->assertStringNotContainsString( 'joo-slider__controls', $html );
		$this->assertStringContainsString( 'data-autoplay="0"', $html );
	}

	public function test_toc_ids_agree_with_the_heading_filter_and_the_hide_rule(): void {
		$this->boot();
		$content = '<!-- wp:heading --><h2 class="wp-block-heading">Intro</h2><!-- /wp:heading -->'
			. '<!-- wp:paragraph --><p>text</p><!-- /wp:paragraph -->'
			. '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Tối ưu hiệu năng</h3><!-- /wp:heading -->'
			. '<!-- wp:heading --><h2 class="wp-block-heading">Intro</h2><!-- /wp:heading -->'
			. '<!-- wp:heading {"level":5} --><h5 class="wp-block-heading">Deep</h5><!-- /wp:heading -->';
		$post    = self::factory()->post->create( array( 'post_content' => $content ) );
		$this->visit( $post );
		$rendered = apply_filters( 'the_content', get_post_field( 'post_content', $post ) );
		preg_match_all( '/<h[2-4][^>]*\sid="([^"]+)"/', $rendered, $ids );
		$this->assertSame( array( 'intro', 'toi-uu-hieu-nang', 'intro-2' ), $ids[1] );
		$this->assertStringContainsString( 'class="joo-anchor"', $rendered );
		$this->assertStringContainsString( 'aria-label="Link to section"', $rendered );
		$this->assertStringNotContainsString( 'id="deep"', $rendered, 'h5 is left alone' );

		$toc = do_blocks( '<!-- wp:joo/toc {"levels":["h2","h3"]} /-->' );
		preg_match_all( '/<a href="#([^"]+)">/', $toc, $links );
		$this->assertSame( $ids[1], $links[1], 'TOC links match the heading ids' );
		$this->assertStringContainsString( 'joo-toc__l3', $toc );
		$this->assertStringContainsString( ' open', $toc );
		$this->assertStringNotContainsString( ' open', do_blocks( '<!-- wp:joo/toc {"collapsed":true} /-->' ) );
		$this->assertSame( '', trim( do_blocks( '<!-- wp:joo/toc {"minHeadings":4} /-->' ) ), 'fewer headings than the minimum hides the block' );
		$this->assertCount( 2, HeadingAnchors::outline( $content, array( 2 ) ) );
	}

	public function test_heading_filter_only_touches_singular_h2_to_h4_and_keeps_custom_anchors(): void {
		$this->boot();
		$this->go_to( '/' );
		$this->assertStringNotContainsString( 'joo-anchor', (string) apply_filters( 'the_content', '<!-- wp:heading --><h2 class="wp-block-heading">Home</h2><!-- /wp:heading -->' ) );
		$post = self::factory()->post->create( array( 'post_content' => '<!-- wp:heading {"anchor":"custom"} --><h2 class="wp-block-heading" id="custom">Named</h2><!-- /wp:heading -->' ) );
		$this->visit( $post );
		$out = (string) apply_filters( 'the_content', get_post_field( 'post_content', $post ) );
		$this->assertSame( 1, substr_count( $out, 'id="custom"' ) );
		$this->assertStringContainsString( 'href="#custom"', $out );
	}

	public function test_headings_rendered_with_surrounding_newlines_still_get_an_anchor(): void {
		$this->boot();
		$post = self::factory()->post->create();
		$this->visit( $post );
		// WordPress 7 renders the heading block with a leading newline.
		$out = ( new HeadingAnchors() )->filter( "\n<h2 class=\"wp-block-heading\">Spaced out</h2>\n", array() );
		$this->assertStringContainsString( 'id="spaced-out"', $out );
		$this->assertStringContainsString( 'href="#spaced-out"', $out );
	}

	public function test_code_blocks_get_a_copy_button_and_the_module_loads_only_then(): void {
		$this->boot();
		$post = self::factory()->post->create();
		$this->visit( $post );
		$plain = (string) apply_filters( 'the_content', '<!-- wp:paragraph --><p>no code</p><!-- /wp:paragraph -->' );
		$this->assertStringNotContainsString( 'joo-code', $plain );
		$code = (string) apply_filters( 'the_content', '<!-- wp:code --><pre class="wp-block-code"><code class="language-php">echo "hi";</code></pre><!-- /wp:code -->' );
		$this->assertStringContainsString( '<div class="joo-code" data-lang="php">', $code );
		$this->assertStringContainsString( 'class="joo-code__copy" hidden', $code );
		$this->assertStringContainsString( 'aria-label="Copy code"', $code );
		$this->assertStringContainsString( 'role="status"', $code );
		$twice = (string) apply_filters( 'the_content', $code );
		$this->assertSame( 1, substr_count( $twice, 'class="joo-code"' ), 'already wrapped' );
		$nolang = (string) apply_filters( 'the_content', '<!-- wp:code --><pre class="wp-block-code"><code>plain</code></pre><!-- /wp:code -->' );
		$this->assertStringNotContainsString( 'data-lang', $nolang );
	}

	public function test_related_posts_inline_and_fragment_html_are_identical(): void {
		$this->boot();
		$cat = self::factory()->category->create( array( 'name' => 'Rel' ) );
		$src = self::factory()->post->create( array( 'post_category' => array( $cat ) ) );
		$one = self::factory()->post->create(
			array(
				'post_category' => array( $cat ),
				'post_title'    => 'Related one',
			)
		);
		$this->visit( $src );
		$html = do_blocks( '<!-- wp:joo/related-posts {"limit":2} /-->' );
		$this->assertStringContainsString( '<joo-lazy ', $html, 'secondary content is deferred' );
		$this->assertStringContainsString( 'name="related"', $html );
		$this->assertMatchesRegularExpression( '#data-src="[^"]*fragment/related\?post=' . $src . '#', $html );
		preg_match( '#<noscript>(.*)</noscript>#s', $html, $inline );
		$this->assertStringContainsString( 'Related one', $inline[1] );

		$request = new \WP_REST_Request( 'GET', '/joo/v1/fragment/related' );
		$request->set_query_params(
			array(
				'post'    => (string) $src,
				'limit'   => '2',
				'image'   => '1',
				'excerpt' => '0',
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( trim( $inline[1] ), trim( $response->get_data()['html'] ), 'fragment HTML equals the inline HTML' );
		unset( $one );
	}

	public function test_related_posts_render_inline_when_deferral_is_off_and_nothing_when_empty(): void {
		add_filter( 'joo/settings/value', static fn( $v, $key ) => 'lazy.fragments' === $key ? false : $v, 10, 2 );
		$this->boot();
		$cat = self::factory()->category->create( array( 'name' => 'Rel2' ) );
		$src = self::factory()->post->create( array( 'post_category' => array( $cat ) ) );
		$this->visit( $src );
		$this->assertSame( '', trim( do_blocks( '<!-- wp:joo/related-posts /-->' ) ), 'no related posts: nothing' );
		self::factory()->post->create(
			array(
				'post_category' => array( $cat ),
				'post_title'    => 'Inline rel',
			)
		);
		Kernel::container()->get( \JOOservices\ThemeCore\Performance\FragmentCache::class )->bump( 'posts' );
		$html = do_blocks( '<!-- wp:joo/related-posts /-->' );
		$this->assertStringNotContainsString( 'joo-lazy', $html );
		$this->assertStringContainsString( 'Inline rel', $html );
		$this->assertInstanceOf( FragmentLazy::class, Kernel::container()->get( FragmentLazy::class ) );
		$this->assertSame( 'related', RelatedPosts::FRAGMENT );
	}

	public function test_author_box_for_the_owner_and_for_other_authors(): void {
		$this->boot();
		wp_update_user(
			array(
				'ID'           => 1,
				'display_name' => 'Owner Name',
				'description'  => 'Owner bio text.',
			)
		);
		update_user_meta( 1, 'joo_title', 'CTO' );
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
		$mine = self::factory()->post->create( array( 'post_author' => 1 ) );
		$this->visit( $mine );
		wp_cache_flush();
		$html = do_blocks( '<!-- wp:joo/author-box /-->' );
		$this->assertStringContainsString( 'Owner Name', $html );
		$this->assertStringContainsString( 'CTO', $html );
		$this->assertStringContainsString( 'rel="author"', $html );
		$this->assertStringContainsString( 'rel="me noopener"', $html );
		$this->assertStringContainsString( 'aria-label="About the author"', $html );

		$guest = self::factory()->user->create(
			array(
				'display_name' => 'Guest Writer',
				'description'  => 'Guest bio.',
			)
		);
		$post  = self::factory()->post->create( array( 'post_author' => $guest ) );
		$this->visit( $post );
		$other = do_blocks( '<!-- wp:joo/author-box /-->' );
		$this->assertStringContainsString( 'Guest bio.', $other );
		$this->assertStringContainsString( 'Guest Writer', $other );
		$this->assertStringNotContainsString( 'rel="me', $other );
	}

	public function test_theme_toggle_language_switcher_search_trigger_and_progress(): void {
		$this->boot( 'multilang.json' );
		$toggle = do_blocks( '<!-- wp:joo/theme-toggle /-->' );
		$this->assertStringContainsString( '<joo-theme-toggle', $toggle );
		$this->assertStringContainsString( 'aria-pressed="true"', $toggle );
		$this->assertStringContainsString( 'hidden>', $toggle );
		$this->assertStringContainsString( 'aria-hidden="true"', $toggle );

		$post = self::factory()->post->create();
		update_post_meta( $post, '_joo_lang', 'en' );
		$this->visit( $post );
		$switcher = do_blocks( '<!-- wp:joo/lang-switcher /-->' );
		$this->assertStringContainsString( 'hreflang="en"', $switcher );
		$this->assertStringContainsString( 'hreflang="vi"', $switcher );
		$this->assertSame( 1, substr_count( $switcher, 'aria-current="true"' ) );
		$this->assertStringContainsString( '<details', do_blocks( '<!-- wp:joo/lang-switcher {"style":"dropdown"} /-->' ) );
		$this->assertSame( 1, substr_count( do_blocks( '<!-- wp:joo/lang-switcher {"hideCurrent":true} /-->' ), '<li>' ) );

		$search = do_blocks( '<!-- wp:joo/search-trigger {"label":"Find"} /-->' );
		$this->assertStringContainsString( '<dialog', $search );
		$this->assertStringContainsString( 'action="' . esc_url( home_url( '/' ) ) . '"', $search );
		$this->assertStringContainsString( 'href="' . esc_url( home_url( '/?s=' ) ) . '"', $search );
		$this->assertStringContainsString( 'name="s"', $search );

		$this->assertStringContainsString( 'role="progressbar"', do_blocks( '<!-- wp:joo/reading-progress /-->' ) );
		$this->go_to( '/' );
		$this->assertSame( '', trim( do_blocks( '<!-- wp:joo/reading-progress /-->' ) ), 'only on singular pages' );
	}

	public function test_toggle_and_switcher_are_hidden_when_there_is_nothing_to_switch(): void {
		$this->boot( 'single-scheme.json' );
		$this->assertSame( '', trim( do_blocks( '<!-- wp:joo/theme-toggle /-->' ) ) );
		$this->assertSame( '', trim( do_blocks( '<!-- wp:joo/lang-switcher /-->' ) ), 'one language: nothing' );
	}
}
