<?php
/**
 * Company custom post type.
 *
 * @package JOOservices\Content
 */

declare(strict_types=1);

namespace JOOservices\Content\PostTypes;

use JOOservices\Content\Support\Validate;

/**
 * Employers with role, period, short description, URL, logo and a single pinned "current" company.
 */
final class Company {

	public const TYPE  = 'joo_company';
	public const NONCE = 'joo_company_details';

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		$this->register();
		add_filter( 'rest_pre_insert_' . self::TYPE, array( $this, 'validateRest' ), 10, 2 );
		add_action( 'add_meta_boxes_' . self::TYPE, array( $this, 'addMetaBox' ) );
		add_action( 'add_meta_boxes_' . self::TYPE, array( $this, 'removeCustomFieldsBox' ), 20 );
		add_action( 'template_redirect', array( $this, 'blockPublicAccess' ), 0 );
		add_action( 'save_post_' . self::TYPE, array( $this, 'saveClassic' ), 10, 2 );
		add_action( 'added_post_meta', array( $this, 'enforceSinglePinned' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'enforceSinglePinned' ), 10, 4 );
		add_filter( 'manage_' . self::TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . self::TYPE . '_sortable_columns', array( $this, 'sortable' ) );
		add_action( 'pre_get_posts', array( $this, 'adminOrder' ) );
		add_filter( 'post_row_actions', array( $this, 'rowActions' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/**
	 * Registers the post type and its meta.
	 */
	public function register(): void {
		register_post_type(
			self::TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Companies', 'jooservices-content' ),
					'singular_name' => __( 'Company', 'jooservices-content' ),
					'add_new_item'  => __( 'Add new company', 'jooservices-content' ),
					'edit_item'     => __( 'Edit company', 'jooservices-content' ),
					'menu_name'     => __( 'Companies', 'jooservices-content' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'rest_base'           => 'joo-companies',
				'supports'            => array( 'title', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields' ),
				'menu_icon'           => 'dashicons-building',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'exclude_from_search' => true,
			)
		);
		$auth = static fn( $allowed, $key, $postId ) => current_user_can( 'edit_post', (int) $postId );
		$meta = array(
			'_joo_company_role'    => array(
				'type'              => 'string',
				'schema'            => array( 'maxLength' => 80 ),
				'sanitize_callback' => static fn( $v ) => mb_substr( sanitize_text_field( (string) $v ), 0, 80 ),
			),
			'_joo_company_from'    => array(
				'type'              => 'string',
				'schema'            => array( 'pattern' => '^(\d{4}-(0[1-9]|1[0-2]))?$' ),
				'sanitize_callback' => array( Validate::class, 'cleanMonth' ),
			),
			'_joo_company_to'      => array(
				'type'              => 'string',
				'schema'            => array( 'pattern' => '^(\d{4}-(0[1-9]|1[0-2]))?$' ),
				'sanitize_callback' => array( Validate::class, 'cleanMonth' ),
			),
			'_joo_company_pinned'  => array(
				'type'              => 'boolean',
				'sanitize_callback' => static fn( $v ): bool => filter_var( $v, FILTER_VALIDATE_BOOLEAN ),
			),
			'_joo_company_url'     => array(
				'type'              => 'string',
				'schema'            => array( 'pattern' => '^(https://.*)?$' ),
				'sanitize_callback' => array( Validate::class, 'httpsUrl' ),
			),
			'_joo_company_logo_id' => array(
				'type'              => 'integer',
				'sanitize_callback' => array( Validate::class, 'imageId' ),
			),
		);
		foreach ( $meta as $key => $def ) {
			register_post_meta(
				self::TYPE,
				$key,
				array(
					'single'            => true,
					'type'              => $def['type'],
					'show_in_rest'      => array( 'schema' => array_merge( array( 'type' => $def['type'] ), $def['schema'] ?? array() ) ),
					'sanitize_callback' => $def['sanitize_callback'],
					'auth_callback'     => $auth,
				)
			);
		}
	}

	/**
	 * REST: rejects a period that ends before it starts and publishing without role or start month.
	 *
	 * @param \stdClass        $prepared Prepared post.
	 * @param \WP_REST_Request $request  Request.
	 * @return \stdClass|\WP_Error
	 */
	public function validateRest( $prepared, $request ) {
		$meta = $request->get_param( 'meta' );
		$meta = is_array( $meta ) ? $meta : array();
		$id   = isset( $prepared->ID ) ? (int) $prepared->ID : 0;
		$from = (string) ( $meta['_joo_company_from'] ?? ( $id > 0 ? get_post_meta( $id, '_joo_company_from', true ) : '' ) );
		$to   = (string) ( $meta['_joo_company_to'] ?? ( $id > 0 ? get_post_meta( $id, '_joo_company_to', true ) : '' ) );
		if ( ! Validate::periodIsValid( $from, $to ) ) {
			return new \WP_Error( 'rest_invalid_param', __( 'The end month cannot be before the start month.', 'jooservices-content' ), array( 'status' => 400 ) );
		}
		$status = (string) ( $prepared->post_status ?? '' );
		if ( 'publish' === $status ) {
			$role = (string) ( $meta['_joo_company_role'] ?? ( $id > 0 ? get_post_meta( $id, '_joo_company_role', true ) : '' ) );
			if ( '' === trim( $role ) || '' === $from ) {
				return new \WP_Error( 'rest_invalid_param', __( 'A published company needs a role and a start month.', 'jooservices-content' ), array( 'status' => 400 ) );
			}
		}
		return $prepared;
	}

	/**
	 * Setting pinned on one company unsets it on all the others.
	 *
	 * @param int    $metaId   Meta id.
	 * @param int    $postId   Post id.
	 * @param string $metaKey  Meta key.
	 * @param mixed  $metaValue Value.
	 */
	public function enforceSinglePinned( $metaId, $postId, $metaKey, $metaValue ): void {
		unset( $metaId );
		if ( '_joo_company_pinned' !== $metaKey || ! filter_var( $metaValue, FILTER_VALIDATE_BOOLEAN ) || self::TYPE !== get_post_type( (int) $postId ) ) {
			return;
		}
		$others = get_posts(
			array(
				'post_type'        => self::TYPE,
				'post_status'      => 'any',
				'posts_per_page'   => 50, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded: normally exactly one.
				'post__not_in'     => array( (int) $postId ),
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_joo_company_pinned',
						'value' => '1',
					),
				),
			)
		);
		foreach ( $others as $other ) {
			update_post_meta( (int) $other, '_joo_company_pinned', false );
		}
	}

	/**
	 * The generic Custom Fields box would show raw meta: hide it.
	 */
	public function removeCustomFieldsBox(): void {
		remove_meta_box( 'postcustom', self::TYPE, 'normal' );
	}

	/**
	 * `?post_type=joo_company` on the front end is a 404 (WordPress would otherwise show the blog home).
	 */
	public function blockPublicAccess(): void {
		if ( is_admin() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only request check.
		$asked = isset( $_GET['post_type'] ) && self::TYPE === sanitize_key( wp_unslash( (string) $_GET['post_type'] ) );
		if ( ! $asked && ! is_singular( self::TYPE ) ) {
			return;
		}
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Adds the details box (classic fields, no JavaScript needed).
	 */
	public function addMetaBox(): void {
		add_meta_box( 'joo_company_details', __( 'Company details', 'jooservices-content' ), array( $this, 'renderMetaBox' ), self::TYPE, 'normal', 'high' );
	}

	/**
	 * Renders the box.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function renderMetaBox( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		$v    = static fn( string $key ) => (string) get_post_meta( $post->ID, $key, true );
		echo '<table class="form-table" role="presentation"><tbody>';
		$rows = array(
			array( '_joo_company_role', __( 'Role', 'jooservices-content' ), 'text', '' ),
			array( '_joo_company_from', __( 'From (YYYY-MM)', 'jooservices-content' ), 'text', 'pattern="\d{4}-(0[1-9]|1[0-2])" placeholder="2022-03"' ),
			array( '_joo_company_to', __( 'To (YYYY-MM, empty = present)', 'jooservices-content' ), 'text', 'pattern="(\d{4}-(0[1-9]|1[0-2]))?" placeholder="2025-06"' ),
			array( '_joo_company_url', __( 'Website (https)', 'jooservices-content' ), 'url', 'placeholder="https://"' ),
			array( '_joo_company_logo_id', __( 'Logo (attachment id)', 'jooservices-content' ), 'number', 'min="0"' ),
		);
		foreach ( $rows as $row ) {
			printf(
				'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="%3$s" class="regular-text" id="%1$s" name="%1$s" value="%4$s" %5$s></td></tr>',
				esc_attr( $row[0] ),
				esc_html( $row[1] ),
				esc_attr( $row[2] ),
				esc_attr( $v( $row[0] ) ),
				$row[3] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute strings defined above.
			);
		}
		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="_joo_company_pinned" value="1" %2$s> %3$s</label></td></tr>',
			esc_html__( 'Pinned', 'jooservices-content' ),
			checked( (bool) $v( '_joo_company_pinned' ), true, false ),
			esc_html__( 'Show as the current company (only one company can be pinned)', 'jooservices-content' )
		);
		echo '</tbody></table>';
	}

	/**
	 * Saves the classic form with nonce and capability checks.
	 *
	 * @param int      $postId Post id.
	 * @param \WP_Post $post   Post.
	 */
	public function saveClassic( int $postId, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		$nonce = isset( $_POST[ self::NONCE . '_nonce' ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE . '_nonce' ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE ) || ! current_user_can( 'edit_post', $postId ) ) {
			return;
		}
		$input = static fn( string $key ): string => isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$from  = $input( '_joo_company_from' );
		$to    = $input( '_joo_company_to' );
		$error = array();
		if ( '' !== $from && ! Validate::month( $from ) ) {
			$error[] = __( 'The start month must look like 2022-03.', 'jooservices-content' );
			$from    = '';
		}
		if ( '' !== $to && ! Validate::month( $to ) ) {
			$error[] = __( 'The end month must look like 2025-06.', 'jooservices-content' );
			$to      = '';
		}
		if ( ! Validate::periodIsValid( $from, $to ) ) {
			$error[] = __( 'The end month cannot be before the start month.', 'jooservices-content' );
			$to      = '';
		}
		$url = $input( '_joo_company_url' );
		if ( '' !== $url && '' === Validate::httpsUrl( $url ) ) {
			$error[] = __( 'The website must be an https URL.', 'jooservices-content' );
			$url     = '';
		}
		update_post_meta( $postId, '_joo_company_role', $input( '_joo_company_role' ) );
		update_post_meta( $postId, '_joo_company_from', $from );
		update_post_meta( $postId, '_joo_company_to', $to );
		update_post_meta( $postId, '_joo_company_url', $url );
		update_post_meta( $postId, '_joo_company_logo_id', absint( $input( '_joo_company_logo_id' ) ) );
		update_post_meta( $postId, '_joo_company_pinned', isset( $_POST['_joo_company_pinned'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		if ( isset( $_POST['_joo_company_pinned'] ) && '' !== $to ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$error[] = __( 'A pinned company normally has no end month (it is the current one). It was saved anyway.', 'jooservices-content' );
		}
		unset( $post );
		if ( array() !== $error ) {
			set_transient( 'joo_company_errors_' . get_current_user_id(), $error, 60 );
		}
	}

	/**
	 * Shows messages collected during the last save.
	 */
	public function notices(): void {
		$key    = 'joo_company_errors_' . get_current_user_id();
		$errors = get_transient( $key );
		if ( ! is_array( $errors ) || array() === $errors ) {
			return;
		}
		delete_transient( $key );
		foreach ( $errors as $message ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( (string) $message ) . '</p></div>';
		}
	}

	/**
	 * List columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( $columns ): array {
		return array(
			'cb'         => $columns['cb'] ?? '',
			'title'      => __( 'Company', 'jooservices-content' ),
			'role'       => __( 'Role', 'jooservices-content' ),
			'period'     => __( 'Period', 'jooservices-content' ),
			'pinned'     => __( 'Pinned', 'jooservices-content' ),
			'menu_order' => __( 'Order', 'jooservices-content' ),
		);
	}

	/**
	 * Column output.
	 *
	 * @param string $column Column.
	 * @param int    $postId Post id.
	 */
	public function column( $column, $postId ): void {
		$postId = (int) $postId;
		switch ( $column ) {
			case 'role':
				echo esc_html( (string) get_post_meta( $postId, '_joo_company_role', true ) );
				break;
			case 'period':
				echo esc_html( Validate::period( (string) get_post_meta( $postId, '_joo_company_from', true ), (string) get_post_meta( $postId, '_joo_company_to', true ) ) );
				break;
			case 'pinned':
				echo (bool) get_post_meta( $postId, '_joo_company_pinned', true ) ? '<span aria-label="' . esc_attr__( 'Pinned', 'jooservices-content' ) . '">★</span>' : '';
				break;
			case 'menu_order':
				echo (int) get_post_field( 'menu_order', $postId ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- integer.
				break;
		}
	}

	/**
	 * Sortable columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function sortable( $columns ): array {
		$columns['period']     = 'joo_from';
		$columns['menu_order'] = 'menu_order';
		return $columns;
	}

	/**
	 * Admin list: pinned first, then the start month, newest first (unless sorted explicitly).
	 *
	 * @param \WP_Query $query Query.
	 */
	public function adminOrder( $query ): void {
		if ( ! is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() || self::TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		$orderby = (string) $query->get( 'orderby' );
		if ( 'joo_from' === $orderby ) {
			$query->set( 'meta_key', '_joo_company_from' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query->set( 'orderby', 'meta_value' );
		} elseif ( '' === $orderby ) {
			$query->set(
				'meta_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'relation' => 'OR',
					'pinned'   => array(
						'key'     => '_joo_company_pinned',
						'compare' => 'EXISTS',
					),
					'nopin'    => array(
						'key'     => '_joo_company_pinned',
						'compare' => 'NOT EXISTS',
					),
				)
			);
			$query->set(
				'orderby',
				array(
					'pinned' => 'DESC',
					'date'   => 'DESC',
				)
			);
		}
	}

	/**
	 * No quick edit (its form would drop the meta).
	 *
	 * @param array<string, string> $actions Actions.
	 * @param \WP_Post              $post    Post.
	 * @return array<string, string>
	 */
	public function rowActions( $actions, $post ): array {
		if ( $post instanceof \WP_Post && self::TYPE === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}
}
