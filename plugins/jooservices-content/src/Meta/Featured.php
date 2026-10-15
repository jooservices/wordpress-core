<?php
/**
 * Featured posts.
 *
 * @package JOOservices\Content
 */

declare(strict_types=1);

namespace JOOservices\Content\Meta;

/**
 * Featured flag and order for posts, with list column, filter and bulk actions.
 */
final class Featured {

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		$this->register();
		add_filter( 'manage_post_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_post_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'manage_edit-post_sortable_columns', array( $this, 'sortable' ) );
		add_action( 'pre_get_posts', array( $this, 'adminQuery' ) );
		add_action( 'restrict_manage_posts', array( $this, 'filterDropdown' ) );
		add_filter( 'bulk_actions-edit-post', array( $this, 'bulkActions' ) );
		add_filter( 'handle_bulk_actions-edit-post', array( $this, 'handleBulk' ), 10, 3 );
	}

	/**
	 * Registers the meta.
	 */
	public function register(): void {
		$auth = static fn( $allowed, $key, $postId ) => current_user_can( 'edit_post', (int) $postId );
		register_post_meta(
			'post',
			'_joo_featured',
			array(
				'single'            => true,
				'type'              => 'boolean',
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => static fn( $v ): bool => filter_var( $v, FILTER_VALIDATE_BOOLEAN ),
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			'post',
			'_joo_featured_order',
			array(
				'single'            => true,
				'type'              => 'integer',
				'default'           => 0,
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'integer',
						'minimum' => 0,
						'maximum' => 999,
					),
				),
				'sanitize_callback' => static fn( $v ) => max( 0, min( 999, absint( $v ) ) ),
				'auth_callback'     => $auth,
			)
		);
	}

	/**
	 * Adds the Featured column.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( $columns ): array {
		$columns['joo_featured'] = __( 'Featured', 'jooservices-content' );
		return $columns;
	}

	/**
	 * Column output (star and order).
	 *
	 * @param string $column Column.
	 * @param int    $postId Post id.
	 */
	public function column( $column, $postId ): void {
		if ( 'joo_featured' !== $column ) {
			return;
		}
		if ( (bool) get_post_meta( (int) $postId, '_joo_featured', true ) ) {
			echo '<span aria-label="' . esc_attr__( 'Featured', 'jooservices-content' ) . '">★</span> ' . (int) get_post_meta( (int) $postId, '_joo_featured_order', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- integer.
		}
	}

	/**
	 * Sortable column.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function sortable( $columns ): array {
		$columns['joo_featured'] = 'joo_featured';
		return $columns;
	}

	/**
	 * Admin list: sort by featured order and "featured only" filter.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function adminQuery( $query ): void {
		if ( ! is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return;
		}
		$type = (string) $query->get( 'post_type' );
		if ( '' !== $type && 'post' !== $type ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		if ( isset( $_GET['joo_featured'] ) && '1' === $_GET['joo_featured'] ) {
			$query->set(
				'meta_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					array(
						'key'   => '_joo_featured',
						'value' => '1',
					),
				)
			);
		}
		if ( 'joo_featured' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', '_joo_featured_order' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * "Featured only" dropdown.
	 *
	 * @param string $postType Post type.
	 */
	public function filterDropdown( $postType ): void {
		if ( 'post' !== $postType ) {
			return;
		}
		$on = isset( $_GET['joo_featured'] ) && '1' === $_GET['joo_featured']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<select name="joo_featured"><option value="">' . esc_html__( 'All posts', 'jooservices-content' ) . '</option><option value="1"' . selected( $on, true, false ) . '>' . esc_html__( 'Featured only', 'jooservices-content' ) . '</option></select>';
	}

	/**
	 * Bulk actions.
	 *
	 * @param array<string, string> $actions Actions.
	 * @return array<string, string>
	 */
	public function bulkActions( $actions ): array {
		if ( current_user_can( 'edit_others_posts' ) ) {
			$actions['joo_feature']   = __( 'Mark featured', 'jooservices-content' );
			$actions['joo_unfeature'] = __( 'Unmark featured', 'jooservices-content' );
		}
		return $actions;
	}

	/**
	 * Applies the bulk action (WordPress verified the nonce; the capability is checked again here).
	 *
	 * @param string     $redirect Redirect URL.
	 * @param string     $action   Action.
	 * @param list<int>  $postIds  Selected ids.
	 */
	public function handleBulk( $redirect, $action, $postIds ): string {
		if ( ! in_array( $action, array( 'joo_feature', 'joo_unfeature' ), true ) || ! current_user_can( 'edit_others_posts' ) ) {
			return (string) $redirect;
		}
		foreach ( (array) $postIds as $id ) {
			if ( current_user_can( 'edit_post', (int) $id ) ) {
				update_post_meta( (int) $id, '_joo_featured', 'joo_feature' === $action );
			}
		}
		return (string) $redirect;
	}
}
