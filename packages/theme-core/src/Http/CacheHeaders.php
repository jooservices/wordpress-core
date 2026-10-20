<?php
/**
 * HTTP caching for the public joo/v1 REST routes.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Http;

/**
 * Adds Cache-Control and a weak ETag to GET responses of the joo/v1 namespace and answers conditional
 * requests with 304. The routes only expose published public content, so responses may be cached publicly.
 *
 * @internal
 */
final class CacheHeaders {

	public const MAX_AGE = 300;
	public const SWR     = 600;

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_filter( 'rest_post_dispatch', array( $this, 'filter' ), 10, 3 );
	}

	/**
	 * Filter callback for rest_post_dispatch.
	 *
	 * @param \WP_HTTP_Response|\WP_Error $result  Response.
	 * @param \WP_REST_Server             $server  Server.
	 * @param \WP_REST_Request            $request Request.
	 * @return \WP_HTTP_Response|\WP_Error
	 */
	public function filter( $result, $server, $request ) {
		unset( $server );
		if ( ! $result instanceof \WP_HTTP_Response || 'GET' !== $request->get_method() || 0 !== strpos( $request->get_route(), '/joo/v1/' ) || 200 !== $result->get_status() ) {
			return $result;
		}
		$etag = $this->etag( $result->get_data() );
		$result->header( 'Cache-Control', sprintf( 'public, max-age=%d, stale-while-revalidate=%d', self::MAX_AGE, self::SWR ) );
		$result->header( 'ETag', $etag );
		$result->header( 'Vary', 'Accept-Encoding' );
		$match = (string) $request->get_header( 'if_none_match' );
		if ( '' !== $match && $this->matches( $match, $etag ) ) {
			$result->set_status( 304 );
			$result->set_data( null );
		}
		return $result;
	}

	/**
	 * Weak ETag of a response body.
	 *
	 * @param mixed $data Response data.
	 */
	public function etag( $data ): string {
		return 'W/"' . md5( (string) wp_json_encode( $data ) ) . '"';
	}

	/**
	 * Whether an If-None-Match header contains the ETag.
	 *
	 * @param string $header If-None-Match value.
	 * @param string $etag   Current ETag.
	 */
	public function matches( string $header, string $etag ): bool {
		foreach ( explode( ',', $header ) as $candidate ) {
			if ( trim( $candidate ) === $etag || trim( $candidate ) === '*' ) {
				return true;
			}
		}
		return false;
	}
}
