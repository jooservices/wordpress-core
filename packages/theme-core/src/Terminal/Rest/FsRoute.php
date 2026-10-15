<?php
/**
 * REST route of the terminal file system.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Terminal\Rest;

use JOOservices\ThemeCore\Terminal\FsBuilder;

/**
 * GET /joo/v1/terminal/fs?lang= (public, read-only, cached by Http\CacheHeaders).
 *
 * @internal
 */
final class FsRoute {

	/**
	 * Builder.
	 *
	 * @var FsBuilder
	 */
	private FsBuilder $builder;

	/**
	 * Creates the route.
	 *
	 * @param FsBuilder $builder Builder.
	 */
	public function __construct( FsBuilder $builder ) {
		$this->builder = $builder;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * Registers the route.
	 */
	public function register(): void {
		register_rest_route(
			'joo/v1',
			'/terminal/fs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'lang' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static fn( $v ) => sanitize_text_field( (string) $v ),
						'validate_callback' => static fn( $v ) => '' === (string) $v || 1 === preg_match( '/^[a-zA-Z]{2,3}([-_][a-zA-Z]{2})?$/', (string) $v ),
					),
				),
			)
		);
	}

	/**
	 * Handles a request.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function get( \WP_REST_Request $request ): \WP_REST_Response {
		$raw  = (string) $request['lang'];
		$lang = '' === $raw ? '' : \JOOservices\ThemeCore\Seo\Context::normaliseLanguage( $raw );
		return new \WP_REST_Response( $this->builder->build( $lang ) );
	}
}
