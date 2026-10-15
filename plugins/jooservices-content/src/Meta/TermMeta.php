<?php
/**
 * Category and tag meta.
 *
 * @package JOOservices\Content
 */

declare(strict_types=1);

namespace JOOservices\Content\Meta;

use JOOservices\Content\Support\Validate;

/**
 * Short description, topic image and intro for categories and tags.
 */
final class TermMeta {

	public const NONCE      = 'joo_term_meta';
	public const TAXONOMIES = array( 'category', 'post_tag' );

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		$this->register();
		foreach ( self::TAXONOMIES as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', array( $this, 'renderAdd' ) );
			add_action( $taxonomy . '_edit_form_fields', array( $this, 'renderEdit' ) );
			add_action( 'created_' . $taxonomy, array( $this, 'save' ) );
			add_action( 'edited_' . $taxonomy, array( $this, 'save' ) );
			add_filter( 'manage_edit-' . $taxonomy . '_columns', array( $this, 'columns' ) );
			add_filter( 'manage_' . $taxonomy . '_custom_column', array( $this, 'column' ), 10, 3 );
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Registers the term meta.
	 */
	public function register(): void {
		$auth   = static fn( $allowed, $key, $termId ) => current_user_can( 'edit_term', (int) $termId );
		$fields = array(
			'joo_short_description' => array(
				'type'     => 'string',
				'schema'   => array( 'maxLength' => 160 ),
				'sanitize' => static fn( $v ) => mb_substr( sanitize_text_field( (string) $v ), 0, 160 ),
			),
			'joo_image_id'          => array(
				'type'     => 'integer',
				'schema'   => array(),
				'sanitize' => array( Validate::class, 'imageId' ),
			),
			'joo_intro'             => array(
				'type'     => 'string',
				'schema'   => array( 'maxLength' => 200 ),
				'sanitize' => static fn( $v ) => mb_substr( sanitize_text_field( (string) $v ), 0, 200 ),
			),
			'joo_icon'              => array(
				'type'     => 'string',
				'schema'   => array( 'maxLength' => 40 ),
				'sanitize' => static fn( $v ) => sanitize_key( (string) $v ),
			),
		);
		foreach ( self::TAXONOMIES as $taxonomy ) {
			foreach ( $fields as $key => $def ) {
				register_term_meta(
					$taxonomy,
					$key,
					array(
						'single'            => true,
						'type'              => $def['type'],
						'show_in_rest'      => array( 'schema' => array_merge( array( 'type' => $def['type'] ), $def['schema'] ) ),
						'sanitize_callback' => $def['sanitize'],
						'auth_callback'     => $auth,
					)
				);
			}
		}
	}

	/**
	 * Fields on the "add term" form.
	 */
	public function renderAdd(): void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		foreach ( $this->definitions() as $key => $def ) {
			echo '<div class="form-field"><label for="' . esc_attr( $key ) . '">' . esc_html( $def['label'] ) . '</label>';
			$this->input( $key, '', $def );
			echo '<p>' . esc_html( $def['help'] ) . '</p></div>';
		}
	}

