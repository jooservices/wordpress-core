<?php
/**
 * HTTP semantics for special responses.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo;

use JOOservices\ThemeCore\Support\Logger;

/**
 * Real 404s, no soft 404 redirects, blank search redirect and feed discovery hygiene.
 *
 * @internal
 */
final class HttpSemantics {

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Creates the service.
	 *
	 * @param Logger $logger Logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		if ( is_admin() ) {
			return;
		}
		add_action( 'template_redirect', array( $this, 'handle' ), 5 );
		add_filter( 'feed_links_show_comments_feed', array( $this, 'commentsFeed' ) );
	}

	/**
	 * Target for a blank search (`?s=`), or null.
	 */
	public function blankSearchTarget(): ?string {
		if ( ! isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return null;
		}
		$query = is_string( $_GET['s'] ) ? trim( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return '' === $query ? home_url( '/' ) : null;
	}

	/**
	 * Redirects blank searches and makes sure a 404 is sent as a 404.
	 */
	public function handle(): void {
		$target = $this->blankSearchTarget();
		if ( null !== $target && wp_safe_redirect( $target, 301, 'JOOservices' ) ) {
			exit;
		}
		if ( is_404() ) {
			$this->send404();
		}
	}

	/**
	 * Sends the 404 status and no-cache headers.
	 */
	public function send404(): void {
		if ( headers_sent() ) {
			$this->logger->warning( 'A 404 response could not be marked as 404: headers were already sent.' );
			return;
		}
		if ( function_exists( 'http_response_code' ) && false !== http_response_code() && 404 !== http_response_code() ) {
			$this->logger->warning( 'A theme or plugin set the status to ' . http_response_code() . ' on a 404 page; corrected to 404.' );
		}
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Hides the comments feed link when comments are closed site-wide.
	 *
	 * @param mixed $show Current value.
	 */
	public function commentsFeed( $show ): bool {
		return 'open' === get_option( 'default_comment_status' ) ? (bool) $show : false;
	}
}
