<?php
/**
 * REST endpoint for deferred fragments.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Lazy\Rest;

use JOOservices\ThemeCore\Lazy\FragmentLazy;

/**
 * GET /joo/v1/fragment/{name}: public and read-only; comments snapshots are private and not cached.
 *
 * @internal
 */
final class FragmentController {

	/**
	 * Fragment service.
	 *
	 * @var FragmentLazy
	 */
	private FragmentLazy $fragments;

	/**
	 * Creates the controller.
	 *
	 * @param FragmentLazy $fragments Fragment service.
	 */
	public function __construct( FragmentLazy $fragments ) {
		$this->fragments = $fragments;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	/**
	 * Registers the route.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			'joo/v1',
			'/fragment/(?P<name>[a-z0-9-]{1,64})',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'name' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Handles a request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get( \WP_REST_Request $request ) {
		$name = (string) $request['name'];
		if ( ! $this->fragments->has( $name ) ) {
			return new \WP_Error( 'joo_fragment_not_found', 'Unknown fragment.', array( 'status' => 404 ) );
		}
		$args = $this->fragments->validate( $name, $request->get_query_params() );
		if ( is_wp_error( $args ) ) {
			return $args;
		}
		$html = $this->fragments->render( $name, $args );
		if ( null === $html ) {
			return new \WP_Error( 'joo_fragment_unavailable', 'This fragment is not available right now.', array( 'status' => 404 ) );
		}
		return new \WP_REST_Response(
			array(
				'html' => $html,
				'etag' => md5( $html ),
			)
		);
	}
}
