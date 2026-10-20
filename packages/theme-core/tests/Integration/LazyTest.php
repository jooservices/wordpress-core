<?php
/**
 * Lazy-load module (real WordPress).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\Factories\AttachmentFactory;
use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\EditorPanels;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Assets\AssetRegistry;
use JOOservices\ThemeCore\Kernel;
use JOOservices\ThemeCore\Lazy\EmbedFacade;
use JOOservices\ThemeCore\Lazy\FragmentLazy;
use JOOservices\ThemeCore\Lazy\ImageLazy;
use JOOservices\ThemeCore\Lazy\Policy;
use JOOservices\ThemeCore\Lazy\SectionLazy;
use JOOservices\ThemeCore\Performance\FragmentCache;

final class LazyTest extends IntegrationTestCase {

	private const MANIFEST = __DIR__ . '/../Fixtures/manifests/perf.json';

	public function set_up(): void {
		parent::set_up();
		Kernel::reset();
		Notices::reset();
		$GLOBALS['wp_rest_server'] = null;
		$GLOBALS['wp_styles']      = new \WP_Styles();
		$GLOBALS['wp_scripts']     = new \WP_Scripts();
	}

	public function tear_down(): void {
		$GLOBALS['wp_rest_server'] = null;
		Kernel::reset();
		parent::tear_down();
	}

	private function boot(): void {
		Kernel::boot( self::MANIFEST );
		do_action( 'after_setup_theme' );
	}

	/**
	 * @template T of object
	 * @param class-string<T> $class Service class.
	 * @return T
	 */
	private function service( string $class ) {
		return Kernel::container()->get( $class );
	}

	/**
	 * Boots with settings forced through the filter and opens a single post on the front end.
	 *
	 * @param array<string, mixed> $settings Setting values by key.
	 * @return int Post id.
	 */
	private function singular( array $settings = array() ): int {
		add_filter( 'joo/settings/value', static fn( $value, $key ) => array_key_exists( $key, $settings ) ? $settings[ $key ] : $value, 10, 2 );
		$this->boot();
		$post = self::factory()->post->create();
		$this->go_to( get_permalink( $post ) );
		return $post;
	}

	/**
	 * Renders n images through the normal attachment API and returns their tags.
	 *
	 * @return list<string>
	 */
	private function images( int $n, int $id ): array {
		$out = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$out[] = wp_get_attachment_image( $id, 'joo-card' );
		}
		return $out;
	}

	public function test_only_the_first_image_is_eager_by_default(): void {
		$id = AttachmentFactory::image( 1600, 1200 );
		$this->singular();
		$tags = $this->images( 3, $id );
		$this->assertStringNotContainsString( 'loading="lazy"', $tags[0] );
		$this->assertStringContainsString( 'loading="lazy"', $tags[1] );
		$this->assertStringContainsString( 'loading="lazy"', $tags[2] );
	}

	public function test_an_image_core_marks_high_priority_is_never_made_lazy(): void {
		$this->singular();
		$service = $this->service( ImageLazy::class );
		$service->attributes( array( 'loading' => 'lazy' ), 'img', array(), 'the_content' ); // The first image uses the eager slot.
		$out = $service->attributes( array( 'fetchpriority' => 'high' ), 'img', array(), 'the_content' );
		$this->assertArrayNotHasKey( 'loading', $out, 'lazy plus high priority makes WordPress log a notice' );
	}

	public function test_a_larger_eager_count_keeps_more_images_eager(): void {
		$id = AttachmentFactory::image( 1600, 1200 );
		$this->singular( array( 'lazy.eager_count' => 2 ) );
		$tags = $this->images( 3, $id );
		$this->assertStringNotContainsString( 'loading="lazy"', $tags[0] );
		$this->assertStringNotContainsString( 'loading="lazy"', $tags[1] );
		$this->assertStringContainsString( 'loading="lazy"', $tags[2] );
	}

	public function test_with_the_master_switch_off_no_image_is_lazy(): void {
		$id = AttachmentFactory::image( 1600, 1200 );
		$this->singular( array( 'lazy.enabled' => false ) );
		foreach ( $this->images( 3, $id ) as $tag ) {
			$this->assertStringNotContainsString( 'loading="lazy"', $tag );
		}
	}

	public function test_the_post_override_off_disables_lazy_loading_for_that_post_only(): void {
		$id = AttachmentFactory::image( 1600, 1200 );
		$this->boot();
		$off = self::factory()->post->create();
		update_post_meta( $off, '_joo_lazy_override', 'off' );
		$this->go_to( get_permalink( $off ) );
		foreach ( $this->images( 3, $id ) as $tag ) {
			$this->assertStringNotContainsString( 'loading="lazy"', $tag );
		}
		$normal = self::factory()->post->create();
		$this->go_to( get_permalink( $normal ) );
		$tags = $this->images( 3, $id );
		$this->assertStringContainsString( 'loading="lazy"', $tags[2] );
	}

	public function test_yielding_to_an_optimisation_plugin_removes_core_lazy_attributes(): void {
		add_filter( 'joo/lazy/provider', static fn() => 'litespeed-cache' );
		$id = AttachmentFactory::image( 1600, 1200 );
		$this->singular();
		foreach ( $this->images( 3, $id ) as $tag ) {
			$this->assertStringNotContainsString( 'loading="lazy"', $tag );
		}
		$this->assertSame( 'yield:litespeed-cache', $this->service( Policy::class )->decide( 'image', array( 'index' => 5 ) )->reason );
	}

	public function test_an_explicit_lcp_attribute_wins_over_lazy(): void {
		$id = AttachmentFactory::image( 1600, 1200 );
		$this->singular();
		$this->images( 2, $id );
		$tag = wp_get_attachment_image(
			$id,
			'joo-card',
			false,
			array(
				'fetchpriority' => 'high',
				'loading'       => 'eager',
			)
		);
		$this->assertStringNotContainsString( 'loading="lazy"', $tag );
		$this->assertStringContainsString( 'fetchpriority="high"', $tag );
	}

	public function test_iframes_follow_the_policy(): void {
		$this->singular();
		$html = wp_filter_content_tags( '<iframe src="https://example.test/page" width="300" height="200"></iframe>' );
		$this->assertStringContainsString( 'loading="lazy"', $html );
		Kernel::reset();
		remove_all_filters( 'joo/settings/value' );
		$this->singular( array( 'lazy.iframes' => false ) );
		$this->assertStringNotContainsString( 'loading="lazy"', wp_filter_content_tags( '<iframe src="https://example.test/page" width="300" height="200"></iframe>' ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string, 3: int}>
	 */
	public static function embedUrls(): array {
		return array(
			'youtube watch'        => array( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ', 0 ),
			'youtube watch params' => array( 'https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=90', 'youtube', 'dQw4w9WgXcQ', 90 ),
			'youtu.be'             => array( 'https://youtu.be/dQw4w9WgXcQ?t=15', 'youtube', 'dQw4w9WgXcQ', 15 ),
			'youtube embed'        => array( 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ', 0 ),
			'youtube shorts'       => array( 'https://www.youtube.com/shorts/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ', 0 ),
			'youtube live'         => array( 'https://www.youtube.com/live/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ', 0 ),
			'youtube nocookie'     => array( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ', 0 ),
			'vimeo'                => array( 'https://vimeo.com/123456789', 'vimeo', '123456789', 0 ),
			'vimeo player'         => array( 'https://player.vimeo.com/video/123456789?h=abc', 'vimeo', '123456789', 0 ),
		);
	}

	/**
	 * @dataProvider embedUrls
	 */
	public function test_provider_embeds_become_click_to_load_facades( string $url, string $provider, string $id, int $start ): void {
		$this->singular();
		$iframe = '<iframe title="A talk about caching" src="' . $url . '" width="500" height="281"></iframe>';
		$html   = apply_filters( 'embed_oembed_html', $iframe, $url, array(), 0 );
		$this->assertStringContainsString( '<joo-embed class="joo-embed" provider="' . $provider . '" video="' . $id . '" start="' . $start . '" title="A talk about caching">', $html );
		$this->assertStringNotContainsString( '<iframe', $html, 'no iframe (and so no third-party request) before the click' );
		$this->assertStringContainsString( '<a class="joo-embed__link" href="https://', $html, 'the link is the no-JS fallback' );
		$this->assertStringContainsString( 'Play video: A talk about caching', $html );
	}

	public function test_unknown_providers_and_disabled_lazy_leave_the_embed_alone(): void {
		$this->singular();
		$other = '<iframe src="https://example.test/e"></iframe>';
		$this->assertSame( $other, apply_filters( 'embed_oembed_html', $other, 'https://example.test/e', array(), 0 ) );
		Kernel::reset();
		remove_all_filters( 'joo/settings/value' );
		$this->singular( array( 'lazy.iframes' => false ) );
		$yt = '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>';
		$this->assertSame( $yt, apply_filters( 'embed_oembed_html', $yt, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', array(), 0 ) );
	}

	public function test_thumbnails_are_remote_only_when_chosen(): void {
		$this->singular();
		$url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
		$this->assertStringNotContainsString( 'i.ytimg.com', apply_filters( 'embed_oembed_html', '<iframe></iframe>', $url, array(), 0 ), 'local mode makes no third-party request' );
		Kernel::reset();
		remove_all_filters( 'joo/settings/value' );
		$this->singular( array( 'lazy.embed_thumbnails' => 'remote' ) );
		$html = apply_filters( 'embed_oembed_html', '<iframe></iframe>', $url, array(), 0 );
		$this->assertStringContainsString( 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $html );
		$this->assertStringContainsString( 'loading="lazy"', $html );
	}

	public function test_providers_can_be_extended(): void {
		add_filter(
			'joo/lazy/embed_providers',
			static fn( array $providers ) => $providers + array(
				'demo' => array(
					'pattern' => '#demo\.test/v/(?P<id>\d+)#',
					'watch'   => 'https://demo.test/v/%s',
					'thumb'   => '',
				),
			)
		);
		$this->singular();
		$html = apply_filters( 'embed_oembed_html', '<iframe></iframe>', 'https://demo.test/v/42', array(), 0 );
		$this->assertStringContainsString( 'provider="demo" video="42"', $html );
		$this->assertArrayHasKey( 'demo', $this->service( EmbedFacade::class )->providers() );
	}

	public function test_a_registered_fragment_is_wrapped_with_placeholder_noscript_and_rest_url(): void {
		$this->singular();
		$fragments = $this->service( FragmentLazy::class );
		$fragments->register(
			'demo',
			static fn( array $args ) => '<ul><li>' . (int) $args['limit'] . '</li></ul>',
			array(
				'limit' => array(
					'type'    => 'int',
					'default' => 4,
					'min'     => 1,
					'max'     => 12,
				),
			),
			220
		);
		$html = $fragments->wrap( 'demo', array( 'limit' => 3 ), static fn() => '<ul><li>3</li></ul>' );
		$this->assertStringContainsString( '<joo-lazy name="demo"', $html );
		$this->assertMatchesRegularExpression( '#data-src="[^"]*fragment%2Fdemo[^"]*limit=3#', $html );
		$this->assertStringContainsString( 'contain-intrinsic-size:auto 220px', $html );
		$this->assertStringContainsString( 'aria-busy="true"', $html );
		$this->assertStringContainsString( '<noscript><ul><li>3</li></ul></noscript>', $html );
	}

	public function test_fragments_are_inline_when_lazy_is_off_and_empty_fragments_vanish(): void {
		$this->singular( array( 'lazy.fragments' => false ) );
		$fragments = $this->service( FragmentLazy::class );
		$fragments->register( 'demo', static fn() => '<p>x</p>' );
		$this->assertSame( '<p>inline</p>', $fragments->wrap( 'demo', array(), static fn() => '<p>inline</p>' ) );
		Kernel::reset();
		remove_all_filters( 'joo/settings/value' );
		$this->singular();
		$this->assertSame( '', $this->service( FragmentLazy::class )->wrap( 'demo', array(), static fn() => '   ' ) );
	}

	public function test_rest_html_is_identical_to_the_inline_html(): void {
		$this->singular();
		$fragments = $this->service( FragmentLazy::class );
		$renderer  = static fn( array $args ) => '<ol class="x">' . str_repeat( '<li>row</li>', (int) $args['limit'] ) . '</ol>';
		$fragments->register(
			'rows',
			$renderer,
			array(
				'limit' => array(
					'type'    => 'int',
					'default' => 2,
					'min'     => 1,
					'max'     => 5,
				),
			)
		);
		$request   = new \WP_REST_Request( 'GET', '/joo/v1/fragment/rows' );
		$request->set_query_params( array( 'limit' => '3' ) );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $renderer( array( 'limit' => 3 ) ), $data['html'], 'REST and inline HTML are byte-identical' );
		$this->assertSame( md5( $data['html'] ), $data['etag'] );
		$this->assertStringContainsString( 'public, max-age=300', $response->get_headers()['Cache-Control'] );
		$this->assertStringStartsWith( 'W/"', $response->get_headers()['ETag'] );
	}

	public function test_fragment_endpoint_validation_and_unknown_names(): void {
		$this->singular();
		$this->service( FragmentLazy::class )->register(
			'rows',
			static fn() => '<p>x</p>',
			array(
				'limit' => array(
					'type' => 'int',
					'min'  => 1,
					'max'  => 5,
				),
				'mode'  => array(
					'type' => 'enum',
					'enum' => array( 'a', 'b' ),
				),
				'flag'  => array( 'type' => 'bool' ),
				'label' => array(
					'type'      => 'string',
					'maxLength' => 5,
				),
			)
		);
		$call = static function ( string $name, array $query ): \WP_REST_Response {
			$request = new \WP_REST_Request( 'GET', '/joo/v1/fragment/' . $name );
			$request->set_query_params( $query );
			return rest_do_request( $request );
		};
		$this->assertSame( 404, $call( 'nope', array() )->get_status() );
		$this->assertSame( 400, $call( 'rows', array( 'limit' => '99' ) )->get_status() );
		$this->assertSame( 400, $call( 'rows', array( 'limit' => 'abc' ) )->get_status() );
		$this->assertSame( 400, $call( 'rows', array( 'mode' => 'c' ) )->get_status() );
		$this->assertSame( 400, $call( 'rows', array( 'label' => 'toolongvalue' ) )->get_status() );
		$this->assertSame(
			200,
			$call(
				'rows',
				array(
					'limit'   => '2',
					'mode'    => 'a',
					'flag'    => 'true',
					'label'   => 'ok',
					'ignored' => 'x',
				)
			)->get_status()
		);
		$this->assertSame( 404, rest_do_request( new \WP_REST_Request( 'GET', '/joo/v1/fragment/' . str_repeat( 'a', 65 ) ) )->get_status() );
	}

	public function test_comment_snapshot_route_never_returns_draft_private_or_protected_content(): void {
		$this->singular();
		$this->assertSame( 400, rest_do_request( new \WP_REST_Request( 'GET', '/joo/v1/fragment/comments' ) )->get_status(), 'snapshot parent is required' );
		$this->assertSame( 400, rest_do_request( new \WP_REST_Request( 'GET', '/joo/v1/fragment/related' ) )->get_status(), 'related post is required' );
		$fragments = $this->service( FragmentLazy::class );
		$hidden    = array(
			array(
				'post_status' => 'draft',
				'post_title'  => 'Draft comments parent',
			),
			array(
				'post_status' => 'private',
				'post_title'  => 'Private comments parent',
			),
			array(
				'post_status'   => 'publish',
				'post_password' => 'secret',
				'post_title'    => 'Protected comments parent',
			),
		);
		foreach ( $hidden as $post_args ) {
			$post = self::factory()->post->create( $post_args );
			$args = array(
				'post'  => $post,
				'cpage' => 1,
			);
			$fragments->wrap( 'comments', $args, static fn() => '<p>private comment snapshot</p>' );
			$request = new \WP_REST_Request( 'GET', '/joo/v1/fragment/comments' );
			$request->set_query_params( $args );
			$this->assertSame( 404, rest_do_request( $request )->get_status(), $post_args['post_title'] );
		}
	}

	public function test_comment_snapshots_are_not_created_for_logged_in_requests(): void {
		$post = $this->singular();
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user );
		$html = '<div class="wp-block-comments"><form><input value="private@example.test"></form></div>';
		$this->assertSame( $html, $this->service( SectionLazy::class )->filter( $html, array( 'blockName' => 'core/comments' ) ) );
		wp_set_current_user( 0 );

		$request = new \WP_REST_Request( 'GET', '/joo/v1/fragment/comments' );
		$request->set_query_params(
			array(
				'post'  => $post,
				'cpage' => 1,
			)
		);
		$this->assertSame( 404, rest_do_request( $request )->get_status() );
	}

	public function test_comment_fragments_use_private_no_store_headers(): void {
		$post = $this->singular();
		wp_set_current_user( 0 );
		$legacy_args = array(
			'cpage' => 1,
			'post'  => $post,
		);
		$this->service( FragmentCache::class )->put(
			'snapshot:comments:' . md5( (string) wp_json_encode( $legacy_args ) ),
			'<div>legacy personalized comments</div>',
			DAY_IN_SECONDS,
			array( 'posts' ),
			'lazy'
		);
		$legacy_request = new \WP_REST_Request( 'GET', '/joo/v1/fragment/comments' );
		$legacy_request->set_query_params(
			array(
				'post'  => $post,
				'cpage' => 1,
			)
		);
		$this->assertSame( 404, rest_do_request( $legacy_request )->get_status(), 'previously cached personalized snapshots are not reused' );
		$this->service( FragmentLazy::class )->wrap(
			'comments',
			array(
				'post'  => $post,
				'cpage' => 1,
			),
			static fn() => '<div>Public comments</div>'
		);
		$request = new \WP_REST_Request( 'GET', '/joo/v1/fragment/comments' );
		$request->set_query_params(
			array(
				'post'  => $post,
				'cpage' => 1,
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'private, no-store', $response->get_headers()['Cache-Control'] );
		$this->assertSame( 'Cookie, Accept-Encoding', $response->get_headers()['Vary'] );
		$this->assertArrayNotHasKey( 'ETag', $response->get_headers() );
	}

	public function test_snapshot_fragments_return_what_was_rendered_with_the_page(): void {
		$post = $this->singular();
		$this->service( FragmentLazy::class )->register(
			'snap',
			null,
			array(
				'post' => array(
					'type' => 'int',
					'min'  => 1,
				),
			)
		);
		$call = static function () use ( $post ): \WP_REST_Response {
			$request = new \WP_REST_Request( 'GET', '/joo/v1/fragment/snap' );
			$request->set_query_params( array( 'post' => (string) $post ) );
			return rest_do_request( $request );
		};
		$this->service( FragmentLazy::class )->wrap( 'snap', array( 'post' => $post ), static fn() => '<section id="s">snapshot html</section>' );
		$missing = new \WP_REST_Request( 'GET', '/joo/v1/fragment/snap' );
		$missing->set_query_params( array( 'post' => '999999' ) );
		$this->assertSame( 404, rest_do_request( $missing )->get_status(), 'nothing was rendered for that post' );
		$response = $call();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '<section id="s">snapshot html</section>', $response->get_data()['html'] );
	}

	public function test_invalid_fragment_names_are_rejected_at_registration(): void {
		$this->singular();
		$this->expectException( \InvalidArgumentException::class );
		$this->service( FragmentLazy::class )->register( 'Bad Name', static fn() => '' );
	}

	public function test_fragment_names_are_bounded_at_registration(): void {
		$this->singular();
		$this->expectException( \InvalidArgumentException::class );
		$this->service( FragmentLazy::class )->register( str_repeat( 'a', 65 ), static fn() => '' );
	}

	public function test_sections_get_the_content_visibility_class_when_lazy(): void {
		$this->singular();
		$sections = $this->service( SectionLazy::class );
		$html     = $sections->filter( '<section class="wp-block-joo-related-posts"><h2>Related</h2></section>', array( 'blockName' => 'joo/related-posts' ) );
		$this->assertStringContainsString( 'joo-cv', $html );
		$this->assertStringContainsString( 'data-joo-section="joo/related-posts"', $html );
		$this->assertStringContainsString( 'wp-block-joo-related-posts', $html, 'existing classes are kept' );
		$this->assertSame( '<p>x</p>', $sections->filter( '<p>x</p>', array( 'blockName' => 'core/paragraph' ) ) );
		$this->assertSame( '', $sections->filter( '', array( 'blockName' => 'joo/related-posts' ) ) );
	}

	public function test_sections_stay_untouched_when_the_switch_is_off(): void {
		$this->singular( array( 'lazy.sections' => false ) );
		$html = '<section class="x"></section>';
		$this->assertSame( $html, $this->service( SectionLazy::class )->filter( $html, array( 'blockName' => 'joo/author-box' ) ) );
	}

	public function test_comments_are_deferred_with_a_button_and_a_snapshot(): void {
		$post = $this->singular();
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post,
				'comment_approved' => 1,
			)
		);
		global $wp_query, $post_obj;
		$post_obj = get_post( $post );
		setup_postdata( $post_obj );
		$comments = '<div class="wp-block-comments"><ol><li id="comment-1">Hi</li></ol></div>';
		$html     = $this->service( SectionLazy::class )->filter( $comments, array( 'blockName' => 'core/comments' ) );
		$this->assertStringContainsString( '<joo-lazy name="comments"', $html );
		$this->assertStringContainsString( 'data-joo-load', $html );
		$this->assertStringContainsString( 'Show 1 comment', $html );
		$this->assertStringContainsString( '<noscript>' . $comments . '</noscript>', $html );
		$request = new \WP_REST_Request( 'GET', '/joo/v1/fragment/comments' );
		$request->set_query_params(
			array(
				'post'  => (string) $post,
				'cpage' => '1',
			)
		);
		$this->assertSame( $comments, rest_do_request( $request )->get_data()['html'] );
		// A second pass over the wrapped markup must not wrap it again or replace the snapshot.
		$again = $this->service( SectionLazy::class )->filter( $html, array( 'blockName' => 'core/comments' ) );
		$this->assertSame( $html, $again );
		$this->assertSame( $comments, rest_do_request( $request )->get_data()['html'] );
		unset( $wp_query );
	}

	public function test_comments_are_inline_when_that_switch_is_off(): void {
		$post = $this->singular( array( 'lazy.comments' => false ) );
		setup_postdata( get_post( $post ) );
		$comments = '<div class="wp-block-comments"></div>';
		$this->assertSame( $comments, $this->service( SectionLazy::class )->filter( $comments, array( 'blockName' => 'core/comments' ) ) );
	}

	public function test_the_override_panel_and_lazy_modules_are_registered(): void {
		$this->boot();
		$fields = $this->service( EditorPanels::class )->fields();
		$this->assertArrayHasKey( '_joo_lazy_override', $fields );
		$this->assertSame( array( 'default', 'on', 'off' ), $fields['_joo_lazy_override']['enum'] );
		$this->assertEqualsCanonicalizing(
			array( 'lazy', 'embed' ),
			array_keys(
				array_intersect_key(
					$this->service( AssetRegistry::class )->lazyModules(),
					array(
						'lazy'  => 1,
						'embed' => 1,
					)
				)
			)
		);
	}
}
