<?php
/**
 * Hook deprecation integration tests (real WordPress).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Integration;

use JOOservices\TestKit\IntegrationTestCase;
use JOOservices\ThemeCore\Tests\Fixtures\Hooks\DeprecatedHook;

final class HooksDeprecationTest extends IntegrationTestCase {

	/**
	 * @expectedDeprecated joo/fixture/deprecated
	 */
	public function test_deprecated_fixture_logs_once_without_breaking_front_end_content(): void {
		$this->assertTrue( defined( 'WP_DEBUG' ) && WP_DEBUG, 'The integration suite runs with WordPress debug logging enabled.' );

		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'Deprecation fixture',
				'post_content' => 'Front-end fixture content',
			)
		);
		$this->request( get_permalink( $post_id ) );
		$this->assertTrue( is_singular() );
		$this->assertSame( $post_id, get_queried_object_id() );

		DeprecatedHook::register();
		add_filter( 'the_content', array( DeprecatedHook::class, 'filterContent' ), 99 );

		add_filter( 'deprecated_hook_trigger_error', '__return_true', PHP_INT_MAX );
		$notices = array();
		set_error_handler(
			static function ( int $severity, string $message ) use ( &$notices ): bool {
				if ( E_USER_DEPRECATED !== $severity || false === strpos( $message, DeprecatedHook::NAME ) ) {
					return false;
				}

				$notices[] = $message;
				return true;
			}
		);
		try {
			$content = apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) );
			$second  = apply_filters( 'the_content', 'Repeated fixture content' );
			$direct  = DeprecatedHook::apply( 'Direct fixture value' );
		} finally {
			restore_error_handler();
			remove_filter( 'deprecated_hook_trigger_error', '__return_true', PHP_INT_MAX );
		}

		$this->assertStringContainsString( 'Front-end fixture content', $content );
		$this->assertStringContainsString( '[fixture replacement]', $content );
		$this->assertStringContainsString( 'Repeated fixture content', $second );
		$this->assertStringContainsString( '[fixture replacement]', $second );
		$this->assertSame( 'Direct fixture value [fixture replacement]', $direct );
		$this->assertCount( 1, $notices, 'A deprecated hook logs one warning per request.' );
		$this->assertStringContainsString( DeprecatedHook::REPLACEMENT, $notices[0] );
	}
}
