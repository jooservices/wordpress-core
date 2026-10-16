<?php
/**
 * Company data model (real WordPress): fields, validation, privacy, repository.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Tests\Integration;

use JOOservices\Resume\Model\CompanyRepository;
use JOOservices\Resume\Model\Repeaters;
use JOOservices\Resume\Modules\CompanyDataModel;
use JOOservices\Resume\Plugin;
use JOOservices\TestKit\IntegrationTestCase;

final class CompanyDataModelTest extends IntegrationTestCase {

	public function set_up(): void {
		parent::set_up();
		Plugin::register();
		global $wp_rest_server;
		$wp_rest_server = null;
		do_action( 'rest_api_init' );
		delete_option( CompanyRepository::DEFINITIONS_OPTION );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		delete_option( CompanyRepository::DEFINITIONS_OPTION );
		parent::tear_down();
	}

	/**
	 * @param array<string, mixed> $meta  Meta.
	 * @param array<string, mixed> $extra Extra body fields.
	 */
	private function create( array $meta, array $extra = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/wp/v2/joo-companies' );
		$request->set_body_params(
			array_merge(
				array(
					'title'  => 'ACME',
					'status' => 'publish',
					'meta'   => array_merge(
						array(
							'_joo_company_role' => 'CTO',
							'_joo_company_from' => '2022-03',
							'_joo_company_to'   => '2025-06',
						),
						$meta
					),
				),
				$extra
			)
		);
		return rest_do_request( $request );
	}

	private function editor(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function read( int $id ): array {
		$data = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/joo-companies/' . $id ) )->get_data();
		return is_array( $data['meta'] ?? null ) ? $data['meta'] : array();
	}

	public function test_every_new_field_is_stored_and_read_back_as_a_typed_record(): void {
		$this->editor();
		$repo = new CompanyRepository();
		$this->assertSame(
			array(),
			$repo->saveDefinitions(
				array(
					array(
						'key'    => 'team_size',
						'label'  => 'Team size',
						'type'   => 'number',
						'public' => true,
					),
				)
			)
		);
		$response = $this->create(
			array(
				'_joo_company_city'         => 'Hanoi',
				'_joo_company_country'      => 'Vietnam',
				'_joo_company_employment'   => 'founder',
				'_joo_company_industry'     => 'Software',
				'_joo_company_remote'       => true,
				'_joo_company_summary'      => 'Built the platform.',
				'_joo_company_description'  => '<p>Hello <strong>world</strong><script>alert(1)</script> <a href="javascript:x">bad</a></p>',
				'_joo_company_roles'        => wp_json_encode(
					array(
						array(
							'title'   => 'CTO',
							'from'    => '2023-01',
							'to'      => '2025-06',
							'summary' => 'Promoted',
						),
						array(
							'title' => 'Engineer',
							'from'  => '2022-03',
							'to'    => '2022-12',
						),
					)
				),
				'_joo_company_achievements' => wp_json_encode(
					array(
						array(
							'text'   => 'Cut cost',
							'metric' => '40',
							'unit'   => '%',
							'url'    => 'https://acme.test/case',
						),
					)
				),
				'_joo_company_technologies' => wp_json_encode( array( 'PHP', 'php', 'Go' ) ),
				'_joo_company_custom'       => wp_json_encode(
					array(
						'team_size' => '12',
						'unknown'   => 'dropped',
					)
				),
				'_joo_company_compensation' => 'private amount',
			)
		);
		$this->assertSame( 201, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$record = $repo->find( (int) $response->get_data()['id'] );
		$this->assertNotNull( $record );
		$this->assertSame( array( 'Hanoi', 'Vietnam', 'founder', 'Software', true, 'Built the platform.' ), array( $record->city, $record->country, $record->employment, $record->industry, $record->remote, $record->summary ) );
		$this->assertStringContainsString( '<strong>world</strong>', $record->description );
		$this->assertStringNotContainsString( 'script', $record->description );
		$this->assertStringNotContainsString( 'javascript', $record->description );
		$this->assertSame( array( 'CTO', 'Engineer' ), array_column( $record->roles, 'title' ) );
		$this->assertSame( '40', $record->achievements[0]['metric'] );
		$this->assertSame( array( 'PHP', 'Go' ), $record->technologies );
		$this->assertSame( array( 'team_size' => 12 ), $record->custom );
		$this->assertFalse( $record->isCurrent() );
		$this->assertSame( '3 yrs 4 mos', $record->durationLabel( '2030-01' ) );
		$this->assertSame( '1 yr', ( new \JOOservices\Resume\Model\CompanyRecord( 1, 'x', '', '2024-01', '2024-12', false, '', 0, '', '', '', '', '', '', false, array(), array(), array(), array(), '', '', array(), 0 ) )->durationLabel( '2030-01' ) );
		$this->assertSame( '1 mo', ( new \JOOservices\Resume\Model\CompanyRecord( 1, 'x', '', '2024-01', '2024-01', false, '', 0, '', '', '', '', '', '', false, array(), array(), array(), array(), '', '', array(), 0 ) )->durationLabel( '2030-01' ) );
	}

	public function test_bad_data_is_rejected_with_clear_messages(): void {
		$this->editor();
		$cases = array(
			array(
				'_joo_company_roles' => wp_json_encode(
					array(
						array(
							'title' => 'Early',
							'from'  => '2020-01',
							'to'    => '2021-01',
						),
					)
				),
			),
			array(
				'_joo_company_roles' => wp_json_encode(
					array(
						array(
							'title' => 'A',
							'from'  => '2022-03',
							'to'    => '2023-03',
						),
						array(
							'title' => 'B',
							'from'  => '2023-03',
							'to'    => '2024-01',
						),
					)
				),
			),
			array(
				'_joo_company_achievements' => wp_json_encode(
					array(
						array(
							'text' => 'X',
							'url'  => 'http://insecure.test',
						),
					)
				),
			),
			array( '_joo_company_custom' => wp_json_encode( array( 'team_size' => 'many' ) ) ),
			array( '_joo_company_employment' => 'slave' ),
		);
		( new CompanyRepository() )->saveDefinitions(
			array(
				array(
					'key'   => 'team_size',
					'label' => 'Team size',
					'type'  => 'number',
				),
			)
		);
		$expected = array( 'must lie inside', 'overlaps', 'https URL', 'holds a number', 'full time, contract' );
		foreach ( $cases as $i => $meta ) {
			$response = $this->create( $meta );
			$this->assertSame( 400, $response->get_status(), (string) $i );
			$this->assertStringContainsString( $expected[ $i ], (string) $response->as_error()->get_error_message(), (string) $i );
		}
	}

	public function test_private_fields_never_reach_visitors_unless_switched_to_public(): void {
		$this->editor();
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
			)
		);
		$id = (int) $this->create(
			array(
				'_joo_company_compensation' => '100k',
				'_joo_company_equity'       => '1%',
				'_joo_company_custom'       => wp_json_encode(
					array(
						'team_size' => 5,
						'manager'   => 'Secret Name',
					)
				),
			)
		)->get_data()['id'];

		$owner = $this->read( $id );
		$this->assertSame( '100k', $owner['_joo_company_compensation'] );
		$this->assertStringContainsString( 'Secret Name', (string) $owner['_joo_company_custom'] );

		wp_set_current_user( 0 );
		$visitor = $this->read( $id );
		$this->assertArrayNotHasKey( '_joo_company_compensation', $visitor );
		$this->assertArrayNotHasKey( '_joo_company_equity', $visitor );
		$this->assertStringNotContainsString( 'Secret Name', (string) $visitor['_joo_company_custom'] );
		$this->assertStringContainsString( '"team_size":5', (string) $visitor['_joo_company_custom'] );
		$this->assertSame( 'CTO', $visitor['_joo_company_role'], 'public fields stay visible' );

		$this->editor();
		update_post_meta( $id, '_joo_company_public_fields', wp_json_encode( array( 'compensation', 'bogus' ) ) );
		$this->assertSame( '["compensation"]', get_post_meta( $id, '_joo_company_public_fields', true ) );
		wp_set_current_user( 0 );
		$visitor = $this->read( $id );
		$this->assertSame( '100k', $visitor['_joo_company_compensation'] );
		$this->assertArrayNotHasKey( '_joo_company_equity', $visitor );

		$record = ( new CompanyRepository() )->find( $id );
		$public = $record->toPublic( ( new CompanyRepository() )->definitions(), '2030-01' );
		$this->assertSame( '100k', $public['compensation'] );
		$this->assertArrayNotHasKey( 'equity', $public );
		$this->assertSame( array( 'team_size' => 5 ), $public['custom'] );
		$this->assertSame( 'CTO', $public['role'] );
		$this->assertArrayNotHasKey( 'publicFields', $public );
	}

	public function test_repository_orders_pinned_first_then_manual_or_date_and_keeps_old_companies_working(): void {
		$make = static fn( string $title, string $from, int $order, bool $pinned = false, string $status = 'publish' ): int => self::factory()->post->create(
			array(
				'post_type'   => 'joo_company',
				'post_title'  => $title,
				'post_status' => $status,
				'menu_order'  => $order,
				'meta_input'  => array(
					'_joo_company_from'   => $from,
					'_joo_company_role'   => 'Dev',
					'_joo_company_pinned' => $pinned,
				),
			)
		);
		$old  = $make( 'Old', '2015-01', 1 );
		$mid  = $make( 'Mid', '2019-01', 3 );
		$new  = $make( 'New', '2023-01', 2, true );
		$make( 'Draft', '2024-01', 0, false, 'draft' );
		$repo = new CompanyRepository();
		$this->assertSame( array( $new, $old, $mid ), array_map( static fn( $r ) => $r->id, $repo->all( 'manual' ) ) );
		$this->assertSame( array( $new, $mid, $old ), array_map( static fn( $r ) => $r->id, $repo->all( 'date' ) ) );
		$this->assertCount( 4, $repo->all( 'date', false ) );
		$this->assertSame( $new, $repo->pinned()?->id );
		$this->assertNull( $repo->find( self::factory()->post->create() ), 'a normal post is not a company' );
		$legacy = $repo->find( $old );
		$this->assertSame( array( '', '', '', array(), array(), array(), array() ), array( $legacy->city, $legacy->employment, $legacy->description, $legacy->roles, $legacy->achievements, $legacy->technologies, $legacy->custom ) );
		$this->assertTrue( $legacy->isCurrent() );
		$this->assertSame( 'Old', $legacy->toPublic( array(), '2030-01' )['name'] );
	}

	public function test_definitions_are_validated_before_they_are_saved(): void {
		$repo   = new CompanyRepository();
		$errors = $repo->saveDefinitions(
			array(
				array(
					'key'   => 'Bad Key',
					'label' => 'x',
				),
			)
		);
		$this->assertSame( 'definition_key', $errors[0]['code'] );
		$this->assertSame( array(), $repo->definitions() );
		$this->assertSame(
			array(),
			$repo->saveDefinitions(
				array(
					array(
						'key'   => 'ok_key',
						'label' => 'OK',
						'type'  => 'list',
					),
				)
			)
		);
		$saved = $repo->definitions();
		$this->assertSame( array( 'ok_key' ), array_column( $saved, 'key' ) );
	}

	public function test_messages_and_encoding_helpers(): void {
		foreach ( array( 'role_title', 'role_month', 'role_order', 'role_outside', 'role_overlap', 'achievement_text', 'achievement_metric', 'achievement_url', 'employment', 'definition_key', 'definition_label', 'definition_duplicate', 'custom_number', 'custom_date', 'custom_url', 'unknown' ) as $code ) {
			$this->assertNotSame(
				'',
				CompanyDataModel::message(
					array(
						'code'  => $code,
						'field' => 'roles.0',
					)
				),
				$code
			);
		}
		$this->assertStringContainsString(
			'Role 3',
			CompanyDataModel::message(
				array(
					'code'  => 'role_title',
					'field' => 'roles.2',
				)
			)
		);
		$this->assertSame(
			array(
				'items'  => array(),
				'errors' => array(),
			),
			Repeaters::roles( array(), '', '' )
		);
		$this->assertContains( '_joo_company_equity', CompanyDataModel::privateKeys() );
	}
}
