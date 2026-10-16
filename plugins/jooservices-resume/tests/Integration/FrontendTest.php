<?php
/**
 * Resume front end (real WordPress): markup, shortcode, block, REST and structured data.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Tests\Integration;

use JOOservices\Resume\Admin\Settings;
use JOOservices\Resume\Frontend\Renderer;
use JOOservices\Resume\Frontend\Surfaces;
use JOOservices\Resume\Model\CompanyRepository;
use JOOservices\Resume\Model\Keys;
use JOOservices\Resume\Plugin;
use JOOservices\TestKit\Factories\AttachmentFactory;
use JOOservices\TestKit\IntegrationTestCase;

final class FrontendTest extends IntegrationTestCase {

	public function set_up(): void {
		parent::set_up();
		Plugin::register();
		global $wp_rest_server;
		$wp_rest_server = null;
		do_action( 'rest_api_init' );
		delete_option( CompanyRepository::DEFINITIONS_OPTION );
		delete_option( Settings::OPTION );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		delete_option( CompanyRepository::DEFINITIONS_OPTION );
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	/**
	 * @param array<string, mixed> $meta Meta (short keys are expanded to the registered meta keys).
	 */
	private function company( string $title, array $meta = array(), int $order = 0 ): int {
		$input = array();
		foreach ( $meta as $key => $value ) {
			$input[ Keys::META[ $key ] ?? '_joo_company_' . $key ] = $value;
		}
		return (int) self::factory()->post->create(
			array(
				'post_type'  => 'joo_company',
				'post_title' => $title,
				'menu_order' => $order,
				'meta_input' => $input,
			)
		);
	}

	public function test_the_timeline_is_semantic_escaped_and_public_only(): void {
		( new CompanyRepository() )->saveDefinitions(
			array(
				array(
					'key'    => 'team_size',
					'label'  => 'Team size',
					'type'   => 'number',
					'public' => true,
				),
				array(
					'key'    => 'manager',
					'label'  => 'Manager',
					'type'   => 'text',
					'public' => false,
				),
				array(
					'key'    => 'site',
					'label'  => 'Site',
					'type'   => 'url',
					'public' => true,
				),
				array(
					'key'    => 'stack',
					'label'  => 'Stack',
					'type'   => 'list',
					'public' => true,
				),
			)
		);
		$logo = ( new AttachmentFactory() )->image();
		$this->company(
			'Acme <script>alert(1)</script>',
			array(
				'role'         => 'CTO',
				'from'         => '2022-03',
				'to'           => '',
				'url'          => 'https://acme.test',
				'logo_id'      => $logo,
				'city'         => 'Hanoi',
				'country'      => 'Vietnam',
				'employment'   => 'founder',
				'remote'       => true,
				'summary'      => 'Built <b>it</b>',
				'description'  => '<p>Hello <strong>world</strong></p>',
				'roles'        => '{"v":1,"items":[{"title":"CTO","from":"2023-01","to":"","summary":"Promoted"},{"title":"Engineer","from":"2022-03","to":"2022-12","summary":""}]}',
				'achievements' => '{"v":1,"items":[{"text":"Cut cost","metric":"40","unit":"%","url":"https://acme.test/case"}]}',
				'technologies' => '["PHP","Go"]',
				'custom'       => '{"team_size":12,"manager":"Secret Name","site":"https://acme.test/team","stack":["A","B"]}',
				'compensation' => 'secret-pay',
			)
		);
		$html = ( new Renderer() )->render();
		foreach ( array( '<ol class="joo-resume__list">', 'is-current', '<time datetime="2022-03">March 2022</time>', 'joo-resume__current">Present', 'joo-resume__duration', 'CTO · Founder · Hanoi, Vietnam · Remote', 'href="https://acme.test"', 'alt=""', 'joo-resume__roles', 'Promoted', 'joo-resume__metric">40 %', 'aria-label="Technologies"', '<li>PHP</li>', '<dt>Team size</dt><dd>12</dd>', '<dd>A, B</dd>', 'href="https://acme.test/team"', '<strong>world</strong>' ) as $needle ) {
			$this->assertStringContainsString( $needle, $html, $needle );
		}
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'rel="noopener">Acme', $html, 'the title is never printed raw' );
		foreach ( array( 'Secret Name', 'secret-pay', 'Manager' ) as $private ) {
			$this->assertStringNotContainsString( $private, $html, $private );
		}
		$this->assertTrue( wp_style_is( Renderer::STYLE, 'enqueued' ) );
	}

	public function test_display_settings_filters_and_empty_states(): void {
		$renderer = new Renderer();
		$this->assertStringContainsString( 'No companies to show yet.', $renderer->render() );
		$this->company(
			'Old',
			array(
				'role' => 'Dev',
				'from' => '2015-01',
				'to'   => '2016-12',
			),
			1
		);
		$this->company(
			'Mid',
			array(
				'role' => 'Dev',
				'from' => '2019-01',
				'to'   => '2020-12',
			),
			3
		);
		$this->company(
			'New',
			array(
				'role' => 'Dev',
				'from' => '2023-01',
				'to'   => '',
			),
			2
		);
		$names = static function ( string $html ): array {
			preg_match_all( '#joo-resume__company">(?:<a[^>]*>)?([^<]+)#', $html, $m );
			return $m[1];
		};
		$this->assertSame( array( 'Old', 'New', 'Mid' ), $names( $renderer->render( array( 'order' => 'manual' ) ) ) );
		$this->assertSame( array( 'New', 'Mid', 'Old' ), $names( $renderer->render( array( 'order' => 'date' ) ) ) );
		$this->assertSame( array( 'Old' ), $names( $renderer->render( array( 'limit' => 1 ) ) ) );
		$this->assertSame( array( 'New' ), $names( $renderer->render( array( 'current_only' => true ) ) ) );
		$this->assertStringContainsString( 'joo-resume__duration', $renderer->render() );
		$this->assertStringContainsString( 'January 2015', $renderer->render() );
		update_option(
			Settings::OPTION,
			array(
				'date_format'   => 'iso',
				'show_duration' => false,
			)
		);
		$html = $renderer->render();
		$this->assertStringNotContainsString( 'joo-resume__duration', $html );
		$this->assertStringContainsString( '<time datetime="2015-01">2015-01</time>', $html );
	}

	public function test_the_shortcode_and_the_block_use_the_same_markup(): void {
		$this->company(
			'Acme',
			array(
				'role' => 'Dev',
				'from' => '2020-01',
			),
			1
		);
		$this->company(
			'Beta',
			array(
				'role' => 'Dev',
				'from' => '2021-01',
				'to'   => '2022-01',
			),
			2
		);
		$short = do_shortcode( '[joo_resume]' );
		$block = do_blocks( '<!-- wp:joo/resume /-->' );
		$this->assertSame( $short, $block );
		$this->assertStringContainsString( 'Beta', $short );
		$this->assertStringNotContainsString( 'Beta', do_shortcode( '[joo_resume current="yes"]' ) );
		$this->assertStringNotContainsString( 'Beta', do_blocks( '<!-- wp:joo/resume {"currentOnly":true} /-->' ) );
		$this->assertStringNotContainsString( 'Beta', do_shortcode( '[joo_resume limit="1"]' ) );
		$this->assertStringNotContainsString( 'Beta', do_blocks( '<!-- wp:joo/resume {"limit":1} /-->' ) );
		$this->assertTrue( shortcode_exists( 'joo_resume' ) );
		$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( 'joo/resume' ) );
		$this->assertStringContainsString( 'joo-resume', do_shortcode( '[joo_resume order="date"]' ) );
	}

	public function test_rest_exposes_public_data_with_validators(): void {
		$id = $this->company(
			'Acme',
			array(
				'role'         => 'CTO',
				'from'         => '2022-03',
				'compensation' => 'secret-pay',
				'summary'      => 'Hello',
				'achievements' => '{"v":1,"items":[{"text":"Won","metric":"","unit":"","url":""}]}',
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/jooservices-resume/v1/resume' ) );
		$this->assertSame( 200, $response->get_status() );
		$body = (string) wp_json_encode( $response->get_data() );
		$this->assertStringNotContainsString( 'secret-pay', $body, 'even an administrator gets the public projection' );
		$this->assertSame( 'Acme', $response->get_data()['companies'][0]['name'] );
		$this->assertSame( 'public, max-age=300', $response->get_headers()['Cache-Control'] );
		$etag = (string) $response->get_headers()['ETag'];
		$this->assertMatchesRegularExpression( '/^"[a-f0-9]{32}"$/', $etag );

		wp_set_current_user( 0 );
		$again = new \WP_REST_Request( 'GET', '/jooservices-resume/v1/resume' );
		$again->set_header( 'If-None-Match', $etag );
		$cached = rest_do_request( $again );
		$this->assertSame( 304, $cached->get_status() );
		$this->assertNull( $cached->get_data() );
		update_post_meta( $id, Keys::META['role'], 'CEO' );
		$changed = rest_do_request( $again );
		$this->assertSame( 200, $changed->get_status(), 'a changed resume has a new validator' );

		$work = rest_do_request( new \WP_REST_Request( 'GET', '/jooservices-resume/v1/resume/json-resume' ) );
		$this->assertSame( 200, $work->get_status() );
		$this->assertSame( 'CEO', $work->get_data()['work'][0]['position'] );
		$this->assertSame( array( 'Won' ), $work->get_data()['work'][0]['highlights'] );
		$this->assertStringNotContainsString( 'secret-pay', (string) wp_json_encode( $work->get_data() ) );
	}

	public function test_structured_data_adds_works_for_to_the_person_node(): void {
		$this->company(
			'Current Co',
			array(
				'role' => 'CTO',
				'from' => '2022-01',
				'url'  => 'https://current.test',
			),
			1
		);
		$this->company(
			'Past Co',
			array(
				'role' => 'Dev',
				'from' => '2015-01',
				'to'   => '2016-12',
			),
			2
		);
		$surfaces = new Surfaces();
		$this->assertSame( 'not a node', $surfaces->person( 'not a node' ) );
		$node = $surfaces->person( array( '@type' => 'Person' ) );
		$this->assertSame( array( 'Current Co' ), array_column( $node['worksFor'], 'name' ), 'elsewhere only the current company' );
		$this->assertSame( 'Organization', $node['worksFor'][0]['@type'] );
		$this->assertSame( 'https://current.test', $node['worksFor'][0]['url'] );
		$this->assertSame(
			array(
				array(
					'@type' => 'Occupation',
					'name'  => 'CTO',
				),
			),
			$node['hasOccupation']
		);

		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '[joo_resume]',
			)
		);
		$this->go_to( get_permalink( $page ) );
		$node = $surfaces->person( array( '@type' => 'Person' ) );
		$this->assertSame( array( 'Current Co', 'Past Co' ), array_column( $node['worksFor'], 'name' ), 'the resume page lists every company' );

		$this->assertTrue( (bool) has_filter( 'joo/seo/schema/person', array( $surfaces, 'person' ) ) || (bool) has_filter( 'joo/seo/schema/person' ) );
		$this->assertNotFalse( has_filter( 'wpseo_schema_person' ) );
		wp_delete_post( $page, true );
		foreach ( get_posts(
			array(
				'post_type'   => 'joo_company',
				'post_status' => 'any',
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		) as $id ) {
			wp_delete_post( (int) $id, true );
		}
		$this->assertArrayNotHasKey( 'worksFor', $surfaces->person( array( '@type' => 'Person' ) ) );
	}
}
