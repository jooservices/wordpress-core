<?php
/**
 * WP-CLI settings command tests.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Admin\Cli\SettingsCommand;
use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Kernel;

final class SettingsCommandTest extends IntegrationTestCase {

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
		parent::tear_down();
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_settings_commands_list_get_set_and_reset_values(): void {
		Kernel::boot( self::MANIFEST );
		do_action( 'after_setup_theme' );
		require __DIR__ . '/../Fixtures/wp-cli-recording-stub.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$command = new SettingsCommand( Kernel::container() );

		ob_start();
		$command->list(
			array(),
			array(
				'format' => 'json',
				'source' => 'true',
			)
		);
		$list_output = (string) ob_get_clean();
		if ( isset( $GLOBALS['joo_wp_cli_format_items'][0] ) ) {
			[ $format, $rows, $fields ] = $GLOBALS['joo_wp_cli_format_items'][0];
			$rows                       = array_column( $rows, null, 'key' );
			$this->assertSame( 'json', $format );
			$this->assertSame( array( 'key', 'value', 'source' ), $fields );
			$this->assertSame( 'core', $rows['lazy.eager_count']['source'] );
		} else {
			$this->assertNotSame( '', $list_output );
		}

		ob_start();
		$command->list( array(), array() );
		$list_output = (string) ob_get_clean();
		if ( isset( $GLOBALS['joo_wp_cli_format_items'][1] ) ) {
			$this->assertSame( array( 'key', 'value' ), $GLOBALS['joo_wp_cli_format_items'][1][2] );
		} else {
			$this->assertNotSame( '', $list_output );
		}
		$command->get( array( 'core.debug' ) );
		$command->get( array( 'content.topics_manual' ) );

		$command->set( array( 'features.back_to_top', 'no' ) );
		$this->assertFalse( Kernel::container()->get( \JOOservices\ThemeCore\Config\Settings::class )->get( 'features.back_to_top' ) );
		$command->set( array( 'lazy.eager_count', '3' ) );
		$this->assertSame( 3, Kernel::container()->get( \JOOservices\ThemeCore\Config\Settings::class )->get( 'lazy.eager_count' ) );
		$command->set( array( 'i18n.languages', '["en","fr"]' ) );
		$this->assertSame( array( 'en', 'fr' ), Kernel::container()->get( \JOOservices\ThemeCore\Config\Settings::class )->get( 'i18n.languages' ) );
		$command->set( array( 'seo.yield', 'never' ) );
		$this->assertSame( 'never', Kernel::container()->get( \JOOservices\ThemeCore\Config\Settings::class )->get( 'seo.yield' ) );

		$command->reset( array( 'lazy.eager_count' ), array() );
		$this->assertSame( 1, Kernel::container()->get( \JOOservices\ThemeCore\Config\Settings::class )->get( 'lazy.eager_count' ) );
		$command->reset(
			array(),
			array(
				'all' => 'true',
				'yes' => 'true',
			)
		);
		$this->assertSame( true, Kernel::container()->get( \JOOservices\ThemeCore\Config\Settings::class )->get( 'features.back_to_top' ) );
	}
}