	/**
	 * Fields on the "edit term" form.
	 *
	 * @param \WP_Term $term Term.
	 */
	public function renderEdit( \WP_Term $term ): void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		foreach ( $this->definitions() as $key => $def ) {
			echo '<tr class="form-field"><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $def['label'] ) . '</label></th><td>';
			$this->input( $key, (string) get_term_meta( $term->term_id, $key, true ), $def );
			echo '<p class="description">' . esc_html( $def['help'] ) . '</p></td></tr>';
		}
	}

	/**
	 * Field definitions for the forms.
	 *
	 * @return array<string, array{label: string, help: string, type: string, max: int}>
	 */
	private function definitions(): array {
		return array(
			'joo_short_description' => array(
				'label' => __( 'Short description', 'jooservices-content' ),
				'help'  => __( 'Up to 160 characters. Shown in the category tree and used as the archive meta description.', 'jooservices-content' ),
				'type'  => 'text',
				'max'   => 160,
			),
			'joo_image_id'          => array(
				'label' => __( 'Topic image (attachment id)', 'jooservices-content' ),
				'help'  => __( 'A small image for the Top topics block. Use the button to pick one from the media library.', 'jooservices-content' ),
				'type'  => 'media',
				'max'   => 0,
			),
			'joo_intro'             => array(
				'label' => __( 'Topic intro', 'jooservices-content' ),
				'help'  => __( 'Up to 200 characters shown next to the topic image.', 'jooservices-content' ),
				'type'  => 'text',
				'max'   => 200,
			),
		);
	}

	/**
	 * Prints one input.
	 *
	 * @param string                                                          $key   Meta key.
	 * @param string                                                          $value Current value.
	 * @param array{label: string, help: string, type: string, max: int} $def   Definition.
	 */
	private function input( string $key, string $value, array $def ): void {
		if ( 'media' === $def['type'] ) {
			printf(
				'<input type="number" min="0" id="%1$s" name="%1$s" value="%2$s" class="small-text" data-joo-media> <button type="button" class="button" data-joo-media-pick="%1$s">%3$s</button>',
				esc_attr( $key ),
				esc_attr( $value ),
				esc_html__( 'Choose image', 'jooservices-content' )
			);
			return;
		}
		printf( '<input type="text" id="%1$s" name="%1$s" value="%2$s" maxlength="%3$d" class="large-text">', esc_attr( $key ), esc_attr( $value ), (int) $def['max'] );
	}

	/**
	 * Saves the fields (nonce, capability, sanitising; an attachment that is not an image is rejected).
	 *
	 * @param int $termId Term id.
	 */
	public function save( $termId ): void {
		$termId = (int) $termId;
		$nonce  = isset( $_POST[ self::NONCE . '_nonce' ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE . '_nonce' ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE ) || ! current_user_can( 'edit_term', $termId ) ) {
			return;
		}
		$short = isset( $_POST['joo_short_description'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['joo_short_description'] ) ) : '';
		$intro = isset( $_POST['joo_intro'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['joo_intro'] ) ) : '';
		$image = isset( $_POST['joo_image_id'] ) ? Validate::imageId( wp_unslash( (string) $_POST['joo_image_id'] ) ) : 0;
		update_term_meta( $termId, 'joo_short_description', mb_substr( $short, 0, 160 ) );
		update_term_meta( $termId, 'joo_intro', mb_substr( $intro, 0, 200 ) );
		update_term_meta( $termId, 'joo_image_id', $image );
	}

	/**
	 * Adds the list columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( $columns ): array {
		$columns['joo_image'] = __( 'Topic image', 'jooservices-content' );
		$columns['joo_short'] = __( 'Short description', 'jooservices-content' );
		return $columns;
	}

	/**
	 * Column output.
	 *
	 * @param string $content Existing content.
	 * @param string $column  Column.
	 * @param int    $termId  Term id.
	 */
	public function column( $content, $column, $termId ): string {
		$termId = (int) $termId;
		if ( 'joo_image' === $column ) {
			$id = (int) get_term_meta( $termId, 'joo_image_id', true );
			return $id > 0 ? (string) wp_get_attachment_image( $id, array( 32, 32 ) ) : '';
		}
		if ( 'joo_short' === $column ) {
			return esc_html( wp_html_excerpt( (string) get_term_meta( $termId, 'joo_short_description', true ), 60, '…' ) );
		}
		return (string) $content;
	}

	/**
	 * Media picker script on the term screens.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function assets( $hook ): void {
		if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'joo-content-term-meta', plugins_url( 'assets/term-meta.js', JOO_CONTENT_FILE ), array( 'jquery' ), \JOOservices\Content\Plugin::VERSION, true );
	}
}
