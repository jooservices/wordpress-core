<?php
/**
 * Prints the dynamic values the health check needs, as JSON (wp eval-file).
 *
 * @package JOOservices\Tooling
 */

// phpcs:disable

$find = static function ( array $args ): ?WP_Post {
	$posts = get_posts( array_merge( array( 'post_type' => 'post', 'posts_per_page' => 1, 'suppress_filters' => true, 'meta_key' => '_joo_demo', 'meta_value' => '1', 'orderby' => 'ID' ), $args ) );
	return $posts[0] ?? null;
};
$path = static fn( ?WP_Post $post ): string => $post ? (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH ) : '';
$out  = array(
	'draft-id'       => (string) ( $find( array( 'post_status' => 'draft' ) )->ID ?? '' ),
	'private-id'     => (string) ( $find( array( 'post_status' => 'private' ) )->ID ?? '' ),
	'first-post'     => $path( $find( array( 'post_status' => 'publish', 'has_password' => false, 'orderby' => 'date', 'order' => 'DESC' ) ) ),
	'protected-post' => $path( $find( array( 'post_status' => 'publish', 'has_password' => true ) ) ),
);
echo wp_json_encode( $out );
