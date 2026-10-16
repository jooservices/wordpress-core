<?php
/**
 * Company list table: logo, role, period, current, visibility, order, filter and bulk actions.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Admin;

use JOOservices\Resume\Model\Keys;
use JOOservices\Resume\PostTypes\Company;

/**
 * Extends the list that `PostTypes\Company` registers.
 */
final class CompanyList {

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_filter( 'manage_' . Company::TYPE . '_posts_columns', array( $this, 'columns' ), 20 );
		add_action( 'manage_' . Company::TYPE . '_posts_custom_column', array( $this, 'column' ), 20, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'filter' ) );
		add_action( 'pre_get_posts', array( $this, 'applyFilter' ), 5 );
		add_filter( 'bulk_actions-edit-' . Company::TYPE, array( $this, 'bulkActions' ) );
		add_filter( 'handle_bulk_actions-edit-' . Company::TYPE, array( $this, 'handleBulk' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulkNotice' ) );
	}

	/**
	 * Logo first, then the existing columns, plus current and visibility.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( $columns ): array {
		return array(
			'cb'         => $columns['cb'] ?? '',
			'logo'       => __( 'Logo', 'jooservices-resume' ),
			'title'      => __( 'Company', 'jooservices-resume' ),
			'role'       => __( 'Role', 'jooservices-resume' ),
			'period'     => __( 'Period', 'jooservices-resume' ),
			'current'    => __( 'Current', 'jooservices-resume' ),
			'pinned'     => __( 'Pinned', 'jooservices-resume' ),
			'visibility' => __( 'Visibility', 'jooservices-resume' ),
			'menu_order' => __( 'Order', 'jooservices-resume' ),
		);
	}

	/**
	 * New columns.
	 *
	 * @param string $column Column.
	 * @param int    $postId Post id.
	 */
	public function column( $column, $postId ): void {
		$postId = (int) $postId;
		switch ( $column ) {
			case 'logo':
				$logo = (int) get_post_meta( $postId, '_joo_company_logo_id', true );
				echo $logo > 0 ? wp_get_attachment_image( $logo, array( 40, 40 ), false, array( 'alt' => '' ) ) : '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
				break;
			case 'current':
				echo '' === (string) get_post_meta( $postId, '_joo_company_to', true ) ? esc_html__( 'Yes', 'jooservices-resume' ) : esc_html__( 'No', 'jooservices-resume' );
				break;
			case 'visibility':
				$status = get_post_status( $postId );
				$public = json_decode( (string) get_post_meta( $postId, Keys::META['public_fields'], true ), true );
				$label  = 'publish' === $status ? __( 'Visible', 'jooservices-resume' ) : __( 'Hidden', 'jooservices-resume' );
				if ( is_array( $public ) && array() !== $public ) {
					/* translators: 1: visibility, 2: number of private fields made public. */
					$label = sprintf( __( '%1$s, %2$d private field(s) public', 'jooservices-resume' ), $label, count( $public ) );
				}
				echo esc_html( $label );
				break;
		}
	}

	/**
	 * Filter dropdown: all, current, past, pinned.
	 */
	public function filter(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-' . Company::TYPE !== $screen->id ) {
			return;
		}
		$selected = isset( $_GET['joo_filter'] ) ? sanitize_key( wp_unslash( (string) $_GET['joo_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		echo '<label for="joo_filter" class="screen-reader-text">' . esc_html__( 'Filter companies', 'jooservices-resume' ) . '</label><select id="joo_filter" name="joo_filter">';
		foreach ( array(
			''        => __( 'All companies', 'jooservices-resume' ),
			'current' => __( 'Current only', 'jooservices-resume' ),
			'past'    => __( 'Past only', 'jooservices-resume' ),
			'pinned'  => __( 'Pinned only', 'jooservices-resume' ),
		) as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $selected, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * Applies the filter to the main admin query.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function applyFilter( $query ): void {
		if ( ! is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() || Company::TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		$filter = isset( $_GET['joo_filter'] ) ? sanitize_key( wp_unslash( (string) $_GET['joo_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$clause = array(
			'current' => array(
				'key'     => '_joo_company_to',
				'value'   => '',
				'compare' => '=',
			),
			'past'    => array(
				'key'     => '_joo_company_to',
				'value'   => '',
				'compare' => '!=',
			),
			'pinned'  => array(
				'key'     => '_joo_company_pinned',
				'value'   => '1',
				'compare' => '=',
			),
		)[ $filter ] ?? null;
		if ( null === $clause ) {
			return;
		}
		$query->set( 'meta_query', array( $clause ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}

	/**
	 * Bulk actions.
	 *
	 * @param array<string, string> $actions Actions.
	 * @return array<string, string>
	 */
	public function bulkActions( $actions ): array {
		$actions['joo_pin']  = __( 'Pin as current company', 'jooservices-resume' );
		$actions['joo_hide'] = __( 'Hide (set to draft)', 'jooservices-resume' );
		$actions['joo_show'] = __( 'Show (publish)', 'jooservices-resume' );
		return $actions;
	}

	/**
	 * Handles the bulk actions with the capability of each post.
	 *
	 * @param string    $redirect Redirect URL.
	 * @param string    $action   Action.
	 * @param list<int> $ids      Post ids.
	 */
	public function handleBulk( $redirect, $action, $ids ): string {
		if ( ! in_array( $action, array( 'joo_pin', 'joo_hide', 'joo_show' ), true ) ) {
			return (string) $redirect;
		}
		$done = 0;
		if ( 'joo_pin' === $action && 1 !== count( $ids ) ) {
			return add_query_arg( 'joo_bulk', 'pin_one', (string) $redirect );
		}
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( Company::TYPE !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			if ( 'joo_pin' === $action ) {
				update_post_meta( $id, '_joo_company_to', '' );
				update_post_meta( $id, '_joo_company_pinned', true );
			} else {
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'joo_hide' === $action ? 'draft' : 'publish',
					)
				);
			}
			++$done;
		}
		return add_query_arg(
			array(
				'joo_bulk' => $action,
				'joo_done' => $done,
			),
			(string) $redirect
		);
	}

	/**
	 * Result message of a bulk action.
	 */
	public function bulkNotice(): void {
		$bulk = isset( $_GET['joo_bulk'] ) ? sanitize_key( wp_unslash( (string) $_GET['joo_bulk'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
		if ( '' === $bulk ) {
			return;
		}
		if ( 'pin_one' === $bulk ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Select exactly one company to pin.', 'jooservices-resume' ) . '</p></div>';
			return;
		}
		$count = isset( $_GET['joo_done'] ) ? absint( wp_unslash( $_GET['joo_done'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
		/* translators: %d: number of companies. */
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%d company updated.', '%d companies updated.', $count, 'jooservices-resume' ), $count ) ) . '</p></div>';
	}
}
