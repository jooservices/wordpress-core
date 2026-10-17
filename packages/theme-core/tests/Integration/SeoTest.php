<?php
/**
 * SEO head (real WordPress).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\Factories\AttachmentFactory;
use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\EditorPanels;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Compat\Detector;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Kernel;
use JOOservices\ThemeCore\Seo\Canonical;
use JOOservices\ThemeCore\Seo\Context;
use JOOservices\ThemeCore\Seo\DescriptionBuilder;
use JOOservices\ThemeCore\Seo\HeadPrinter;
use JOOservices\ThemeCore\Seo\Hreflang;
use JOOservices\ThemeCore\Seo\RobotsBuilder;
use JOOservices\ThemeCore\Seo\SeoModule;
use JOOservices\ThemeCore\Seo\SocialTags;
use JOOservices\ThemeCore\Seo\TitleBuilder;
use JOOservices\ThemeCore\Seo\YieldPolicy;

final class SeoTest extends IntegrationTestCase {

	private const MANIFEST = __DIR__ . '/../Fixtures/manifests/perf.json';

	public function set_up(): void {
		parent::set_up();
		Kernel::reset();
		Notices::reset();
		$this->set_permalink_structure( '/%postname%/' );
		create_initial_taxonomies();
		$GLOBALS['wp_rewrite']->flush_rules();
		update_option( 'blogname', 'Joo Blog' );
		update_option( 'blogdescription', 'Notes on engineering' );
	}

	public function tear_down(): void {
		Kernel::reset();
		parent::tear_down();
	}

	/**
	 * Boots the kernel (settings forced through the filter) and returns the printer.
	 *
	 * @param array<string, mixed> $settings Setting values by key.
	 */
	private function boot( array $settings = array(), string $manifest = self::MANIFEST ): HeadPrinter {
		if ( array() !== $settings ) {
			add_filter( 'joo/settings/value', static fn( $value, $key ) => array_key_exists( $key, $settings ) ? $settings[ $key ] : $value, 10, 2 );
		}
		Kernel::boot( $manifest );
		do_action( 'after_setup_theme' );
		return Kernel::container()->get( HeadPrinter::class );
	}

	/**
	 * Visits a URL and returns the rendered head tags joined by newlines.
	 *
	 * @param HeadPrinter $printer Printer.
	 * @param string      $url     URL.
	 */
	private function head( HeadPrinter $printer, string $url ): string {
		$this->go_to( $url );
		$printer->attach();
		return implode( "\n", $printer->render() );
	}

	private function occurrences( string $needle, string $haystack ): int {
		return substr_count( $haystack, $needle );
	}

	public function test_context_is_resolved_once_per_request(): void {
		$printer = $this->boot();
		$post    = self::factory()->post->create( array( 'post_title' => 'Once' ) );
		$this->go_to( get_permalink( $post ) );
		$printer->attach();
		$printer->title();
		$printer->render();
		$printer->render();
		$this->assertSame( 1, $printer->evaluations() );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function contextUrls(): array {
		return array(
			'home'   => array( '/', 'front' ),
			'search' => array( '/?s=hello', 'search' ),
			'404'    => array( '/definitely-missing/', '404' ),
			'date'   => array( '/2020/01/', 'date' ),
			'page 2' => array( '/page/2/', 'front' ),
		);
	}

	/**
	 * @dataProvider contextUrls
	 */
	public function test_context_types( string $url, string $type ): void {
		$this->boot();
		self::factory()->post->create_many( 12, array( 'post_date' => '2020-01-15 10:00:00' ) );
		$this->go_to( $url );
		$this->assertSame( $type, Context::fromGlobals()->type() );
	}

	public function test_single_post_head_has_one_of_each_tag_and_no_wordpress_duplicates(): void {
		$printer = $this->boot();
		$post    = self::factory()->post->create(
			array(
				'post_title'   => 'Fast WordPress themes',
				'post_excerpt' => 'How to build fast themes without plugins.',
			)
		);
		$this->go_to( get_permalink( $post ) );
		$printer->attach();
		$head = $this->capture( 'wp_head' );
		$this->assertSame( 1, $this->occurrences( '<meta name="description"', $head ) );
		$this->assertSame( 1, $this->occurrences( 'rel="canonical"', $head ), 'rel_canonical from core is removed' );
		$this->assertSame( 0, $this->occurrences( 'rel=\'shortlink\'', $head ) + $this->occurrences( 'rel="shortlink"', $head ) );
		$this->assertSame( 1, $this->occurrences( '<meta name=\'robots\'', $head ) + $this->occurrences( '<meta name="robots"', $head ) );
		$this->assertSame( 1, $this->occurrences( 'property="og:title"', $head ) );
		$this->assertStringContainsString( 'content="How to build fast themes without plugins."', $head );
		$this->assertSame( 'Fast WordPress themes | Joo Blog', wp_get_document_title() );
		$this->assertLessThan( strpos( $head, 'og:title' ), strpos( $head, 'name="description"' ), 'description comes before the social tags' );
	}

	public function test_title_templates_and_variables(): void {
		$printer = $this->boot( array( 'seo.title_separator' => '·' ) );
		$cat     = self::factory()->category->create( array( 'name' => 'Performance' ) );
		$post    = self::factory()->post->create(
			array(
				'post_title'    => 'Cache me',
				'post_category' => array( $cat ),
			)
		);
		$this->head( $printer, get_permalink( $post ) );
		$this->assertSame( 'Cache me · Joo Blog', wp_get_document_title() );
		$this->head( $printer, '/' );
		$this->assertSame( 'Joo Blog · Notes on engineering', wp_get_document_title() );
		$this->head( $printer, get_category_link( $cat ) );
		$this->assertSame( 'Performance · Joo Blog', wp_get_document_title() );
		$this->head( $printer, '/?s=cache' );
		$this->assertSame( 'Search results for "cache" · Joo Blog', wp_get_document_title() );
		$this->head( $printer, '/nope-nothing/' );
		$this->assertSame( 'Page not found · Joo Blog', wp_get_document_title() );
	}

	public function test_custom_template_with_unknown_variables_and_empty_parts(): void {
		$printer = $this->boot( array( 'seo.title_template.single' => '%category% %sep% %title% %unknown% %sep% %sitename%' ) );
		$post    = self::factory()->post->create(
			array(
				'post_title'    => 'T',
				'post_category' => array(),
			)
		);
		$this->head( $printer, get_permalink( $post ) );
		$title = wp_get_document_title();
		$this->assertStringNotContainsString( '%', $title );
		$this->assertStringNotContainsString( '| |', $title );
		$this->assertStringContainsString( 'Joo Blog', $title );
	}

	public function test_post_title_override_and_length_limits(): void {
		$printer = $this->boot();
		$post    = self::factory()->post->create( array( 'post_title' => 'Plain' ) );
		update_post_meta( $post, '_joo_seo_title', 'Custom <b>SEO</b> title' );
		$this->head( $printer, get_permalink( $post ) );
		$this->assertSame( 'Custom SEO title', wp_get_document_title() );

		$long = self::factory()->post->create( array( 'post_title' => str_repeat( 'Optimising Vietnamese typography ', 6 ) ) );
		$this->head( $printer, get_permalink( $long ) );
		$title = wp_get_document_title();
		$this->assertLessThanOrEqual( 70, mb_strlen( $title ) );
		$this->assertStringEndsWith( 'Joo Blog', $title, 'the site name survives truncation' );
		$this->assertStringContainsString( "\u{2026}", $title );
	}

	public function test_paged_archives_get_a_page_suffix_when_the_template_asks_for_it(): void {
		$printer = $this->boot( array( 'seo.title_template.archive' => '%title% %page% %sep% %sitename%' ) );
		$cat     = self::factory()->category->create( array( 'name' => 'Many' ) );
		self::factory()->post->create_many( 15, array( 'post_category' => array( $cat ) ) );
		$head = $this->head( $printer, get_category_link( $cat ) . 'page/2/' );
		$this->assertMatchesRegularExpression( '/^Many Page 2 of 2 \| Joo Blog$/', wp_get_document_title() );
		$this->assertStringContainsString( 'rel="canonical" href="' . get_category_link( $cat ) . 'page/2/"', $head );
	}

	public function test_description_chain(): void {
		$printer = $this->boot();
		$content = '<!-- wp:paragraph --><p>' . str_repeat( 'Sentence about caching and tuning. ', 12 ) . '</p><!-- /wp:paragraph -->';
		$post    = self::factory()->post->create(
			array(
				'post_content' => $content,
				'post_excerpt' => '',
			)
		);
		$head    = $this->head( $printer, get_permalink( $post ) );
		preg_match( '/<meta name="description" content="([^"]*)"/', $head, $m );
		$this->assertNotEmpty( $m[1] );
		$this->assertLessThanOrEqual( 160, mb_strlen( html_entity_decode( $m[1] ) ) );

		update_post_meta( $post, '_joo_seo_description', 'Hand written description.' );
		$this->assertStringContainsString( 'content="Hand written description."', $this->head( $printer, get_permalink( $post ) ) );

		$empty = self::factory()->post->create(
			array(
				'post_content' => '',
				'post_excerpt' => '',
			)
		);
		$this->assertStringContainsString( 'content="Notes on engineering"', $this->head( $printer, get_permalink( $empty ) ), 'falls back to the tagline' );

		$cat = self::factory()->category->create(
			array(
				'name'        => 'Docs',
				'description' => 'All the docs.',
			)
		);
		$this->assertStringContainsString( 'content="All the docs."', $this->head( $printer, get_category_link( $cat ) ) );
		update_term_meta( $cat, 'joo_short_description', 'Short docs blurb' );
		$this->assertStringContainsString( 'content="Short docs blurb"', $this->head( $printer, get_category_link( $cat ) ) );

		$user = self::factory()->user->create( array( 'description' => 'Writes about PHP.' ) );
		self::factory()->post->create( array( 'post_author' => $user ) );
		$this->assertStringContainsString( 'content="Writes about PHP."', $this->head( $printer, get_author_posts_url( $user ) ) );
	}

	public function test_protected_posts_reveal_nothing(): void {
		$printer = $this->boot();
		$post    = self::factory()->post->create(
			array(
				'post_password' => 'secret',
				'post_excerpt'  => 'Top secret excerpt',
				'post_title'    => 'Hidden',
			)
		);
		$head    = $this->head( $printer, get_permalink( $post ) );
		$this->assertStringNotContainsString( 'Top secret', $head );
		$this->assertStringNotContainsString( 'og:title', $head );
		$this->assertStringNotContainsString( 'rel="canonical"', $head );
		$robots = Kernel::container()->get( RobotsBuilder::class )->directives( Context::fromGlobals() );
		$this->assertTrue( $robots['noindex'] );
		$this->assertTrue( $robots['nofollow'] );
	}

	public function test_title_and_description_are_escaped(): void {
		$printer = $this->boot();
		$post    = self::factory()->post->create(
			array(
				'post_title'   => 'Tag "><script>alert(1)</script>',
				'post_excerpt' => 'Quote " and <img src=x onerror=alert(1)> done',
			)
		);
		$head    = $this->head( $printer, get_permalink( $post ) );
		$this->assertStringNotContainsString( '<script>', $head );
		$this->assertStringNotContainsString( '<img', $head );
		$this->assertStringNotContainsString( 'content="Quote "', $head );
		$this->assertStringNotContainsString( '<script>', wp_get_document_title() );
	}

	/**
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function robotsCases(): array {
		return array(
			'home'   => array( '/', true ),
			'search' => array( '/?s=x', false ),
			'404'    => array( '/not-here/', false ),
			'date'   => array( '/2020/', false ),
		);
	}

	/**
	 * @dataProvider robotsCases
	 */
	public function test_robots_matrix( string $url, bool $indexable ): void {
		$printer = $this->boot();
		$this->go_to( $url );
		$printer->attach();
		$robots = Kernel::container()->get( RobotsBuilder::class );
		$header = $robots->header( $printer->context() );
		if ( $indexable ) {
			$this->assertNull( $header );
			$this->assertStringContainsString( 'max-image-preview:large', wp_robots_to_string( $url ) );
		} else {
			$this->assertSame( 'noindex, follow', $header );
			$this->assertStringContainsString( 'noindex', wp_robots_to_string( $url ) );
			$this->assertStringNotContainsString( 'max-image-preview', wp_robots_to_string( $url ) );
		}
	}

	public function test_noindex_flag_and_paged_noindex_setting(): void {
		$printer = $this->boot( array( 'seo.paged_noindex' => true ) );
		$post    = self::factory()->post->create();
		update_post_meta( $post, '_joo_seo_noindex', true );
		$this->go_to( get_permalink( $post ) );
		$printer->attach();
		$this->assertFalse( $printer->context()->isIndexable() );
		$directives = Kernel::container()->get( RobotsBuilder::class )->directives( $printer->context() );
		$this->assertTrue( $directives['noindex'] );
		$this->assertTrue( $directives['follow'] );

		self::factory()->post->create_many( 15 );
		$this->go_to( '/page/2/' );
		$printer->attach();
		$directives = Kernel::container()->get( RobotsBuilder::class )->directives( $printer->context() );
		$this->assertTrue( $directives['noindex'] );
		$this->assertTrue( $directives['follow'] );
	}

	public function test_canonical_per_context(): void {
		$printer = $this->boot();
		$post    = self::factory()->post->create( array( 'post_title' => 'Canon' ) );
		$cat     = self::factory()->category->create( array( 'name' => 'Cc' ) );
		self::factory()->post->create( array( 'post_category' => array( $cat ) ) );
		$user = self::factory()->user->create();
		self::factory()->post->create( array( 'post_author' => $user ) );
		$tag = self::factory()->tag->create( array( 'name' => 'Tt' ) );
		wp_set_post_tags( $post, array( 'Tt' ) );

		$this->assertStringContainsString( 'rel="canonical" href="' . home_url( '/' ) . '"', $this->head( $printer, '/' ) );
		$this->assertStringContainsString( 'rel="canonical" href="' . get_permalink( $post ) . '"', $this->head( $printer, get_permalink( $post ) . '?utm_source=x&fbclid=1' ) );
		$this->assertStringContainsString( 'rel="canonical" href="' . get_category_link( $cat ) . '"', $this->head( $printer, get_category_link( $cat ) ) );
		$this->assertStringContainsString( 'rel="canonical" href="' . get_tag_link( $tag ) . '"', $this->head( $printer, get_tag_link( $tag ) ) );
		$this->assertStringContainsString( 'rel="canonical" href="' . get_author_posts_url( $user ) . '"', $this->head( $printer, get_author_posts_url( $user ) ) );
		$this->assertStringNotContainsString( 'rel="canonical"', $this->head( $printer, '/?s=x' ) );
		$this->assertStringNotContainsString( 'rel="canonical"', $this->head( $printer, '/missing-page/' ) );
	}

	public function test_canonical_override_is_validated(): void {
		$printer = $this->boot();
		$post    = self::factory()->post->create();
		update_post_meta( $post, '_joo_seo_canonical', home_url( '/elsewhere/' ) );
		$this->assertStringContainsString( 'href="' . home_url( '/elsewhere/' ) . '"', $this->head( $printer, get_permalink( $post ) ) );
		update_post_meta( $post, '_joo_seo_canonical', 'https://evil.example/steal' );
		$this->assertStringContainsString( 'href="' . get_permalink( $post ) . '"', $this->head( $printer, get_permalink( $post ) ), 'foreign host is ignored' );
		update_post_meta( $post, '_joo_seo_canonical', 'javascript:alert(1)' );
		$this->assertStringContainsString( 'href="' . get_permalink( $post ) . '"', $this->head( $printer, get_permalink( $post ) ) );
	}

	public function test_attachment_with_a_published_parent_redirects_to_it(): void {
		$printer = $this->boot();
		$parent  = self::factory()->post->create();
		$id      = AttachmentFactory::image( 800, 600 );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_parent' => $parent,
			)
		);
		$this->go_to( get_attachment_link( $id ) );
		$printer->attach();
		$this->assertSame( 'attachment', $printer->context()->type() );
		$this->assertFalse( $printer->context()->isIndexable() );
		$this->assertSame( get_permalink( $parent ), $printer->attachmentTarget( $printer->context() ) );
	}

	public function test_orphan_attachments_stay_and_are_noindexed(): void {
		$printer = $this->boot();
		$id      = AttachmentFactory::image( 800, 600 );
		$this->go_to( get_attachment_link( $id ) );
		$printer->attach();
		$this->assertNull( $printer->attachmentTarget( $printer->context() ) );
		$this->assertSame( 'noindex, follow', Kernel::container()->get( RobotsBuilder::class )->header( $printer->context() ) );
	}

	public function test_open_graph_and_twitter_for_a_post_with_a_featured_image(): void {
		$printer = $this->boot( array( 'seo.twitter_site' => '@jooservices' ) );
		$image   = AttachmentFactory::image( 1600, 900 );
		$cat     = self::factory()->category->create( array( 'name' => 'Ops' ) );
		$post    = self::factory()->post->create(
			array(
				'post_title'    => 'Social post',
				'post_excerpt'  => 'Preview text.',
				'post_category' => array( $cat ),
			)
		);
		wp_set_post_tags( $post, array( 'php', 'cache' ) );
		set_post_thumbnail( $post, $image );
		$head = $this->head( $printer, get_permalink( $post ) );
		foreach ( array( 'og:locale" content="en_US', 'og:type" content="article', 'og:title" content="Social post', 'og:description" content="Preview text.', 'og:url" content="' . get_permalink( $post ), 'og:site_name" content="Joo Blog', 'og:image:width', 'og:image:height', 'og:image:alt" content="Generated test image', 'article:published_time', 'article:modified_time', 'article:section" content="Ops', 'article:tag" content="php', 'twitter:card" content="summary_large_image', 'twitter:site" content="@jooservices', 'twitter:title', 'twitter:image' ) as $needle ) {
			$this->assertStringContainsString( $needle, $head, $needle );
		}
		$this->assertSame( 1, $this->occurrences( 'property="og:image"', $head ) );
	}

	public function test_image_chain_falls_back_in_order(): void {
		$printer = $this->boot();
		$small   = AttachmentFactory::image( 400, 300 );
		$post    = self::factory()->post->create( array( 'post_title' => 'Imgs' ) );
		set_post_thumbnail( $post, $small );
		$head = $this->head( $printer, get_permalink( $post ) );
		$this->assertStringNotContainsString( 'og:image"', $head, 'too small to be used' );
		$this->assertStringContainsString( 'twitter:card" content="summary"', $head );

		$default  = AttachmentFactory::image( 1200, 630 );
		$printer2 = $this->boot_default( $default );
		$this->assertStringContainsString( 'og:image"', $this->head( $printer2, get_permalink( $post ) ), 'site default image' );

		$content_img = AttachmentFactory::image( 1300, 700 );
		wp_update_post(
			array(
				'ID'           => $post,
				'post_content' => '<!-- wp:image {"id":' . $content_img . '} --><figure class="wp-block-image"><img src="x.jpg"/></figure><!-- /wp:image -->',
			)
		);
		$this->assertStringContainsString( pathinfo( (string) get_attached_file( $content_img ), PATHINFO_FILENAME ), $this->head( $printer2, get_permalink( $post ) ), 'first content image beats the default' );

		$override = AttachmentFactory::image( 1500, 800 );
		update_post_meta( $post, '_joo_seo_og_image', $override );
		$this->assertStringContainsString( pathinfo( (string) get_attached_file( $override ), PATHINFO_FILENAME ), $this->head( $printer2, get_permalink( $post ) ), 'override wins' );
	}

	private function boot_default( int $attachment ): HeadPrinter {
		Kernel::reset();
		remove_all_filters( 'joo/settings/value' );
		return $this->boot( array( 'seo.default_og_image' => $attachment ) );
	}

	public function test_hreflang_is_reciprocal_and_has_x_default(): void {
		$printer = $this->boot( array(), __DIR__ . '/../Fixtures/manifests/multilang.json' );
		$en      = self::factory()->post->create( array( 'post_title' => 'English' ) );
		$vi      = self::factory()->post->create( array( 'post_title' => 'Tiếng Việt' ) );
		update_post_meta( $en, '_joo_lang', 'en' );
		update_post_meta( $vi, '_joo_lang', 'vi' );
		update_post_meta( $vi, '_joo_translation_of', $en );
		$en_head = $this->head( $printer, get_permalink( $en ) );
		$vi_head = $this->head( $printer, get_permalink( $vi ) );
		foreach ( array( $en_head, $vi_head ) as $head ) {
			$this->assertStringContainsString( 'hreflang="en" href="' . get_permalink( $en ) . '"', $head );
			$this->assertStringContainsString( 'hreflang="vi" href="' . get_permalink( $vi ) . '"', $head );
			$this->assertStringContainsString( 'hreflang="x-default" href="' . get_permalink( $en ) . '"', $head );
		}
		$this->assertStringContainsString( 'og:locale" content="vi_VN"', $vi_head );
		$this->assertStringContainsString( 'og:locale:alternate" content="en_US"', $vi_head );
		$this->assertStringContainsString( 'lang="vi"', get_language_attributes() );
	}

	public function test_single_language_posts_print_no_hreflang_and_unpublished_translations_are_skipped(): void {
		$printer = $this->boot( array(), __DIR__ . '/../Fixtures/manifests/multilang.json' );
		$en      = self::factory()->post->create();
		update_post_meta( $en, '_joo_lang', 'en' );
		$this->assertStringNotContainsString( 'hreflang', $this->head( $printer, get_permalink( $en ) ) );
		$draft = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		update_post_meta( $draft, '_joo_lang', 'vi' );
		update_post_meta( $draft, '_joo_translation_of', $en );
		$this->assertStringNotContainsString( 'hreflang', $this->head( $printer, get_permalink( $en ) ) );
	}

	public function test_translation_of_cannot_point_to_itself_or_to_another_translation(): void {
		$this->boot( array(), __DIR__ . '/../Fixtures/manifests/multilang.json' );
		$a = self::factory()->post->create();
		$b = self::factory()->post->create();
		$c = self::factory()->post->create();
		update_post_meta( $a, '_joo_translation_of', $a );
		$this->assertSame( '', (string) get_post_meta( $a, '_joo_translation_of', true ) );
		update_post_meta( $b, '_joo_translation_of', $a );
		$this->assertSame( (string) $a, (string) get_post_meta( $b, '_joo_translation_of', true ) );
		update_post_meta( $c, '_joo_translation_of', $b );
		$this->assertSame( '', (string) get_post_meta( $c, '_joo_translation_of', true ), 'b is itself a translation' );
		update_post_meta( $c, '_joo_translation_of', 999999 );
		$this->assertSame( '', (string) get_post_meta( $c, '_joo_translation_of', true ) );
	}

	public function test_adapters_can_supply_alternates(): void {
		$printer = $this->boot();
		add_filter(
			'joo/seo/alternates',
			static fn() => array(
				'en'    => 'https://example.test/en/',
				'pt_BR' => 'https://example.test/pt/',
			)
		);
		$head = $this->head( $printer, '/' );
		$this->assertStringContainsString( 'hreflang="pt-BR" href="https://example.test/pt/"', $head );
		$this->assertStringContainsString( 'hreflang="x-default" href="https://example.test/en/"', $head );
	}

	public function test_yield_modes(): void {
		$this->boot();
		$settings = Kernel::container()->get( Settings::class );
		$active   = new Detector( static fn( string $k, string $n ): ?string => 'WPSEO_VERSION' === $n ? '24.1' : null );
		$none     = new Detector( static fn(): ?string => null );
		$mode     = static function ( string $value ): void {
			remove_all_filters( 'joo/settings/value' );
			add_filter( 'joo/settings/value', static fn( $v, $key ) => 'seo.yield' === $key ? $value : $v, 10, 2 );
		};
		$mode( 'auto' );
		$this->assertTrue( ( new YieldPolicy( $settings, $active ) )->shouldYield() );
		$this->assertFalse( ( new YieldPolicy( $settings, $none ) )->shouldYield() );
		$this->assertSame( 'Yielding to Yoast SEO 24.1.', ( new YieldPolicy( $settings, $active ) )->describe() );
		$mode( 'never' );
		$this->assertFalse( ( new YieldPolicy( $settings, $active ) )->shouldYield() );
		$mode( 'always' );
		$this->assertTrue( ( new YieldPolicy( $settings, $none ) )->shouldYield() );
	}

	public function test_hreflang_is_left_to_the_plugin_only_while_yielding(): void {
		$this->boot();
		$settings = Kernel::container()->get( Settings::class );
		$active   = new Detector( static fn( string $k, string $n ): ?string => 'RANK_MATH_VERSION' === $n ? '1.0' : null );
		$policy   = new YieldPolicy( $settings, $active );
		$this->assertTrue( $policy->yieldsHreflang() );
		add_filter( 'joo/settings/value', static fn( $v, $key ) => 'seo.yield_hreflang' === $key ? false : $v, 10, 2 );
		$this->assertFalse( $policy->yieldsHreflang() );
	}

	public function test_only_the_first_title_survives_when_a_plugin_prints_its_own(): void {
		$head = "<meta charset=\"utf-8\">\n<title>Plugin title</title>\n<meta name=\"x\">\n<title>Core title</title>\n<link rel=\"a\">";
		$out  = HeadPrinter::dedupeTitles( $head );
		$this->assertSame( 1, substr_count( $out, '<title' ) );
		$this->assertStringContainsString( 'Plugin title', $out );
		$this->assertStringNotContainsString( 'Core title', $out );
		$this->assertStringContainsString( '<link rel="a">', $out );
		$this->assertSame( 'no title at all', HeadPrinter::dedupeTitles( 'no title at all' ), 'a head without a title is left alone' );
	}

	public function test_yielding_removes_every_core_seo_tag_and_keeps_wordpress_defaults(): void {
		add_filter( 'joo/seo/yield', '__return_true' );
		$printer = $this->boot();
		$post    = self::factory()->post->create( array( 'post_title' => 'Yielded' ) );
		$this->go_to( get_permalink( $post ) );
		$printer->attach();
		$head = $this->capture( 'wp_head' );
		$this->assertStringNotContainsString( 'og:title', $head );
		$this->assertStringNotContainsString( '<meta name="description"', $head );
		$this->assertStringNotContainsString( 'JOOservices SEO', $head );
		$this->assertSame( 1, $this->occurrences( 'rel="canonical"', $head ), 'WordPress keeps its own canonical when we yield' );
		$this->assertStringContainsString( 'Yielded', wp_get_document_title() );
		$this->assertStringNotContainsString( ' | ', wp_get_document_title(), 'WordPress formats the title, not the core template' );
	}

	public function test_seo_disabled_in_settings_prints_nothing(): void {
		$printer = $this->boot( array( 'seo.enabled' => false ) );
		$post    = self::factory()->post->create();
		$this->go_to( get_permalink( $post ) );
		do_action( 'wp' );
		$this->assertFalse( has_action( 'wp_head', array( $printer, 'print' ) ) );
		$this->assertStringNotContainsString( 'og:title', $this->capture( 'wp_head' ) );
	}

	public function test_nothing_is_attached_for_feeds_and_rest(): void {
		$printer = $this->boot();
		$this->go_to( '/?feed=rss2' );
		$printer->attach();
		$this->assertFalse( has_action( 'wp_head', array( $printer, 'print' ) ) );
	}

	public function test_editor_fields_are_registered_and_sanitised(): void {
		$this->boot();
		$panels = Kernel::container()->get( EditorPanels::class );
		$fields = $panels->fields();
		foreach ( array( '_joo_seo_title', '_joo_seo_description', '_joo_seo_noindex', '_joo_seo_canonical', '_joo_seo_og_image', '_joo_lang', '_joo_translation_of' ) as $key ) {
			$this->assertArrayHasKey( $key, $fields, $key );
		}
		$panels->registerMeta();
		$post = self::factory()->post->create();
		update_post_meta( $post, '_joo_seo_title', '<b>Bold</b> title' );
		$this->assertSame( 'Bold title', get_post_meta( $post, '_joo_seo_title', true ) );
	}

	public function test_site_health_reports_duplicate_seo_plugins(): void {
		$this->boot();
		/** @var SeoModule $module */
		$module = new SeoModule();
		$module->setContainer( Kernel::container() );
		$result = $module->healthTest();
		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'Core prints the SEO tags', $result['description'] );
	}
}

/**
 * Robots meta string the way WordPress would print it for the current request.
 *
 * @param string $unused URL (the request is already set up).
 */
function wp_robots_to_string( string $unused ): string {
	unset( $unused );
	ob_start();
	wp_robots();
	return (string) ob_get_clean();
}
