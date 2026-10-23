<?php
/**
 * Built-in language tagging (no multilingual plugin needed).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\I18n;

use JOOservices\ThemeCore\Seo\Context;

/**
 * Validates `_joo_lang`, adds the admin column, filter and bulk action, exposes REST fields and keeps translation
 * groups consistent when a root post is trashed.
 *
 * @internal
 */
final class LanguageTagging {

	/**
	 * Languages.
	 *
	 * @var Languages
	 */
	private Languages $languages;

	/**
	 * Translations.
	 *
	 * @var Translations
	 */
	private Translations $translations;

	/**
	 * Creates the service.
	 *
	 * @param Languages    $languages    Languages.
	 * @param Translations $translations Translations.
	 */
	public function __construct( Languages $languages, Translations $translations ) {
		$this->languages    = $languages;
		$this->translations = $translations;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_filter( 'sanitize_post_meta__joo_lang', array( $this, 'sanitizeLanguage' ) );
		add_action( 'trashed_post', array( $this->translations, 'promoteAfterTrash' ) );
		add_action( 'rest_api_init', array( $this, 'registerRestFields' ) );
		if ( ! is_admin() || ! $this->languages->isMultilingual() ) {
			return;
		}
		add_filter( 'manage_post_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_post_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'filterDropdown' ) );
		add_action( 'pre_get_posts', array( $this, 'filterQuery' ) );
		add_filter( 'bulk_actions-edit-post', array( $this, 'bulkActions' ) );
		add_filter( 'handle_bulk_actions-edit-post', array( $this, 'handleBulk' ), 10, 3 );
	}

	/**
	 * Keeps only configured languages.
	 *
	 * @param mixed $value Raw value.
	 */
	public function sanitizeLanguage( $value ): string {
		$code = Context::normaliseLanguage( sanitize_text_field( (string) $value ) );
		return '' !== (string) $value && in_array( $code, $this->languages->codes(), true ) ? $code : '';
	}

	/**
	 * REST fields `joo_lang` and `joo_translations`.
	 */
	public function registerRestFields(): void {
		register_rest_field(
			'post',
			'joo_lang',
			array(
				'get_callback' => static fn( $post ) => (string) get_post_meta( (int) ( $post['id'] ?? 0 ), '_joo_lang', true ),
				'schema'       => array(
					'type'        => 'string',
					'description' => __( 'Language of the post.', 'joo-core' ),
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			)
		);
		register_rest_field(
			'post',
			'joo_translations',
			array(
				'get_callback' => function ( $post ): array {
					$out = array();
					foreach ( $this->translations->forPost( (int) ( $post['id'] ?? 0 ) ) as $lang => $id ) {
						$out[ $lang ] = array(
							'id'  => $id,
							'url' => (string) get_permalink( $id ),
						);
					}
					return $out;
				},
				'schema'       => array(
					'type'        => 'object',
					'description' => __( 'Published translations by language.', 'joo-core' ),
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			)
		);
	}

	/**
	 * "Lang" column.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( $columns ): array {
		$columns['joo_lang'] = __( 'Lang', 'joo-core' );
		return $columns;
	}

	/**
	 * Column output.
	 *
	 * @param string $column Column.
	 * @param int    $postId Post id.
	 */
	public function column( $column, $postId ): void {
		if ( 'joo_lang' === $column ) {
			echo esc_html( strtoupper( (string) get_post_meta( (int) $postId, '_joo_lang', true ) ) );
		}
	}

	/**
	 * Language filter dropdown.
	 *
	 * @param string $postType Post type.
	 */
	public function filterDropdown( $postType ): void {
		if ( 'post' !== $postType ) {
			return;
		}
		$current = isset( $_GET['joo_lang'] ) ? sanitize_key( wp_unslash( (string) $_GET['joo_lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<select name="joo_lang"><option value="">' . esc_html__( 'All languages', 'joo-core' ) . '</option>';
		foreach ( $this->languages->all() as $language ) {
			echo '<option value="' . esc_attr( $language->code ) . '"' . selected( strtolower( $current ), strtolower( $language->code ), false ) . '>' . esc_html( $language->name ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Applies the language filter to the admin list.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function filterQuery( $query ): void {
		if ( ! is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() || empty( $_GET['joo_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$code = Context::normaliseLanguage( sanitize_key( wp_unslash( (string) $_GET['joo_lang'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $code, $this->languages->codes(), true ) ) {
			$query->set(
				'meta_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					array(
						'key'   => '_joo_lang',
						'value' => $code,
					),
				)
			);
		}
	}

	/**
	 * "Set language" bulk actions.
	 *
	 * @param array<string, string> $actions Actions.
	 * @return array<string, string>
	 */
	public function bulkActions( $actions ): array {
		if ( current_user_can( 'edit_others_posts' ) ) {
			foreach ( $this->languages->all() as $language ) {
				/* translators: %s: language name. */
				$actions[ 'joo_lang_' . $language->code ] = sprintf( __( 'Set language: %s', 'joo-core' ), $language->name );
			}
		}
		return $actions;
	}

	/**
	 * Applies the bulk action (WordPress checked the nonce; the capability is checked again).
	 *
	 * @param string    $redirect Redirect URL.
	 * @param string    $action   Action.
	 * @param list<int> $postIds  Posts.
	 */
	public function handleBulk( $redirect, $action, $postIds ): string {
		if ( 0 !== strpos( (string) $action, 'joo_lang_' ) || ! current_user_can( 'edit_others_posts' ) ) {
			return (string) $redirect;
		}
		$code = substr( (string) $action, 9 );
		if ( in_array( $code, $this->languages->codes(), true ) ) {
			foreach ( (array) $postIds as $id ) {
				if ( current_user_can( 'edit_post', (int) $id ) ) {
					update_post_meta( (int) $id, '_joo_lang', $code );
				}
			}
		}
		return (string) $redirect;
	}
}
