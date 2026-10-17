<?php
/**
 * Proves the integration harness boots real WordPress.
 *
 * @package JOOservices\TestKit
 */

declare(strict_types=1);

namespace JOOservices\TestKit\Tests\Integration;

use JOOservices\TestKit\IntegrationTestCase;

final class SampleIntegrationTest extends IntegrationTestCase {

	public function test_wordpress_is_loaded(): void {
		$this->assertTrue( function_exists( 'add_action' ) );
		$this->assertNotEmpty( get_bloginfo( 'version' ) );
	}

	public function test_posts_can_be_created_and_queried(): void {
		$id = self::factory()->post->create( array( 'post_title' => 'Hello integration' ) );
		$this->go_to( get_permalink( $id ) );
		$this->assertTrue( is_single() );
		$this->assertSame( 'Hello integration', get_the_title( $id ) );
	}

	public function test_head_output_can_be_captured(): void {
		$this->request( '/' );
		$this->assertStringContainsString( '<link', $this->capture( 'wp_head' ) );
	}

	public function test_resume_plugin_file_is_loaded_when_present(): void {
		$this->assertFileExists( dirname( __DIR__, 4 ) . '/plugins/jooservices-resume/jooservices-resume.php' );
	}
}
