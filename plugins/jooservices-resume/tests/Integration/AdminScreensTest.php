<?php
/**
 * Resume admin screens (real WordPress): editor, settings, list, import and export.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Tests\Integration;

use JOOservices\Resume\Admin\CompanyEditor;
use JOOservices\Resume\Admin\CompanyList;
use JOOservices\Resume\Admin\ImportExport;
use JOOservices\Resume\Admin\Settings;
use JOOservices\Resume\Model\CompanyRepository;
use JOOservices\Resume\Model\Keys;
use JOOservices\Resume\Plugin;
use JOOservices\Resume\Transfer\Mapper;
use JOOservices\TestKit\IntegrationTestCase;

final class AdminScreensTest extends IntegrationTestCase {

	public function set_up(): void {
		parent::set_up();
		Plugin::register();
		delete_option( CompanyRepository::DEFINITIONS_OPTION );
		delete_option( Settings::OPTION );
	}

	public function tear_down(): void {
		$_POST = array();
		$_GET  = array();
		delete_option( CompanyRepository::DEFINITIONS_OPTION );
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	private function as_role( string $role ): int {
		$id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $id );
		return $id;
	}

	private function company( string $from = '2022-03', string $to = '2025-06' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'  => 'joo_company',
				'post_title' => 'Acme',
				'meta_input' => array(
					'_joo_company_role' => 'Dev',
					'_joo_company_from' => $from,
					'_joo_company_to'   => $to,
				),
			)
		);
		return (int) $id;
	}

	/**
	 * Runs a callback that redirects and returns the location.
	 */
	private function redirected( callable $run ): string {
		// A real redirect ends the request: the filter throws instead (an empty location is passed through).
		$thrower = static function ( $location ) {
			if ( '' !== (string) $location ) {
				throw new \RuntimeException( 'redirect:' . $location );
			}
			return $location;
		};
		add_filter( 'wp_redirect', $thrower );
		try {
			$run();
		} catch ( \RuntimeException $e ) {
			return (string) preg_replace( '/^redirect:/', '', $e->getMessage() );
		} finally {
			remove_filter( 'wp_redirect', $thrower );
		}
		return '';
	}

	/**
	 * @param callable $render Renders a box.
	 */
	private function html( callable $render ): string {
		ob_start();
		$render();
		return (string) ob_get_clean();
	}

	public function test_the_editor_boxes_render_labelled_fields_and_repeaters(): void {
		$this->as_role( 'editor' );
		$id = $this->company();
		update_post_meta( $id, Keys::META['roles'], '{"v":1,"items":[{"title":"CTO","from":"2023-01","to":"","summary":""}]}' );
		( new CompanyRepository() )->saveDefinitions(
			array(
				array(
					'key'    => 'team_size',
					'label'  => 'Team size',
					'type'   => 'number',
					'public' => false,
				),
				array(
					'key'    => 'stack',
					'label'  => 'Stack',
					'type'   => 'list',
					'public' => true,
				),
				array(
					'key'    => 'site',
					'label'  => 'Site',
					'type'   => 'url',
					'public' => true,
				),
				array(
					'key'    => 'left_on',
					'label'  => 'Left on',
					'type'   => 'date',
					'public' => true,
				),
			)
		);
		$editor = new CompanyEditor();
		$post   = get_post( $id );
		$html   = $this->html( fn() => $editor->renderProfile( $post ) ) . $this->html( fn() => $editor->renderRoles( $post ) ) . $this->html( fn() => $editor->renderAchievements( $post ) ) . $this->html( fn() => $editor->renderCustom( $post ) ) . $this->html( fn() => $editor->renderPrivate( $post ) );
		foreach ( array( 'for="joo_city"', 'for="joo_employment"', 'data-joo-repeater="joo_roles"', 'name="joo_roles[0][title]"', 'value="CTO"', '<template>', 'joo_roles[__INDEX__][title]', 'data-joo-up', 'data-joo-down', 'data-joo-remove', 'data-joo-live', 'aria-live="polite"', 'data-joo-repeater="joo_achievements"', 'for="joo_technologies"', 'Team size (private)', 'step="any"', 'type="date"', 'type="url"', 'name="joo_public_compensation"', 'name="joo_order"' ) as $needle ) {
			$this->assertStringContainsString( $needle, $html, $needle );
		}
		delete_option( CompanyRepository::DEFINITIONS_OPTION );
		$this->assertStringContainsString( 'Define them in the settings', $this->html( fn() => $editor->renderCustom( $post ) ) );
	}

	public function test_the_editor_saves_every_group_and_reports_the_invalid_ones(): void {
		$user = $this->as_role( 'editor' );
		( new CompanyRepository() )->saveDefinitions(
			array(
				array(
					'key'    => 'team_size',
					'label'  => 'Team size',
					'type'   => 'number',
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
		$id    = $this->company();
		$_POST = array(
			CompanyEditor::NONCE . '_nonce' => wp_create_nonce( CompanyEditor::NONCE ),
			'joo_city'                      => 'Hanoi <b>x</b>',
			'joo_country'                   => 'Vietnam',
			'joo_industry'                  => 'Software',
			'joo_employment'                => 'contract',
			'joo_remote'                    => '1',
			'joo_summary'                   => 'Short',
			'joo_description'               => '<p>Hi</p><script>x</script>',
			'joo_order'                     => '7',
			'joo_roles'                     => array(
				1 => array(
					'title'   => 'Engineer',
					'from'    => '2022-03',
					'to'      => '2022-12',
					'summary' => '',
				),
				0 => array(
					'title'   => 'Lead',
					'from'    => '2023-01',
					'to'      => '2025-06',
					'summary' => 'x',
				),
				2 => array(
					'title'   => '',
					'from'    => '',
					'to'      => '',
					'summary' => '',
				),
			),
			'joo_achievements'              => array(
				array(
					'text'   => 'Cut cost',
					'metric' => '40',
					'unit'   => '%',
					'url'    => '',
				),
			),
			'joo_technologies'              => 'PHP, php, Go',
			'joo_custom_team_size'          => '12',
			'joo_custom_stack'              => 'A, B',
			'joo_compensation'              => '100k',
			'joo_public_equity'             => '1',
		);
		( new CompanyEditor() )->save( $id );
		$record = ( new CompanyRepository() )->find( $id );
		$this->assertSame( array( 'Hanoi x', 'Vietnam', 'Software', 'contract', true, 'Short' ), array( $record->city, $record->country, $record->industry, $record->employment, $record->remote, $record->summary ) );
		$this->assertStringNotContainsString( 'script', $record->description );
		$this->assertSame( 7, $record->menuOrder );
		$this->assertSame( array( 'Lead', 'Engineer' ), array_column( $record->roles, 'title' ), 'blank row ignored, newest first' );
		$this->assertSame( array( 'PHP', 'Go' ), $record->technologies );
		$this->assertSame(
			array(
				'team_size' => 12,
				'stack'     => array( 'A', 'B' ),
			),
			$record->custom
		);
		$this->assertSame( '100k', $record->compensation );
		$this->assertSame( array( 'equity' ), $record->publicFields );
		$this->assertFalse( get_transient( 'joo_company_errors_' . $user ) );

		$_POST['joo_roles']            = array(
			array(
				'title'   => 'Early',
				'from'    => '2019-01',
				'to'      => '2020-01',
				'summary' => '',
			),
		);
		$_POST['joo_achievements']     = array(
			array(
				'text'   => 'X',
				'metric' => 'lots',
				'unit'   => '',
				'url'    => '',
			),
		);
		$_POST['joo_custom_team_size'] = 'many';
		$_POST['joo_employment']       = 'slave';
		( new CompanyEditor() )->save( $id );
		$record = ( new CompanyRepository() )->find( $id );
		$this->assertSame( array( 'Lead', 'Engineer' ), array_column( $record->roles, 'title' ), 'invalid roles are not saved' );
		$this->assertSame( 'Cut cost', $record->achievements[0]['text'] );
		$this->assertSame( 12, $record->custom['team_size'] );
		$this->assertSame( '', $record->employment );
		$errors = (array) get_transient( 'joo_company_errors_' . $user );
		$this->assertCount( 4, $errors );
		$this->assertContains( 'Role 1 must lie inside the period at the company.', $errors );

		$_POST = array( 'joo_city' => 'Hacked' );
		( new CompanyEditor() )->save( $id );
		$this->assertSame( 'Hanoi x', ( new CompanyRepository() )->find( $id )->city, 'no nonce, no save' );
		$this->as_role( 'subscriber' );
		$_POST = array(
			CompanyEditor::NONCE . '_nonce' => wp_create_nonce( CompanyEditor::NONCE ),
			'joo_city'                      => 'Hacked',
		);
		( new CompanyEditor() )->save( $id );
		$this->assertSame( 'Hanoi x', ( new CompanyRepository() )->find( $id )->city, 'no capability, no save' );
	}

	public function test_settings_need_admin_rights_and_save_defaults_and_definitions(): void {
		$this->assertSame(
			array(
				'date_format'         => 'month_year',
				'show_duration'       => true,
				'show_private_labels' => false,
			),
			Settings::get()
		);
		$this->as_role( 'editor' );
		$this->expectException( \WPDieException::class );
		( new Settings() )->render();
	}

	public function test_settings_save_validates_and_redirects(): void {
		$admin    = $this->as_role( 'administrator' );
		$settings = new Settings();
		$_POST    = array(
			Settings::NONCE . '_nonce' => wp_create_nonce( Settings::NONCE ),
			'date_format'              => 'iso',
			'show_private_labels'      => '1',
			'joo_def'                  => array(
				array(
					'key'    => 'team_size',
					'label'  => 'Team size',
					'type'   => 'number',
					'public' => '1',
				),
				array(
					'key'   => '',
					'label' => '',
				),
				array(
					'key'   => 'site',
					'label' => 'Site',
					'type'  => 'url',
				),
			),
		);
		$location = $this->redirected( array( $settings, 'save' ) );
		$this->assertStringContainsString( 'saved=1', $location );
		$this->assertSame(
			array(
				'date_format'         => 'iso',
				'show_duration'       => false,
				'show_private_labels' => true,
			),
			Settings::get()
		);
		$defs = ( new CompanyRepository() )->definitions();
		$this->assertSame( array( 'team_size', 'site' ), array_column( $defs, 'key' ) );
		$this->assertSame( array( true, false ), array_column( $defs, 'public' ) );

		$_POST['joo_def'] = array(
			array(
				'key'   => 'Bad Key',
				'label' => 'x',
			),
		);
		$location         = $this->redirected( array( $settings, 'save' ) );
		$this->assertStringNotContainsString( 'saved=1', $location );
		$this->assertNotEmpty( get_transient( 'joo_resume_settings_errors_' . $admin ) );
		$this->assertSame( array( 'team_size', 'site' ), array_column( ( new CompanyRepository() )->definitions(), 'key' ), 'invalid definitions are not saved' );

		$_GET = array( 'saved' => '1' );
		$html = $this->html( array( $settings, 'render' ) );
		foreach ( array( 'Settings saved.', 'name="joo_def[0][label]"', 'data-joo-repeater="joo_def"', 'Preview', 'Private', 'The key uses lowercase' ) as $needle ) {
			if ( 'The key uses lowercase' === $needle ) {
				continue;
			}
			$this->assertStringContainsString( $needle, $html, $needle );
		}

		$this->as_role( 'editor' );
		$_POST = array(
			Settings::NONCE . '_nonce' => wp_create_nonce( Settings::NONCE ),
			'date_format'              => 'iso',
		);
		$this->expectException( \WPDieException::class );
		$settings->save();
	}

	public function test_the_list_shows_logo_current_visibility_and_filters_and_bulk_actions(): void {
		$this->as_role( 'administrator' );
		$list = new CompanyList();
		$list->hook();
		$current = $this->company( '2023-01', '' );
		$past    = $this->company( '2019-01', '2020-01' );
		update_post_meta( $current, '_joo_company_pinned', true );
		update_post_meta( $current, Keys::META['public_fields'], '["compensation"]' );
		$this->assertSame( array( 'cb', 'logo', 'title', 'role', 'period', 'current', 'pinned', 'visibility', 'menu_order' ), array_keys( $list->columns( array( 'cb' => 'x' ) ) ) );
		$out = $this->html( fn() => $list->column( 'current', $current ) ) . '|' . $this->html( fn() => $list->column( 'current', $past ) ) . '|' . $this->html( fn() => $list->column( 'visibility', $current ) ) . '|' . $this->html( fn() => $list->column( 'logo', $current ) );
		$this->assertSame( 'Yes|No|Visible, 1 private field(s) public|&mdash;', $out );
		$draft = self::factory()->post->create(
			array(
				'post_type'   => 'joo_company',
				'post_status' => 'draft',
			)
		);
		$this->assertSame( 'Hidden', $this->html( fn() => $list->column( 'visibility', (int) $draft ) ) );

		set_current_screen( 'edit-joo_company' );
		foreach ( array(
			'current' => array( $current ),
			'past'    => array( $past ),
			'pinned'  => array( $current ),
		) as $filter => $expected ) {
			$_GET                    = array( 'joo_filter' => $filter );
			$query                   = new \WP_Query();
			$GLOBALS['wp_the_query'] = $query;
			$GLOBALS['wp_query']     = $query;
			$query->query(
				array(
					'post_type'   => 'joo_company',
					'fields'      => 'ids',
					'post_status' => 'any',
				)
			);
			$this->assertSame( $expected, array_map( 'intval', $query->posts ), $filter );
		}
		$this->assertStringContainsString( 'Pinned only', $this->html( array( $list, 'filter' ) ) );

		$this->assertSame( array( 'joo_pin', 'joo_hide', 'joo_show' ), array_keys( $list->bulkActions( array() ) ) );
		$this->assertSame( 'untouched', $list->handleBulk( 'untouched', 'other', array( $past ) ) );
		$this->assertStringContainsString( 'joo_bulk=pin_one', $list->handleBulk( 'edit.php', 'joo_pin', array( $past, $current ) ) );
		$this->assertStringContainsString( 'joo_done=1', $list->handleBulk( 'edit.php', 'joo_pin', array( $past ) ) );
		$this->assertSame( '', get_post_meta( $past, '_joo_company_to', true ) );
		$this->assertFalse( (bool) get_post_meta( $current, '_joo_company_pinned', true ), 'only one company stays pinned' );
		$list->handleBulk( 'edit.php', 'joo_hide', array( $past, $draft ) );
		$this->assertSame( 'draft', get_post_status( $past ) );
		$list->handleBulk( 'edit.php', 'joo_show', array( $past ) );
		$this->assertSame( 'publish', get_post_status( $past ) );
		$this->as_role( 'subscriber' );
		$this->assertStringContainsString( 'joo_done=0', $list->handleBulk( 'edit.php', 'joo_hide', array( $past ) ) );

		$_GET = array( 'joo_bulk' => 'pin_one' );
		$this->assertStringContainsString( 'exactly one', $this->html( array( $list, 'bulkNotice' ) ) );
		$_GET = array(
			'joo_bulk' => 'joo_hide',
			'joo_done' => '2',
		);
		$this->assertStringContainsString( '2 companies updated', $this->html( array( $list, 'bulkNotice' ) ) );
		$_GET = array();
		$this->assertSame( '', $this->html( array( $list, 'bulkNotice' ) ) );
	}

	public function test_exports_cover_the_three_formats_and_need_the_nonce_and_capability(): void {
		$this->as_role( 'administrator' );
		$id = $this->company( '2022-03', '' );
		update_post_meta( $id, Keys::META['compensation'], 'secret-pay' );
		$io  = new ImportExport();
		$own = $io->document( 'own' );
		$this->assertStringContainsString( 'secret-pay', $own['body'], 'the own export is complete' );
		$this->assertSame( 'application/json', $own['type'] );
		$this->assertStringNotContainsString( 'secret-pay', $io->document( 'jsonresume' )['body'] );
		$this->assertStringNotContainsString( 'secret-pay', $io->document( 'csv' )['body'] );
		$this->assertSame( 'text/csv', $io->document( 'csv' )['type'] );
		$this->assertNull( $io->document( 'xml' ) );

		$this->as_role( 'editor' );
		$_GET = array(
			'_wpnonce' => wp_create_nonce( 'joo_resume_export' ),
			'format'   => 'own',
		);
		$this->expectException( \WPDieException::class );
		$io->export();
	}

	public function test_an_import_is_checked_first_and_creates_nothing_unless_every_company_is_valid(): void {
		$admin = $this->as_role( 'administrator' );
		$io    = new ImportExport();
		$good  = Mapper::parse(
			(string) json_encode(
				array(
					'work' => array(
						array(
							'name'       => 'Acme',
							'position'   => 'Dev',
							'startDate'  => '2020-01',
							'endDate'    => '2021-01',
							'highlights' => array( 'Did X' ),
						),
						array(
							'name'      => 'Beta',
							'position'  => 'Lead',
							'startDate' => '2021-02',
						),
					),
				)
			),
			array()
		);
		$bad   = Mapper::parse(
			(string) json_encode(
				array(
					'work' => array(
						array(
							'name'      => 'Ok',
							'startDate' => '2020-01',
						),
						array(
							'name'      => '',
							'startDate' => 'nope',
						),
					),
				)
			),
			array()
		);

		$token = $io->stash( $bad );
		$html  = $this->html(
			function () use ( $token ) {
				$_GET = array( 'token' => $token );
				( new ImportExport() )->render();
			}
		);
		$this->assertStringContainsString( '1 company has errors', $html );
		$this->assertStringNotContainsString( 'joo_apply_nonce', $html );
		$_POST    = array(
			'joo_apply_nonce' => wp_create_nonce( 'joo_resume_import_apply' ),
			'token'           => $token,
		);
		$location = $this->redirected( array( $io, 'apply' ) );
		$this->assertStringNotContainsString( 'imported=', $location );
		$this->assertCount( 0, ( new CompanyRepository() )->all( 'date', false ), 'nothing created from a file with errors' );
		$this->assertNotEmpty( get_transient( 'joo_resume_import_error_' . $admin ) );

		$token = $io->stash( $good );
		$html  = $this->html(
			function () use ( $token ) {
				$_GET = array( 'token' => $token );
				( new ImportExport() )->render();
			}
		);
		$this->assertStringContainsString( 'Import 2 companies', $html );
		$_POST    = array(
			'joo_apply_nonce' => wp_create_nonce( 'joo_resume_import_apply' ),
			'token'           => $token,
		);
		$location = $this->redirected( array( $io, 'apply' ) );
		$this->assertStringContainsString( 'imported=2', $location );
		$records = ( new CompanyRepository() )->all( 'date' );
		$this->assertSame( array( 'Beta', 'Acme' ), array_map( static fn( $r ) => $r->name, $records ) );
		$this->assertSame( 'Did X', $records[1]->achievements[0]['text'] );
		$this->assertTrue( $records[0]->isCurrent() );

		$_POST = array(
			'joo_apply_nonce' => wp_create_nonce( 'joo_resume_import_apply' ),
			'token'           => $token,
		);
		$this->redirected( array( $io, 'apply' ) );
		$this->assertCount( 2, ( new CompanyRepository() )->all( 'date' ), 'a token works once' );

		$this->as_role( 'editor' );
		$this->expectException( \WPDieException::class );
		$io->apply();
	}

	public function test_uploads_are_limited_by_size_type_and_origin_and_the_page_renders(): void {
		$this->as_role( 'administrator' );
		$io = new ImportExport();
		$ok = static fn(): bool => true;
		$this->assertStringContainsString( 'Choose a JSON file', $io->uploadError( array() ) );
		$this->assertStringContainsString(
			'Choose a JSON file',
			$io->uploadError(
				array(
					'error'    => UPLOAD_ERR_OK,
					'tmp_name' => '/tmp/x',
					'name'     => 'a.json',
					'size'     => 5,
				)
			),
			'not an uploaded file'
		);
		$this->assertStringContainsString(
			'larger than 1 MB',
			$io->uploadError(
				array(
					'error'    => UPLOAD_ERR_OK,
					'tmp_name' => '/tmp/x',
					'name'     => 'a.json',
					'size'     => ImportExport::MAX_BYTES + 1,
				),
				$ok
			)
		);
		$this->assertStringContainsString(
			'Only .json',
			$io->uploadError(
				array(
					'error'    => UPLOAD_ERR_OK,
					'tmp_name' => '/tmp/x',
					'name'     => 'a.php',
					'size'     => 5,
				),
				$ok
			)
		);
		$this->assertSame(
			'',
			$io->uploadError(
				array(
					'error'    => UPLOAD_ERR_OK,
					'tmp_name' => '/tmp/x',
					'name'     => 'a.JSON',
					'size'     => 5,
				),
				$ok
			)
		);
		$this->assertSame(
			1,
			ImportExport::invalidRows(
				array(
					array(
						'company' => array(),
						'errors'  => array(
							array(
								'code'  => 'x',
								'field' => 'y',
							),
						),
					),
					array(
						'company' => array(),
						'errors'  => array(),
					),
				)
			)
		);

		set_transient( 'joo_resume_import_error_' . get_current_user_id(), 'Boom', 60 );
		$_GET = array(
			'imported' => '3',
			'token'    => 'expiredtoken',
		);
		$html = $this->html( array( $io, 'render' ) );
		foreach ( array( 'Boom', '3 companies imported', 'joo_import_file', 'action=joo_resume_export&#038;format=csv', 'The check expired' ) as $needle ) {
			$this->assertStringContainsString( $needle, $html, $needle );
		}
		$this->as_role( 'editor' );
		$this->expectException( \WPDieException::class );
		$io->render();
	}

	public function test_assets_load_only_on_the_company_screens(): void {
		$editor = new CompanyEditor();
		set_current_screen( 'post' );
		$editor->assets( 'post.php' );
		$this->assertFalse( wp_script_is( 'joo-resume-admin', 'enqueued' ) );
		set_current_screen( 'joo_company' );
		$editor->assets( 'edit.php' );
		$this->assertFalse( wp_script_is( 'joo-resume-admin', 'enqueued' ) );
		$editor->assets( 'post.php' );
		$this->assertTrue( wp_script_is( 'joo-resume-admin', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'joo-resume-admin', 'enqueued' ) );
	}

	public function test_the_writer_updates_an_existing_company_and_rejects_an_unknown_export_format(): void {
		$this->as_role( 'administrator' );
		$row    = Mapper::company(
			array(
				'name' => 'First',
				'from' => '2020-01',
				'role' => 'Dev',
			),
			array()
		)['company'];
		$writer = new \JOOservices\Resume\Model\CompanyWriter();
		$id     = $writer->save( $row, 0, 'draft' );
		$this->assertIsInt( $id );
		$this->assertSame( 'draft', get_post_status( $id ) );
		$row['name'] = 'Second';
		$row['role'] = 'Lead';
		$this->assertSame( $id, $writer->save( $row, $id ) );
		$record = ( new CompanyRepository() )->find( $id );
		$this->assertSame( array( 'Second', 'Lead' ), array( $record->name, $record->role ) );

		$_GET = array(
			'_wpnonce' => wp_create_nonce( 'joo_resume_export' ),
			'format'   => 'xml',
		);
		$this->expectException( \WPDieException::class );
		( new ImportExport() )->export();
	}
}
