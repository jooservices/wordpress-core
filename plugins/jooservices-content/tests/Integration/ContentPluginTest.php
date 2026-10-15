<?php
/**
 * Companion plugin (real WordPress).
 *
 * @package JOOservices\Content
 */

declare(strict_types=1);

namespace JOOservices\Content\Tests\Integration;

use JOOservices\Content\Meta\Featured;
use JOOservices\Content\Meta\TermMeta;
use JOOservices\Content\Meta\UserMeta;
use JOOservices\Content\Plugin;
use JOOservices\Content\PostTypes\Company;
use JOOservices\Content\Support\Validate;
use JOOservices\TestKit\Factories\AttachmentFactory;
use JOOservices\TestKit\IntegrationTestCase;

final class ContentPluginTest extends IntegrationTestCase {

	public function set_up(): void {
		parent::set_up();
		Plugin::register();
		global $wp_rest_server;
		$wp_rest_server = null;
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	/**
	 * @param array<string, mixed> $meta   Meta.
	 * @param array<string, mixed> $extra  Extra body fields.
	 */
	private function createCompany( array $meta, array $extra = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/wp/v2/joo-companies' );
		$request->set_body_params(
			array_merge(
				array(
					'title'  => 'ACME',
					'status' => 'draft',
					'meta'   => $meta,
				),
				$extra
			)
		);
		return rest_do_request( $request );
	}

	public function test_requirements_check_and_activation_flags(): void {
		$this->assertTrue( Plugin::meetsRequirements( '8.1.0', '6.6' ) );
		$this->assertFalse( Plugin::meetsRequirements( '8.0.30', '6.6' ) );
		$this->assertFalse( Plugin::meetsRequirements( '8.3.0', '6.5' ) );
		Plugin::activate();
		$this->assertSame( Plugin::VERSION, get_option( 'joo_content_version' ) );
		$this->assertSame( '1', get_option( 'joo_content_flush_rewrite' ) );
		Plugin::maybeFlushRewrites();
		$this->assertFalse( get_option( 'joo_content_flush_rewrite' ), 'the flush happens once' );
	}

	public function test_the_company_type_is_private_and_not_queryable(): void {
		$this->assertTrue( post_type_exists( 'joo_company' ) );
		$type = get_post_type_object( 'joo_company' );
		$this->assertFalse( $type->public );
		$this->assertFalse( $type->publicly_queryable );
		$this->assertFalse( $type->has_archive );
		$id = self::factory()->post->create(
			array(
				'post_type'  => 'joo_company',
				'post_title' => 'Hidden Inc',
			)
		);
		$this->set_permalink_structure( '/%postname%/' );
		$this->go_to( '/?post_type=joo_company' );
		$_GET['post_type'] = 'joo_company';
		do_action( 'template_redirect' );
		$this->assertTrue( is_404() );
		unset( $_GET['post_type'] );
		$this->go_to( '/?p=' . $id );
		do_action( 'template_redirect' );
		$this->assertTrue( is_404() );
		$this->assertFalse( is_singular( 'joo_company' ) );
	}

	public function test_rest_validates_months_url_and_period_order(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$ok = $this->createCompany(
			array(
				'_joo_company_role' => 'CTO',
				'_joo_company_from' => '2022-03',
				'_joo_company_to'   => '2025-06',
				'_joo_company_url'  => 'https://acme.test',
			)
		);
		$this->assertSame( 201, $ok->get_status() );
		$id = (int) $ok->get_data()['id'];
		$this->assertSame( 'CTO', get_post_meta( $id, '_joo_company_role', true ) );

		$this->assertSame( 400, $this->createCompany( array( '_joo_company_from' => '2022-13' ) )->get_status(), 'month 13' );
		$this->assertSame( 400, $this->createCompany( array( '_joo_company_from' => '22-03' ) )->get_status() );
		$this->assertSame( 400, $this->createCompany( array( '_joo_company_url' => 'http://insecure.test' ) )->get_status(), 'http url' );
		$this->assertSame(
			400,
			$this->createCompany(
				array(
					'_joo_company_from' => '2025-06',
					'_joo_company_to'   => '2024-01',
				)
			)->get_status(),
			'to before from'
		);
		$this->assertSame( 400, $this->createCompany( array( '_joo_company_from' => '2020-01' ), array( 'status' => 'publish' ) )->get_status(), 'publish needs a role' );
		$this->assertSame(
			201,
			$this->createCompany(
				array(
					'_joo_company_role' => 'Dev',
					'_joo_company_from' => '2020-01',
				),
				array( 'status' => 'publish' )
			)->get_status()
		);
	}

	public function test_rest_permissions(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, $this->createCompany( array() )->get_status() );
		wp_set_current_user( 0 );
		$this->assertContains( $this->createCompany( array() )->get_status(), array( 401, 403 ) );
	}

