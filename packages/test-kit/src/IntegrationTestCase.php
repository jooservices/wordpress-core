<?php
/**
 * Base class for integration tests that need real WordPress.
 *
 * @package JOOservices\TestKit
 */

declare(strict_types=1);

namespace JOOservices\TestKit;

/**
 * Adds helpers on top of WP_UnitTestCase: request simulation and head/footer output capture.
 */
abstract class IntegrationTestCase extends \WP_UnitTestCase {

	/**
	 * Captures the output of a hook (for example wp_head).
	 *
	 * @param string $hook Action name.
	 */
	protected function capture( string $hook ): string {
		ob_start();
		do_action( $hook );
		return (string) ob_get_clean();
	}

	/**
	 * Simulates a front-end request and runs the main query.
	 *
	 * @param string $url Absolute or relative URL.
	 */
	protected function request( string $url ): void {
		$this->go_to( $url );
	}

	/**
	 * Asserts that the head output of the current request contains a string.
	 *
	 * @param string $needle Expected substring.
	 */
	protected function assertHeadContains( string $needle ): void {
		$this->assertStringContainsString( $needle, $this->capture( 'wp_head' ) );
	}

	/**
	 * Resets plugin options created during a test.
	 *
	 * @param string[] $options Option names.
	 */
	protected function resetOptions( array $options ): void {
		foreach ( $options as $option ) {
			delete_option( $option );
		}
	}
}
