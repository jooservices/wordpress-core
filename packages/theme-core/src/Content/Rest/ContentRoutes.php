<?php
/**
 * Public read-only content routes.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Content\Rest;

use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Content\ContentProviderInterface;
use JOOservices\ThemeCore\Content\Model\CategoryNode;
use JOOservices\ThemeCore\Content\Model\PostCard;
use JOOservices\ThemeCore\Content\Model\Topic;

/**
 * GET /joo/v1/{companies, categories/tree, topics, featured, related/{id}, profile}. Data comes from the provider
 * (published content only). Cache headers and 304 handling are added by Http\CacheHeaders.
 *
 * @internal
 */
final class ContentRoutes {

	public const NAMESPACE = 'joo/v1';

	/**
	 * Provider.
	 *
	 * @var ContentProviderInterface
	 */
	private ContentProviderInterface $provider;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Creates the routes.
	 *
	 * @param ContentProviderInterface $provider Provider.
	 * @param Settings                 $settings Settings.
	 */
	public function __construct( ContentProviderInterface $provider, Settings $settings ) {
		$this->provider = $provider;
		$this->settings = $settings;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * Registers the routes.
	 */
	public function register(): void {
		$route = function ( string $path, string $callback, array $args = array() ): void {
			register_rest_route(
				self::NAMESPACE,
				'/' . ltrim( $path, '/' ),
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, $callback ),
					'permission_callback' => '__return_true',
					'args'                => $args,
				)
			);
		};
		$bool  = array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
		);
		$route(
			'/companies',
			'companies',
			array(
				'page'           => array(
					'type'    => 'integer',
					'default' => 1,
					'minimum' => 1,
				),
				'per_page'       => array(
					'type'    => 'integer',
					'default' => 5,
					'minimum' => 1,
					'maximum' => 20,
				),
				'exclude_pinned' => $bool + array( 'default' => true ),
			)
		);
		$route( '/categories/tree', 'categoryTree', array( 'hide_empty' => $bool + array( 'default' => true ) ) );
		$route(
			'/topics',
			'topics',
			array(
				'limit'    => array(
					'type'    => 'integer',
					'default' => (int) $this->settings->get( 'content.topics_limit', 4 ),
					'minimum' => 1,
					'maximum' => 20,
				),
				'strategy' => array(
					'type'    => 'string',
					'enum'    => array( 'count', 'manual' ),
					'default' => (string) $this->settings->get( 'content.topics_strategy', 'count' ),
				),
			)
		);
		$route(
			'/featured',
			'featured',
			array(
				'limit' => array(
					'type'    => 'integer',
					'default' => 5,
					'minimum' => 1,
					'maximum' => 12,
				),
			)
		);
		$route(
			'/related/(?P<post_id>\d+)',
			'related',
			array(
				'post_id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'limit'   => array(
					'type'    => 'integer',
					'default' => 3,
					'minimum' => 1,
					'maximum' => 8,
				),
			)
		);
		$route( '/profile', 'profile' );
	}

	/**
	 * Companies page.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function companies( \WP_REST_Request $request ): \WP_REST_Response {
		$page     = $this->provider->companies( (int) $request['page'], (int) $request['per_page'], (bool) $request['exclude_pinned'] );
		$response = new \WP_REST_Response( array_map( static fn( $c ) => $c->toArray(), $page->items ) );
		$response->header( 'X-WP-Total', (string) $page->total );
		$response->header( 'X-WP-TotalPages', (string) $page->totalPages );
		return $response;
	}

	/**
	 * Category tree.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function categoryTree( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( array_map( static fn( CategoryNode $n ) => $n->toArray(), $this->provider->categoryTree( (bool) $request['hide_empty'] ) ) );
	}

	/**
	 * Topics.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function topics( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( array_map( static fn( Topic $t ) => $t->toArray(), $this->provider->topics( (int) $request['limit'], (string) $request['strategy'] ) ) );
	}

	/**
	 * Featured posts.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function featured( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( array_map( static fn( PostCard $p ) => $p->toArray(), $this->provider->featured( (int) $request['limit'] ) ) );
	}

	/**
	 * Related posts.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function related( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( array_map( static fn( PostCard $p ) => $p->toArray(), $this->provider->related( (int) $request['post_id'], (int) $request['limit'] ) ) );
	}

	/**
	 * Owner profile (empty array when there is none).
	 */
	public function profile(): \WP_REST_Response {
		$profile = $this->provider->profile();
		if ( null === $profile ) {
			return new \WP_REST_Response( array() );
		}
		$data = $profile->toArray();
		return new \WP_REST_Response( $data );
	}
}
