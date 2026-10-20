<?php
/**
 * Settings page, editor panels and Site Health (real WordPress).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Admin\EditorPanels;
use JOOservices\ThemeCore\Admin\SettingsPage;
use JOOservices\ThemeCore\Admin\Cli\SettingsCommand;
use JOOservices\ThemeCore\Admin\SiteHealth;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Kernel;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\FailingModule;

final class AdminTest extends IntegrationTestCase {

	private const MANIFEST = __DIR__ . '/../Fixtures/manifests/valid.json';

	public function set_up(): void {
		parent::set_up();
		Kernel::reset();
		Notices::reset();
		delete_option( 'joo_fixture_theme_settings' );
	}

	public function tear_down(): void {
		Kernel::reset();
		Notices::reset();
		unset( $_GET['tab'], $_POST['joo_tab'], $_POST['joo_settings'] );
		parent::tear_down();
	}

	private function boot(): void {
		Kernel::boot( self::MANIFEST );
		do_action( 'after_setup_theme' );
	}

	private function page(): SettingsPage {
		$this->boot();
		return Kernel::container()->get( SettingsPage::class );
	}

	private function asAdmin(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	private function renderTab( SettingsPage $page, string $tab ): string {
		$_GET['tab'] = $tab;
		ob_start();
		$page->render();
		return (string) ob_get_clean();
	}

	public function test_every_settings_key_appears_exactly_once_in_its_own_tab(): void {
		$page   = $this->page();
		$schema = Kernel::container()->get( Settings::class )->schema();
		$this->asAdmin();
		$seen = array();
		foreach ( $schema->groups() as $group ) {
			$html = $this->renderTab( $page, $group['id'] );
			foreach ( $schema->keysInGroup( $group['id'] ) as $key ) {
				$this->assertSame( 1, substr_count( $html, 'data-joo-key="' . $key . '"' ), "$key must be rendered once in tab {$group['id']}" );
				$seen[ $key ] = true;
			}
		}
		$this->assertCount( count( $schema->all() ), $seen, 'every key belongs to exactly one tab' );
	}

	public function test_tabs_include_compatibility_and_tools(): void {
		$page = $this->page();
		$this->assertArrayHasKey( 'compatibility', $page->tabs() );
		$this->assertArrayHasKey( 'tools', $page->tabs() );
		$this->asAdmin();
		$this->assertStringContainsString( 'joo_export_settings', $this->renderTab( $page, 'tools' ) );
		$this->assertStringContainsString( 'Detected plugins', $this->renderTab( $page, 'compatibility' ) );
	}

	public function test_compatibility_rows_and_tag_choices_are_rendered(): void {
		$page = $this->page();
		$this->asAdmin();
		$tag = self::factory()->tag->create( array( 'name' => 'Coverage tag' ) );
		add_filter(
			'joo/compat/report',
			static fn() => array(
				array(
					'label'    => 'Example SEO',
					'version'  => '1.2.3',
					'provides' => array( 'seo' ),
					'adapter'  => 'ExampleAdapter',
					'notice'   => 'Coverage note',
				),
			)
		);

		ob_start();
		$page->renderCompatibility();
		$compatibility = (string) ob_get_clean();
		$this->assertStringContainsString( 'Example SEO', $compatibility );
		$this->assertStringContainsString( 'ExampleAdapter', $compatibility );
		$this->assertStringContainsString( 'Coverage note', $compatibility );

		$html = $this->renderTab( $page, 'content' );
		$this->assertGreaterThan( 0, $tag );
		$this->assertStringContainsString( 'Coverage tag', $html );
	}

	public function test_settings_notices_and_tools_performance_states_are_rendered(): void {
		$page = $this->page();
		$this->asAdmin();
		$user_id = get_current_user_id();
		set_transient(
			'joo_settings_notice_' . $user_id,
			array(
				'errors' => array(),
				'saved'  => array( 'lazy.images' ),
			),
			60
		);
		$html = $this->renderTab( $page, 'unknown-tab' );
		$this->assertStringContainsString( 'Settings saved.', $html );
		$this->assertFalse( get_transient( 'joo_settings_notice_' . $user_id ) );

		set_transient(
			'joo_selftest_' . $user_id,
			array(
				'ok'      => true,
				'status'  => 200,
				'ttfb_ms' => 12,
				'bytes'   => 1200,
				'scripts' => 3,
				'styles'  => 2,
				'images'  => 1,
			),
			60
		);
		ob_start();
		$page->renderTools();
		$tools = (string) ob_get_clean();
		$this->assertStringContainsString( 'Home page: HTTP 200', $tools );
		$this->assertFalse( get_transient( 'joo_selftest_' . $user_id ) );

		set_transient( 'joo_selftest_' . $user_id, array( 'ok' => false ), 60 );
		ob_start();
		$page->renderTools();
		$this->assertStringContainsString( 'could not reach the home page', (string) ob_get_clean() );

		Kernel::reset();
		ob_start();
		$page->renderTools();
		$this->assertStringContainsString( 'performance module is not running', (string) ob_get_clean() );
	}

	public function test_settings_error_notice_highlights_the_invalid_field(): void {
		$page = $this->page();
		$this->asAdmin();
		set_transient(
			'joo_settings_notice_' . get_current_user_id(),
			array(
				'errors' => array( 'lazy.eager_count' => 'Enter a whole number.' ),
				'saved'  => array(),
			),
			60
		);
		$html = $this->renderTab( $page, 'lazy' );
		$this->assertStringContainsString( 'Some values were not saved.', $html );
		$this->assertStringContainsString( 'Enter a whole number.', $html );
		$this->assertStringContainsString( 'aria-describedby="joo-lazy-eager-count-desc joo-lazy-eager-count-error"', $html );
	}

	public function test_settings_page_assets_are_limited_to_its_screen(): void {
		$page = $this->page();
		$page->enqueue( 'dashboard' );
		$this->assertFalse( wp_script_is( 'joo-settings', 'enqueued' ) );

		$page->enqueue( 'appearance_page_' . SettingsPage::SLUG );
		$this->assertTrue( wp_script_is( 'joo-settings', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'joo-settings', 'enqueued' ) );
	}

	public function test_saving_stores_valid_values_and_reports_invalid_ones(): void {
		$page   = $this->page();
		$result = $page->process(
			'lazy',
			array(
				'lazy.eager_count'      => '3',
				'lazy.images'           => '0',
				'lazy.embed_thumbnails' => 'bogus',
			)
		);
		$this->assertSame( 3, $result['saved']['lazy.eager_count'] );
		$this->assertFalse( $result['saved']['lazy.images'] );
		$this->assertArrayHasKey( 'lazy.embed_thumbnails', $result['errors'] );
		$settings = Kernel::container()->get( Settings::class );
		$this->assertSame( 3, $settings->get( 'lazy.eager_count' ) );
		$this->assertSame( 'user', $settings->source( 'lazy.eager_count' ) );
	}

	public function test_only_keys_of_the_posted_tab_can_be_saved(): void {
		$page = $this->page();
		$page->process( 'lazy', array( 'seo.yield' => 'never' ) );
		$this->assertSame( 'core', Kernel::container()->get( Settings::class )->source( 'seo.yield' ) );
	}

	public function test_a_value_forced_by_code_is_locked_and_labelled(): void {
		add_filter( 'joo/settings/value', static fn( $value, $key ) => 'seo.yield' === $key ? 'always' : $value, 10, 2 );
		$page = $this->page();
		$this->asAdmin();
		$html = $this->renderTab( $page, 'seo' );
		$this->assertStringContainsString( 'Overridden by code', $html );
		$this->assertMatchesRegularExpression( '/<select id="joo-seo-yield"[^>]*disabled/', $html );
		$result = $page->process( 'seo', array( 'seo.yield' => 'never' ) );
		$this->assertArrayNotHasKey( 'seo.yield', $result['saved'], 'locked keys are not editable' );
	}

	public function test_unchecked_term_lists_are_saved_as_empty(): void {
		$page = $this->page();
		$tag  = self::factory()->tag->create();
		$page->process( 'content', array( 'content.topics_manual' => array( (string) $tag ) ) );
		$this->assertSame( array( $tag ), Kernel::container()->get( Settings::class )->get( 'content.topics_manual' ) );
		$page->process( 'content', array() );
		$this->assertSame( array(), Kernel::container()->get( Settings::class )->get( 'content.topics_manual' ) );
	}

	public function test_export_then_import_round_trips_settings(): void {
		$page = $this->page();
		$page->process(
			'lazy',
			array(
				'lazy.eager_count' => '2',
				'lazy.iframes'     => '0',
			)
		);
		$page->process(
			'general',
			array(
				'features.rail'        => '0',
				'features.back_to_top' => '0',
			)
		);
		$page->process( 'terminal', array( 'terminal.enabled' => '0' ) );
		$page->process( 'content', array( 'content.rail_profile' => '0' ) );
		$page->process(
			'appearance',
			array(
				'appearance.card_density' => 'compact',
				'appearance.date_format'  => 'Y-m-d',
			)
		);
		$export = wp_json_encode(
			array(
				'joo_settings_export' => 1,
				'settings'            => Kernel::container()->get( Settings::class )->repository()->all(),
			)
		);
		Kernel::container()->get( Settings::class )->repository()->reset();
		$this->assertSame( 1, Kernel::container()->get( Settings::class )->get( 'lazy.eager_count' ) );
		$this->assertSame( 'comfortable', Kernel::container()->get( Settings::class )->get( 'appearance.card_density' ) );
		$result = $page->import( (string) $export );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 2, Kernel::container()->get( Settings::class )->get( 'lazy.eager_count' ) );
		$this->assertFalse( Kernel::container()->get( Settings::class )->get( 'lazy.iframes' ) );
		$this->assertSame( 'compact', Kernel::container()->get( Settings::class )->get( 'appearance.card_density' ) );
		$this->assertSame( 'Y-m-d', Kernel::container()->get( Settings::class )->get( 'appearance.date_format' ) );
		$this->assertFalse( Kernel::container()->get( Settings::class )->get( 'features.rail' ) );
		$this->assertFalse( Kernel::container()->get( Settings::class )->get( 'terminal.enabled' ) );
		$this->assertFalse( Kernel::container()->get( Settings::class )->get( 'content.rail_profile' ) );
		Kernel::container()->get( Settings::class )->repository()->reset();
		$this->assertSame( 'comfortable', Kernel::container()->get( Settings::class )->get( 'appearance.card_density' ) );
		$this->assertTrue( Kernel::container()->get( Settings::class )->get( 'features.back_to_top' ) );
		$this->assertTrue( Kernel::container()->get( Settings::class )->get( 'terminal.enabled' ) );
	}

	public function test_import_rejects_foreign_documents(): void {
		$page = $this->page();
		$this->assertArrayHasKey( 'import', $page->import( '{"hello":"world"}' )['errors'] );
		$this->assertArrayHasKey( 'import', $page->import( 'not json' )['errors'] );
	}

	public function test_a_bad_nonce_is_rejected(): void {
		$page = $this->page();
		$this->asAdmin();
		$_POST['joo_tab']     = 'lazy';
		$_REQUEST['_wpnonce'] = 'invalid';
		$this->expectException( \WPDieException::class );
		$page->handleSave();
	}

	public function test_a_missing_nonce_is_rejected(): void {
		$page = $this->page();
		$this->asAdmin();
		$_POST['joo_tab'] = 'lazy';
		unset( $_REQUEST['_wpnonce'], $_POST['_wpnonce'], $_GET['_wpnonce'] );
		$this->expectException( \WPDieException::class );
		$page->handleSave();
	}

	public function test_users_without_the_capability_cannot_open_or_save(): void {
		$page = $this->page();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		try {
			$page->render();
			$this->fail( 'render() must be denied.' );
		} catch ( \WPDieException $e ) {
			$this->assertStringContainsString( 'permission', $e->getMessage() );
		}
		$this->expectException( \WPDieException::class );
		$page->handleSave();
	}

	public function test_subscribers_cannot_change_settings_through_wp_cli(): void {
		$this->boot();
		$settings = Kernel::container()->get( Settings::class );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$command = new SettingsCommand( Kernel::container() );
		$command->set( array( 'features.back_to_top', '0' ) );

		$this->assertFalse( $settings->repository()->has( 'features.back_to_top' ) );
		$this->assertTrue( $settings->get( 'features.back_to_top' ) );
	}

	public function test_cache_version_is_written_through_settings_repository(): void {
		$this->boot();
		delete_option( 'joo_cache_version' );

		Kernel::container()->get( Settings::class )->repository()->bumpCacheVersion();

		$this->assertGreaterThan( 0, (int) get_option( 'joo_cache_version' ) );
	}

	public function test_the_menu_entry_is_added_under_appearance(): void {
		$page = $this->page();
		$this->asAdmin();
		global $submenu;
		set_current_screen( 'dashboard' );
		$page->addMenu();
		$slugs = array_column( $submenu['themes.php'] ?? array(), 2 );
		$this->assertContains( SettingsPage::SLUG, $slugs );
	}

	public function test_editor_panels_register_rest_meta_with_capability_checks(): void {
		$this->boot();
		$panels = Kernel::container()->get( EditorPanels::class );
		$panels->panel( 'joo-lazy', 'Lazy loading' );
		$panels->add(
			array(
				'key'     => '_joo_lazy_override',
				'type'    => 'string',
				'label'   => 'Lazy loading',
				'panel'   => 'joo-lazy',
				'enum'    => array( 'default', 'on', 'off' ),
				'default' => 'default',
			)
		);
		$panels->registerMeta();

		$post = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$request = new \WP_REST_Request( 'POST', '/wp/v2/posts/' . $post );
		$request->set_param( 'meta', array( '_joo_lazy_override' => 'off' ) );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'off', get_post_meta( $post, '_joo_lazy_override', true ) );

		$bad = new \WP_REST_Request( 'POST', '/wp/v2/posts/' . $post );
		$bad->set_param( 'meta', array( '_joo_lazy_override' => 'sometimes' ) );
		$this->assertGreaterThanOrEqual( 400, rest_do_request( $bad )->get_status(), 'values outside the enum are rejected' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$denied = new \WP_REST_Request( 'POST', '/wp/v2/posts/' . $post );
		$denied->set_param( 'meta', array( '_joo_lazy_override' => 'on' ) );
		$this->assertContains( rest_do_request( $denied )->get_status(), array( 401, 403 ) );
		$this->assertSame( 'off', get_post_meta( $post, '_joo_lazy_override', true ) );
	}

	public function test_editor_panel_payload_groups_fields_by_panel(): void {
		$this->boot();
		$panels = Kernel::container()->get( EditorPanels::class );
		$panels->add(
			array(
				'key'   => '_joo_featured',
				'type'  => 'boolean',
				'label' => 'Featured',
				'panel' => 'joo-featured',
			)
		);
		$panels->add(
			array(
				'key'     => '_joo_featured_order',
				'type'    => 'integer',
				'label'   => 'Order',
				'panel'   => 'joo-featured',
				'minimum' => 0,
				'maximum' => 999,
			)
		);
		$payload  = $panels->payload();
		$featured = array_values( array_filter( $payload, static fn( array $panel ) => 'joo-featured' === $panel['id'] ) );
		$this->assertCount( 1, $featured );
		$this->assertSame( array( 'toggle', 'number' ), array_column( $featured[0]['fields'], 'ui' ) );
		$this->assertSame( 999, $panels->sanitize( $panels->fields()['_joo_featured_order'], '5000' ) );
	}

	public function test_invalid_panel_definitions_are_rejected(): void {
		$this->boot();
		$panels = Kernel::container()->get( EditorPanels::class );
		$this->expectException( \InvalidArgumentException::class );
		$panels->add(
			array(
				'key'   => 'not_prefixed',
				'type'  => 'string',
				'label' => 'x',
				'panel' => 'p',
			)
		);
	}

	public function test_classic_editor_boxes_render_supported_controls_and_the_block_editor_hint(): void {
		$panels = new EditorPanels();
		foreach (
			array(
				array(
					'key'   => '_joo_bad_type',
					'type'  => 'unsupported',
					'label' => 'Bad type',
					'panel' => 'card-options',
				),
				array(
					'key'   => '_joo_no_label',
					'type'  => 'string',
					'panel' => 'card-options',
				),
			)
			as $invalid_definition
		) {
			try {
				$panels->add( $invalid_definition );
				$this->fail( 'Invalid panel fields must be rejected.' );
			} catch ( \InvalidArgumentException $error ) {
				$this->assertNotSame( '', $error->getMessage() );
			}
		}
		$panels->panel( 'card-options', 'Card options' );
		$panels->add(
			array(
				'key'   => '_joo_featured',
				'type'  => 'boolean',
				'label' => 'Featured',
				'panel' => 'card-options',
				'help'  => 'Show a badge.',
			)
		);
		$panels->add(
			array(
				'key'   => '_joo_layout',
				'type'  => 'string',
				'label' => 'Layout',
				'panel' => 'card-options',
				'enum'  => array( 'wide', 'compact' ),
				'ui'    => 'select',
			)
		);
		$panels->add(
			array(
				'key'   => '_joo_caption',
				'type'  => 'string',
				'label' => 'Caption',
				'panel' => 'card-options',
				'ui'    => 'textarea',
			)
		);
		$panels->add(
			array(
				'key'   => '_joo_rank',
				'type'  => 'integer',
				'label' => 'Rank',
				'panel' => 'card-options',
				'ui'    => 'number',
			)
		);
		$panels->add(
			array(
				'key'        => '_joo_page_note',
				'type'       => 'string',
				'label'      => 'Page note',
				'panel'      => 'card-options',
				'post_types' => array( 'page' ),
			)
		);
		$panels->add(
			array(
				'key'   => '_joo_free_text',
				'type'  => 'string',
				'label' => 'Free text',
				'panel' => 'free-panel',
			)
		);

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_joo_featured', '1' );
		$panels->addClassicBoxes();
		global $wp_meta_boxes;
		$box = $wp_meta_boxes['post']['side']['default']['joo-card-options'];

		add_filter( 'use_block_editor_for_post', '__return_false' );
		ob_start();
		$box['callback']( get_post( $post_id ), $box );
		$html = (string) ob_get_clean();
		remove_filter( 'use_block_editor_for_post', '__return_false' );
		$this->assertStringContainsString( 'name="joo_panel[_joo_featured]" value="1" checked', $html );
		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( '<textarea', $html );
		$this->assertStringContainsString( 'type="number"', $html );
		$this->assertStringNotContainsString( 'Page note', $html );
		$this->assertStringContainsString( 'Show a badge.', $html );
		$payload = $panels->payload();
		$this->assertSame( 'Free panel', $payload[1]['title'] );

		add_filter( 'use_block_editor_for_post', '__return_true' );
		ob_start();
		$box['callback']( get_post( $post_id ), $box );
		$this->assertStringContainsString( 'These options are in the editor sidebar.', (string) ob_get_clean() );
		remove_filter( 'use_block_editor_for_post', '__return_true' );

		$page_box = $wp_meta_boxes['page']['side']['default']['joo-card-options'];
		$page_id  = self::factory()->post->create( array( 'post_type' => 'page' ) );
		add_filter( 'use_block_editor_for_post', '__return_false' );
		ob_start();
		$page_box['callback']( get_post( $page_id ), $page_box );
		$this->assertStringContainsString( 'Page note', (string) ob_get_clean() );
		remove_filter( 'use_block_editor_for_post', '__return_false' );

		$empty = new EditorPanels();
		$empty->enqueueEditor();
		$panels->enqueueEditor();
		$this->assertTrue( wp_script_is( 'joo-editor-panels', 'registered' ) );
	}

	public function test_classic_meta_box_saves_with_nonce_and_capability(): void {
		$this->boot();
		$panels = Kernel::container()->get( EditorPanels::class );
		$panels->add(
			array(
				'key'   => '_joo_featured',
				'type'  => 'boolean',
				'label' => 'Featured',
				'panel' => 'joo-featured',
			)
		);
		$post_id = self::factory()->post->create();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$_POST['joo_panels_nonce'] = wp_create_nonce( 'joo_panels_' . $post_id );
		$_POST['joo_panel']        = array( '_joo_featured' => '1' );
		$panels->saveClassic( $post_id, get_post( $post_id ) );
		$this->assertSame( '1', get_post_meta( $post_id, '_joo_featured', true ) );
		$_POST['joo_panels_nonce'] = 'bad';
		$_POST['joo_panel']        = array( '_joo_featured' => '0' );
		$panels->saveClassic( $post_id, get_post( $post_id ) );
		$this->assertSame( '1', get_post_meta( $post_id, '_joo_featured', true ), 'bad nonce changes nothing' );
		unset( $_POST['joo_panels_nonce'] );
		$panels->saveClassic( $post_id, get_post( $post_id ) );
		$this->assertSame( '1', get_post_meta( $post_id, '_joo_featured', true ), 'missing nonce changes nothing' );
		unset( $_POST['joo_panels_nonce'], $_POST['joo_panel'] );
	}

	public function test_site_health_core_tests(): void {
		$this->boot();
		$health = Kernel::container()->get( SiteHealth::class );
		$this->assertContains( 'joo_core_requirements', $health->ids() );
		$this->assertSame( 'good', $health->run( 'joo_core_requirements' )['status'] );
		$this->assertSame( 'good', $health->run( 'joo_manifest_valid' )['status'] );
		$this->assertSame( 'good', $health->run( 'joo_modules_status' )['status'] );
		$expected = post_type_exists( 'joo_company' ) ? 'good' : 'recommended';
		$this->assertSame( $expected, $health->run( 'joo_resume_plugin' )['status'] );
		if ( post_type_exists( 'joo_company' ) ) {
			unregister_post_type( 'joo_company' );
			$this->assertSame( 'recommended', $health->run( 'joo_resume_plugin' )['status'] );
			( new \JOOservices\Resume\PostTypes\Company() )->register();
		}
		$tests = $health->registerTests( array() );
		$this->assertArrayHasKey( 'joo_core_requirements', $tests['direct'] );
	}

	public function test_site_health_reports_failed_modules_as_critical(): void {
		add_filter( 'joo/core/modules', static fn( array $modules ) => array_merge( $modules, array( FailingModule::class ) ) );
		$this->boot();
		$result = Kernel::container()->get( SiteHealth::class )->run( 'joo_modules_status' );
		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringContainsString( 'intentional boot failure', $result['description'] );
	}

	public function test_debug_information_has_no_secrets_and_lists_core_data(): void {
		$this->boot();
		$info = Kernel::container()->get( SiteHealth::class )->debugInformation( array() );
		$this->assertArrayHasKey( 'joo-core', $info );
		$json = (string) wp_json_encode( $info );
		$this->assertStringContainsString( JOO_CORE_VERSION, $json );
		$this->assertStringNotContainsString( DB_PASSWORD, $json );
	}
}