	public function test_only_one_company_can_be_pinned(): void {
		$a = self::factory()->post->create(
			array(
				'post_type'   => 'joo_company',
				'post_status' => 'publish',
			)
		);
		$b = self::factory()->post->create(
			array(
				'post_type'   => 'joo_company',
				'post_status' => 'publish',
			)
		);
		$c = self::factory()->post->create(
			array(
				'post_type'   => 'joo_company',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $a, '_joo_company_pinned', true );
		update_post_meta( $b, '_joo_company_pinned', true );
		$this->assertFalse( (bool) get_post_meta( $a, '_joo_company_pinned', true ) );
		$this->assertTrue( (bool) get_post_meta( $b, '_joo_company_pinned', true ) );
		update_post_meta( $c, '_joo_company_pinned', true );
		update_post_meta( $a, '_joo_company_pinned', true );
		$pinned = array_filter( array( $a, $b, $c ), static fn( $id ) => (bool) get_post_meta( $id, '_joo_company_pinned', true ) );
		$this->assertSame( array( $a ), array_values( $pinned ) );
		update_post_meta( $a, '_joo_company_pinned', false );
		$this->assertEmpty( array_filter( array( $a, $b, $c ), static fn( $id ) => (bool) get_post_meta( $id, '_joo_company_pinned', true ) ), 'unpinning leaves none pinned' );
	}

	public function test_classic_form_saves_with_nonce_and_reports_problems(): void {
		$user = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user );
		$id    = self::factory()->post->create(
			array(
				'post_type'   => 'joo_company',
				'post_status' => 'draft',
			)
		);
		$_POST = array(
			'joo_company_details_nonce' => wp_create_nonce( 'joo_company_details' ),
			'_joo_company_role'         => 'Lead <b>Engineer</b>',
			'_joo_company_from'         => '2021-02',
			'_joo_company_to'           => '2020-01',
			'_joo_company_url'          => 'http://nope.test',
			'_joo_company_pinned'       => '1',
		);
		( new Company() )->saveClassic( $id, get_post( $id ) );
		$this->assertSame( 'Lead Engineer', get_post_meta( $id, '_joo_company_role', true ) );
		$this->assertSame( '2021-02', get_post_meta( $id, '_joo_company_from', true ) );
		$this->assertSame( '', get_post_meta( $id, '_joo_company_to', true ), 'invalid period end dropped' );
		$this->assertSame( '', get_post_meta( $id, '_joo_company_url', true ), 'non-https URL dropped' );
		$this->assertNotEmpty( get_transient( 'joo_company_errors_' . $user ) );

		$_POST = array( '_joo_company_role' => 'Hacker' );
		( new Company() )->saveClassic( $id, get_post( $id ) );
		$this->assertSame( 'Lead Engineer', get_post_meta( $id, '_joo_company_role', true ), 'no nonce, no save' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST = array(
			'joo_company_details_nonce' => wp_create_nonce( 'joo_company_details' ),
			'_joo_company_role'         => 'Nope',
		);
		( new Company() )->saveClassic( $id, get_post( $id ) );
		$this->assertSame( 'Lead Engineer', get_post_meta( $id, '_joo_company_role', true ), 'no capability, no save' );
		$_POST = array();
	}

	public function test_company_columns(): void {
		$company = new Company();
		$id      = self::factory()->post->create(
			array(
				'post_type'  => 'joo_company',
				'menu_order' => 3,
			)
		);
		update_post_meta( $id, '_joo_company_role', 'CTO' );
		update_post_meta( $id, '_joo_company_from', '2022-03' );
		update_post_meta( $id, '_joo_company_pinned', true );
		$out = function ( string $col ) use ( $company, $id ): string {
			ob_start();
			$company->column( $col, $id );
			return (string) ob_get_clean();
		};
		$this->assertSame( 'CTO', $out( 'role' ) );
		$this->assertSame( '2022-03 – Present', $out( 'period' ) );
		$this->assertStringContainsString( '★', $out( 'pinned' ) );
		$this->assertSame( '3', $out( 'menu_order' ) );
		$this->assertArrayHasKey( 'period', $company->columns( array( 'cb' => 'x' ) ) );
		update_post_meta( $id, '_joo_company_to', '2024-05' );
		$this->assertSame( '2022-03 – 2024-05', $out( 'period' ) );
	}

	public function test_validators(): void {
		$this->assertTrue( Validate::month( '2024-12' ) );
		$this->assertFalse( Validate::month( '2024-00' ) );
		$this->assertFalse( Validate::month( '2024-1' ) );
		$this->assertFalse( Validate::month( 202412 ) );
		$this->assertTrue( Validate::periodIsValid( '2020-01', '' ) );
		$this->assertTrue( Validate::periodIsValid( '2020-01', '2020-01' ) );
		$this->assertFalse( Validate::periodIsValid( '2021-01', '2020-12' ) );
		$this->assertSame( 'https://a.test', Validate::httpsUrl( 'https://a.test' ) );
		$this->assertSame( '', Validate::httpsUrl( 'http://a.test' ) );
		$this->assertSame( '', Validate::httpsUrl( 'javascript:alert(1)' ) );
	}

	public function test_term_meta_is_registered_exposed_and_validated(): void {
		$cat   = self::factory()->category->create( array( 'name' => 'Meta cat' ) );
		$image = AttachmentFactory::image( 200, 200 );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_term_meta( $cat, 'joo_short_description', str_repeat( 'x', 300 ) );
		$this->assertSame( 160, mb_strlen( (string) get_term_meta( $cat, 'joo_short_description', true ) ) );
		update_term_meta( $cat, 'joo_image_id', $image );
		$this->assertSame( $image, (int) get_term_meta( $cat, 'joo_image_id', true ) );
		$file = self::factory()->attachment->create( array( 'post_mime_type' => 'application/pdf' ) );
		update_term_meta( $cat, 'joo_image_id', $file );
		$this->assertSame( 0, (int) get_term_meta( $cat, 'joo_image_id', true ), 'a non-image attachment is rejected' );

		$request  = new \WP_REST_Request( 'GET', '/wp/v2/categories/' . $cat );
		$response = rest_do_request( $request );
		$this->assertArrayHasKey( 'joo_short_description', $response->get_data()['meta'] );
		$this->assertArrayHasKey( 'joo_intro', $response->get_data()['meta'] );
	}

	public function test_term_forms_save_with_nonce_capability_and_forms_render(): void {
		$tag = self::factory()->tag->create( array( 'name' => 'Form tag' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$meta  = new TermMeta();
		$_POST = array(
			'joo_short_description' => 'Hello <b>there</b>',
			'joo_intro'             => 'Intro',
			'joo_image_id'          => '0',
		);
		$meta->save( $tag );
		$this->assertSame( '', (string) get_term_meta( $tag, 'joo_short_description', true ), 'no nonce, no save' );
		$_POST['joo_term_meta_nonce'] = wp_create_nonce( TermMeta::NONCE );
		$meta->save( $tag );
		$this->assertSame( 'Hello there', get_term_meta( $tag, 'joo_short_description', true ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST['joo_short_description'] = 'Changed';
		$meta->save( $tag );
		$this->assertSame( 'Hello there', get_term_meta( $tag, 'joo_short_description', true ), 'subscribers cannot edit terms' );
		$_POST = array();

		ob_start();
		$meta->renderEdit( get_term( $tag ) );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'name="joo_short_description"', $html );
		$this->assertStringContainsString( 'name="joo_image_id"', $html );
		ob_start();
		$meta->renderAdd();
		$this->assertStringContainsString( 'joo_term_meta_nonce', (string) ob_get_clean() );
		$this->assertArrayHasKey( 'joo_short', $meta->columns( array() ) );
		update_term_meta( $tag, 'joo_short_description', 'Shown in the column' );
		$this->assertStringContainsString( 'Shown in the column', $meta->column( '', 'joo_short', $tag ) );
		$this->assertSame( 'keep', $meta->column( 'keep', 'other', $tag ) );
	}

	public function test_user_profile_fields_are_validated_and_do_not_leak(): void {
		$user = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'private@example.test',
			)
		);
		wp_set_current_user( $user );
		$meta  = new UserMeta();
		$image = AttachmentFactory::image( 300, 300 );
		$_POST = array(
			'joo_user_meta_nonce' => wp_create_nonce( UserMeta::NONCE ),
			'joo_title'           => 'Engineer',
			'joo_short_bio'       => 'Builds things.',
			'joo_avatar_id'       => (string) $image,
			'joo_socials'         => array(
				array(
					'label' => 'GitHub',
					'url'   => 'https://github.com/example',
				),
				array(
					'label' => 'Insecure',
					'url'   => 'http://example.test',
				),
				array(
					'label' => '',
					'url'   => 'https://x.test/me',
				),
				array(
					'label' => 'Script',
					'url'   => 'javascript:alert(1)',
				),
			),
		);
		$meta->save( $user );
		$_POST   = array();
		$socials = get_user_meta( $user, 'joo_socials', true );
		$this->assertSame( array( 'GitHub', 'x.test' ), array_column( $socials, 'label' ) );
		$this->assertSame( 'Engineer', get_user_meta( $user, 'joo_title', true ) );

		$many = array_fill(
			0,
			12,
			array(
				'label' => 'A',
				'url'   => 'https://a.test',
			)
		);
		$this->assertCount( 8, UserMeta::cleanSocials( $many ) );

		$avatar = get_avatar_url( $user, array( 'size' => 96 ) );
		$this->assertStringContainsString( 'wp-content/uploads', (string) $avatar, 'the profile avatar replaces gravatar' );

		wp_set_current_user( 0 );
		global $wp_rest_server;
		$wp_rest_server = null;
		$request        = new \WP_REST_Request( 'GET', '/wp/v2/users/' . $user );
		$response       = rest_do_request( $request );
		$body           = wp_json_encode( $response->get_data() );
		$this->assertStringNotContainsString( 'private@example.test', (string) $body );
		$this->assertStringNotContainsString( 'user_email', (string) $body );
	}

	public function test_user_fields_need_nonce_and_capability(): void {
		$target = self::factory()->user->create( array( 'role' => 'author' ) );
		$meta   = new UserMeta();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST = array(
			'joo_user_meta_nonce' => wp_create_nonce( UserMeta::NONCE ),
			'joo_title'           => 'Hijacked',
		);
		$meta->save( $target );
		$_POST = array();
		$this->assertSame( '', (string) get_user_meta( $target, 'joo_title', true ) );
		ob_start();
		$meta->render( get_userdata( $target ) );
		$this->assertStringContainsString( 'joo_socials[7][url]', (string) ob_get_clean() );
	}

	public function test_featured_meta_bulk_actions_and_filters(): void {
		$featured = new Featured();
		$post     = self::factory()->post->create();
		update_post_meta( $post, '_joo_featured_order', 5000 );
		$this->assertSame( 999, (int) get_post_meta( $post, '_joo_featured_order', true ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertArrayHasKey( 'joo_feature', $featured->bulkActions( array() ) );
		$featured->handleBulk( 'x', 'joo_feature', array( $post ) );
		$this->assertTrue( (bool) get_post_meta( $post, '_joo_featured', true ) );
		$featured->handleBulk( 'x', 'joo_unfeature', array( $post ) );
		$this->assertFalse( (bool) get_post_meta( $post, '_joo_featured', true ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$this->assertArrayNotHasKey( 'joo_feature', $featured->bulkActions( array() ) );
		$featured->handleBulk( 'x', 'joo_feature', array( $post ) );
		$this->assertFalse( (bool) get_post_meta( $post, '_joo_featured', true ), 'contributors cannot feature posts' );

		update_post_meta( $post, '_joo_featured', true );
		ob_start();
		$featured->column( 'joo_featured', $post );
		$this->assertStringContainsString( '★', (string) ob_get_clean() );
		$this->assertArrayHasKey( 'joo_featured', $featured->columns( array() ) );
		$this->assertArrayHasKey( 'joo_featured', $featured->sortable( array() ) );
	}

	public function test_featured_meta_is_exposed_in_rest_and_checked_for_permission(): void {
		$post = self::factory()->post->create();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$request = new \WP_REST_Request( 'POST', '/wp/v2/posts/' . $post );
		$request->set_body_params(
			array(
				'meta' => array(
					'_joo_featured'       => true,
					'_joo_featured_order' => 2,
				),
			)
		);
		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertTrue( (bool) get_post_meta( $post, '_joo_featured', true ) );
		$this->assertSame( 2, (int) get_post_meta( $post, '_joo_featured_order', true ) );
		$bad = new \WP_REST_Request( 'POST', '/wp/v2/posts/' . $post );
		$bad->set_body_params( array( 'meta' => array( '_joo_featured_order' => 5000 ) ) );
		$this->assertSame( 400, rest_do_request( $bad )->get_status(), 'above the maximum' );
	}

	public function test_uninstall_keeps_data_unless_the_constant_is_set(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'joo_company' ) );
		define( 'WP_UNINSTALL_PLUGIN', 'jooservices-content/jooservices-content.php' );
		include dirname( __DIR__, 2 ) . '/uninstall.php';
		$this->assertNotNull( get_post( $id ), 'data survives uninstall by default' );
	}
}
