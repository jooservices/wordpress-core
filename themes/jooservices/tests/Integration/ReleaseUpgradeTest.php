<?php
/**
 * Release upgrade preservation checks for the theme and Resume data.
 *
 * @package JOOservices\Jooservices
 */

declare(strict_types=1);

namespace JOOservices\Jooservices\Tests\Integration;

use JOOservices\Resume\Plugin;
use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Kernel;

final class ReleaseUpgradeTest extends IntegrationTestCase {

	public function set_up(): void {
		parent::set_up();
		if ( null === Kernel::container() || 'jooservices' !== Kernel::container()->get( Manifest::class )->slug() ) {
			$this->markTestSkipped( 'Run with JOO_TEST_THEME=jooservices to verify the release upgrade path.' );
		}
	}

	public function test_update_from_previous_release_keeps_theme_settings_and_company_data(): void {
		$settings = array(
			'lazy.images'   => false,
			'lazy.comments' => true,
			'i18n.default'  => 'vi',
		);
		update_option( 'joo_jooservices_theme_settings', $settings );
		update_option( 'joo_resume_version', '0.0.9' );

		$company_id = self::factory()->post->create(
			array(
				'post_type'   => 'joo_company',
				'post_status' => 'publish',
				'post_title'  => 'Example Company',
			)
		);
		update_post_meta( $company_id, '_joo_company_role', 'Principal Engineer' );
		update_post_meta( $company_id, '_joo_company_from', '2021-04' );
		update_post_meta( $company_id, '_joo_company_to', '' );
		update_post_meta( $company_id, '_joo_company_url', 'https://example.test' );
		update_post_meta( $company_id, '_joo_company_pinned', true );

		// The prior release stored these stable WordPress options and meta keys. Run the
		// current plugin activation path to cover the code-update/rollback data contract.
		Plugin::register();
		Plugin::activate();
		Plugin::maybeFlushRewrites();

		$this->assertSame( $settings, get_option( 'joo_jooservices_theme_settings' ) );
		$this->assertSame( Plugin::VERSION, get_option( 'joo_resume_version' ) );
		$this->assertSame( 'Example Company', get_the_title( $company_id ) );
		$this->assertSame( 'Principal Engineer', get_post_meta( $company_id, '_joo_company_role', true ) );
		$this->assertSame( '2021-04', get_post_meta( $company_id, '_joo_company_from', true ) );
		$this->assertSame( '', get_post_meta( $company_id, '_joo_company_to', true ) );
		$this->assertSame( 'https://example.test', get_post_meta( $company_id, '_joo_company_url', true ) );
		$this->assertTrue( (bool) get_post_meta( $company_id, '_joo_company_pinned', true ) );
		$this->assertTrue( post_type_exists( 'joo_company' ) );
	}
}
