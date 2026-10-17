<?php
/**
 * Internationalisation (real WordPress).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Assets\RuntimeConfig;
use JOOservices\ThemeCore\I18n\I18nModule;
use JOOservices\ThemeCore\I18n\LanguageTagging;
use JOOservices\ThemeCore\I18n\Languages;
use JOOservices\ThemeCore\I18n\Translations;
use JOOservices\ThemeCore\Kernel;

final class I18nTest extends IntegrationTestCase {

	private const FIXTURES = __DIR__ . '/../Fixtures/manifests/';

	public function set_up(): void {
		parent::set_up();
		Kernel::reset();
		Notices::reset();
		$GLOBALS['wp_rest_server'] = null;
		$this->set_permalink_structure( '/%postname%/' );
		wp_cache_flush();
	}

	public function tear_down(): void {
		unload_textdomain( 'joo-core' );
		Kernel::reset();
		$GLOBALS['wp_rest_server'] = null;
		parent::tear_down();
	}

	private function boot( string $manifest = 'multilang.json' ): Languages {
		Kernel::boot( self::FIXTURES . $manifest );
		do_action( 'after_setup_theme' );
		return Kernel::container()->get( Languages::class );
	}

	public function test_vietnamese_files_ship_and_switch_the_core_strings(): void {
		$this->assertFileExists( JOO_CORE_DIR . '/languages/joo-core-vi.mo' );
		$this->assertFileExists( JOO_CORE_DIR . '/languages/joo-core.pot' );
		$vi = static fn() => 'vi';
		add_filter( 'locale', $vi );
		unload_textdomain( 'joo-core' );
		$this->assertTrue( I18nModule::loadCore() );
		$this->assertSame( 'Trang chủ', __( 'Home', 'joo-core' ) );
		/* translators: %d: minutes. */
		$this->assertSame( '3 phút đọc', sprintf( _n( '%d min read', '%d min read', 3, 'joo-core' ), 3 ) );
		$this->assertSame( 'Tìm kiếm', __( 'Search', 'joo-core' ) );
		remove_filter( 'locale', $vi );
		unload_textdomain( 'joo-core' );
		$this->assertFalse( I18nModule::loadCore(), 'English needs no file' );
		$this->assertSame( 'Home', __( 'Home', 'joo-core' ) );
	}

	public function test_the_vietnamese_dictionary_is_complete(): void {
		$dict = json_decode( (string) file_get_contents( JOO_CORE_DIR . '/languages/vi.json' ), true );
		$pot  = (string) file_get_contents( JOO_CORE_DIR . '/languages/joo-core.pot' );
		preg_match_all( '/^msgid "(.+)"$/m', $pot, $m );
		$missing = array();
		foreach ( $m[1] as $id ) {
			$plain = stripcslashes( $id );
			if ( ! array_key_exists( $plain, $dict ) ) {
				$missing[] = $plain;
			}
		}
		$this->assertSame( array(), $missing, 'every shipped string has a Vietnamese translation (run make i18n)' );
	}

	public function test_html_lang_follows_the_language_tag_of_the_post(): void {
		$this->boot();
		$vi = self::factory()->post->create();
		$en = self::factory()->post->create();
		update_post_meta( $vi, '_joo_lang', 'vi' );
		update_post_meta( $en, '_joo_lang', 'en' );
		$this->go_to( get_permalink( $vi ) );
		$this->assertStringContainsString( 'lang="vi"', get_language_attributes() );
		$this->assertSame( 1, substr_count( get_language_attributes(), 'lang=' ), 'exactly one lang attribute' );
		$this->go_to( get_permalink( $en ) );
		$this->assertStringContainsString( 'lang="en"', get_language_attributes() );
		$this->go_to( '/' );
		$this->assertStringContainsString( 'lang="en"', get_language_attributes(), 'site locale en_US maps to en' );
	}

	public function test_current_language_precedence_and_defaults(): void {
		$languages = $this->boot();
		$this->assertSame( array( 'en', 'vi' ), $languages->codes() );
		$this->assertSame( 'en', $languages->default() );
		$this->assertTrue( $languages->isMultilingual() );
		$this->assertSame( 'vi', $languages->language( 'vi' )->locale );
		$this->assertSame( 'Tiếng Việt', $languages->language( 'vi' )->name );
		$this->assertSame( 'en_US', $languages->language( 'en' )->locale );
		$this->assertSame( 'pt_BR', $languages->language( 'pt-BR' )->locale );
		$post = self::factory()->post->create();
		update_post_meta( $post, '_joo_lang', 'vi' );
		$this->go_to( get_permalink( $post ) );
		$this->assertSame( 'vi', $languages->current() );
		$this->assertSame( 'vi', $languages->locale() );
		update_post_meta( $post, '_joo_lang', 'xx' );
		$this->assertSame( '', (string) get_post_meta( $post, '_joo_lang', true ), 'unconfigured languages are refused' );
		$this->assertSame( 'en', $languages->current() );
	}

	public function test_a_single_language_site_is_not_multilingual(): void {
		$languages = $this->boot( 'perf.json' );
		$this->assertFalse( $languages->isMultilingual() );
		$this->assertSame( array( 'en' ), $languages->codes() );
	}

	public function test_rtl_languages_get_a_dir_attribute(): void {
		add_filter( 'joo/settings/value', static fn( $v, $key ) => 'i18n.languages' === $key ? array( 'en', 'ar' ) : $v, 10, 2 );
		$languages = $this->boot();
		$this->assertTrue( $languages->language( 'ar' )->rtl );
		$post = self::factory()->post->create();
		update_post_meta( $post, '_joo_lang', 'ar' );
		$this->go_to( get_permalink( $post ) );
		$this->assertStringContainsString( 'dir="rtl"', get_language_attributes() );
		$this->assertStringContainsString( 'lang="ar"', get_language_attributes() );
	}

	public function test_the_locale_is_switched_for_core_strings_only_when_enabled(): void {
		add_filter( 'joo/settings/value', static fn( $v, $key ) => 'i18n.switch_locale' === $key ? true : $v, 10, 2 );
		$languages = $this->boot();
		$post      = self::factory()->post->create();
		update_post_meta( $post, '_joo_lang', 'vi' );
		$this->go_to( get_permalink( $post ) );
		// WordPress only switches to installed languages: announce vi for this test.
		add_filter( 'get_available_languages', static fn( $langs ) => array_merge( (array) $langs, array( 'vi' ) ) );
		$previous                      = $GLOBALS['wp_locale_switcher'];
		$GLOBALS['wp_locale_switcher'] = new \WP_Locale_Switcher();
		$GLOBALS['wp_locale_switcher']->init();
		try {
			$languages->maybeSwitchLocale();
			$this->assertTrue( is_locale_switched() );
			$this->assertSame( 'vi', determine_locale() );
			$this->assertSame( 'vi', $languages->locale() );
		} finally {
			restore_previous_locale();
			$GLOBALS['wp_locale_switcher'] = $previous;
		}
	}

	public function test_the_locale_stays_when_switching_is_off(): void {
		$languages = $this->boot();
		$post      = self::factory()->post->create();
		update_post_meta( $post, '_joo_lang', 'vi' );
		$this->go_to( get_permalink( $post ) );
		$before = determine_locale();
		$languages->maybeSwitchLocale();
		$this->assertSame( $before, determine_locale() );
	}

	public function test_translation_groups_resolve_to_the_root_and_skip_unpublished_posts(): void {
		$this->boot();
		$translations = Kernel::container()->get( Translations::class );
		$en           = self::factory()->post->create();
		$vi           = self::factory()->post->create();
		$draft        = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		update_post_meta( $en, '_joo_lang', 'en' );
		update_post_meta( $vi, '_joo_lang', 'vi' );
		update_post_meta( $vi, '_joo_translation_of', $en );
		update_post_meta( $draft, '_joo_lang', 'vi' );
		$this->assertSame( $en, $translations->root( $vi ) );
		$this->assertSame(
			array(
				'en' => $en,
				'vi' => $vi,
			),
			$translations->forPost( $vi )
		);
		$this->assertSame(
			array(
				'en' => $en,
				'vi' => $vi,
			),
			$translations->forPost( $en )
		);
	}

	public function test_trashing_the_root_promotes_the_oldest_translation(): void {
		$this->boot();
		$root = self::factory()->post->create();
		$vi   = self::factory()->post->create();
		$fr   = self::factory()->post->create();
		update_post_meta( $root, '_joo_lang', 'en' );
		update_post_meta( $vi, '_joo_lang', 'vi' );
		update_post_meta( $fr, '_joo_lang', 'fr' );
		update_post_meta( $vi, '_joo_translation_of', $root );
		update_post_meta( $fr, '_joo_translation_of', $root );
		wp_trash_post( $root );
		$this->assertSame( '', (string) get_post_meta( $vi, '_joo_translation_of', true ), 'the oldest translation became the root' );
		$this->assertSame( (string) $vi, (string) get_post_meta( $fr, '_joo_translation_of', true ), 'the others follow it' );
	}

	public function test_invalid_translation_links_are_rejected(): void {
		$this->boot();
		$a    = self::factory()->post->create();
		$b    = self::factory()->post->create();
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $a, '_joo_translation_of', $a );
		$this->assertSame( '', (string) get_post_meta( $a, '_joo_translation_of', true ), 'itself' );
		update_post_meta( $b, '_joo_translation_of', $a );
		$this->assertSame( (string) $a, (string) get_post_meta( $b, '_joo_translation_of', true ) );
		update_post_meta( $page, '_joo_translation_of', $a );
		$this->assertSame( '', (string) get_post_meta( $page, '_joo_translation_of', true ), 'a page cannot translate a post' );
	}

	public function test_rest_fields_expose_language_and_translations(): void {
		$this->boot();
		$en = self::factory()->post->create();
		$vi = self::factory()->post->create();
		update_post_meta( $en, '_joo_lang', 'en' );
		update_post_meta( $vi, '_joo_lang', 'vi' );
		update_post_meta( $vi, '_joo_translation_of', $en );
		wp_cache_flush();
		do_action( 'rest_api_init' );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/posts/' . $vi ) );
		$data     = $response->get_data();
		$this->assertSame( 'vi', $data['joo_lang'] );
		$this->assertSame( $en, $data['joo_translations']['en']['id'] );
		$this->assertSame( get_permalink( $vi ), $data['joo_translations']['vi']['url'] );
	}

	public function test_admin_column_filter_and_bulk_action(): void {
		set_current_screen( 'edit-post' );
		$this->boot();
		$tagging = Kernel::container()->get( LanguageTagging::class );
		$post    = self::factory()->post->create();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertArrayHasKey( 'joo_lang', $tagging->columns( array() ) );
		update_post_meta( $post, '_joo_lang', 'vi' );
		ob_start();
		$tagging->column( 'joo_lang', $post );
		$this->assertSame( 'VI', ob_get_clean() );
		$actions = $tagging->bulkActions( array() );
		$this->assertArrayHasKey( 'joo_lang_vi', $actions );
		$this->assertArrayHasKey( 'joo_lang_en', $actions );
		$tagging->handleBulk( 'x', 'joo_lang_en', array( $post ) );
		$this->assertSame( 'en', get_post_meta( $post, '_joo_lang', true ) );
		$tagging->handleBulk( 'x', 'joo_lang_zz', array( $post ) );
		$this->assertSame( 'en', get_post_meta( $post, '_joo_lang', true ), 'unknown languages are ignored' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$this->assertArrayNotHasKey( 'joo_lang_vi', $tagging->bulkActions( array() ) );
		$tagging->handleBulk( 'x', 'joo_lang_vi', array( $post ) );
		$this->assertSame( 'en', get_post_meta( $post, '_joo_lang', true ), 'contributors cannot retag posts' );
		ob_start();
		$tagging->filterDropdown( 'post' );
		$this->assertStringContainsString( 'name="joo_lang"', (string) ob_get_clean() );
	}

	public function test_the_javascript_string_map_is_part_of_the_runtime_config(): void {
		$this->boot();
		$data = Kernel::container()->get( RuntimeConfig::class )->data();
		$this->assertSame( 'Retry', $data['i18n']['retry'] );
		$this->assertArrayHasKey( 'page_of', $data['i18n'] );
		add_filter( 'locale', static fn() => 'vi' );
		unload_textdomain( 'joo-core' );
		I18nModule::loadCore();
		$this->assertSame( 'Thử lại', Kernel::container()->get( RuntimeConfig::class )->data()['i18n']['retry'] );
	}
}
