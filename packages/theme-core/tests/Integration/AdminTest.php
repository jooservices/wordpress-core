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
use JOOservices\ThemeCore\Admin\SiteHealth;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Kernel;
use JOOservices\ThemeCore\Tests\Fixtures\Modules\FailingModule;

final class AdminTest extends IntegrationTestCase {

	private const MANIFEST = '/workspace/packages/theme-core/tests/Fixtures/manifests/valid.json';

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
		$export = wp_json_encode(
			array(
				'joo_settings_export' => 1,
				'settings'            => Kernel::container()->get( Settings::class )->repository()->all(),
			)
		);
		Kernel::container()->get( Settings::class )->repository()->reset();
		$this->assertSame( 1, Kernel::container()->get( Settings::class )->get( 'lazy.eager_count' ) );
		$result = $page->import( (string) $export );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 2, Kernel::container()->get( Settings::class )->get( 'lazy.eager_count' ) );
		$this->assertFalse( Kernel::container()->get( Settings::class )->get( 'lazy.iframes' ) );
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
		$this->assertSame( $expected, $health->run( 'joo_content_plugin' )['status'] );
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
