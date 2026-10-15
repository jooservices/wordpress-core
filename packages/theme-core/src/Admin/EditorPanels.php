<?php
/**
 * Declarative post meta and editor sidebar panels.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Admin;

use JOOservices\ThemeCore\Support\Paths;

/**
 * Modules describe a meta field once; this class registers the post meta (REST-visible, capability checked),
 * hands the definitions to the block editor panel script and renders a classic meta box fallback.
 *
 * @internal
 */
final class EditorPanels {

	/**
	 * Allowed field types.
	 */
	private const TYPES = array( 'boolean', 'string', 'integer' );

	/**
	 * Definitions by meta key.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $fields = array();

	/**
	 * Panel titles by panel id.
	 *
	 * @var array<string, string>
	 */
	private array $panels = array();

	/**
	 * Adds a panel title.
	 *
	 * @param string $id    Panel id.
	 * @param string $title Panel title.
	 */
	public function panel( string $id, string $title ): void {
		$this->panels[ $id ] = $title;
	}

	/**
	 * Adds a field definition.
	 *
	 * Required: key (starts with _joo_), type (boolean|string|integer), label, panel.
	 * Optional: enum, default, post_types (default ['post']), sanitize (bool|int|enum|text), maxLength, minimum, maximum, ui (toggle|select|text|textarea|number).
	 *
	 * @param array<string, mixed> $definition Definition.
	 * @throws \InvalidArgumentException For invalid definitions.
	 */
	public function add( array $definition ): void {
		$key = (string) ( $definition['key'] ?? '' );
		if ( 1 !== preg_match( '/^_joo_[a-z0-9_]+$/', $key ) ) {
			throw new \InvalidArgumentException( 'Panel field keys must start with _joo_.' );
		}
		if ( ! in_array( $definition['type'] ?? '', self::TYPES, true ) ) {
			throw new \InvalidArgumentException( sprintf( 'Unsupported field type for %s.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( empty( $definition['label'] ) || empty( $definition['panel'] ) ) {
			throw new \InvalidArgumentException( sprintf( 'Field %s needs a label and a panel.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$definition['post_types'] = (array) ( $definition['post_types'] ?? array( 'post' ) );
		$this->fields[ $key ]     = $definition;
	}

	/**
	 * Registered definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function fields(): array {
		return $this->fields;
	}

	/**
	 * Hooks registration, editor script and classic fallback.
	 */
	public function hook(): void {
		add_action( 'init', array( $this, 'registerMeta' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueueEditor' ) );
		add_action( 'add_meta_boxes', array( $this, 'addClassicBoxes' ) );
		add_action( 'save_post', array( $this, 'saveClassic' ), 10, 2 );
	}

	/**
	 * Registers the post meta.
	 */
	public function registerMeta(): void {
		foreach ( $this->fields as $key => $field ) {
			foreach ( $field['post_types'] as $post_type ) {
				$schema = array( 'type' => $field['type'] );
				if ( isset( $field['enum'] ) ) {
					$schema['enum'] = $field['enum'];
				}
				register_post_meta(
					$post_type,
					$key,
					array(
						'single'            => true,
						'type'              => $field['type'],
						'default'           => $field['default'] ?? ( 'boolean' === $field['type'] ? false : ( 'integer' === $field['type'] ? 0 : '' ) ),
						'show_in_rest'      => array( 'schema' => $schema ),
						'sanitize_callback' => fn( $value ) => $this->sanitize( $field, $value ),
						'auth_callback'     => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', (int) $post_id ),
					)
				);
			}
		}
	}

	/**
	 * Loads the panel script in the block editor.
	 */
	public function enqueueEditor(): void {
		if ( array() === $this->fields ) {
			return;
		}
		$handle = 'joo-editor-panels';
		wp_enqueue_script(
			$handle,
			Paths::url( 'resources/editor/panels.js' ),
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n' ),
			Paths::version( 'resources/editor/panels.js' ),
			true
		);
		wp_add_inline_script( $handle, 'window.jooPanels = ' . wp_json_encode( $this->payload(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ) . ';', 'before' );
	}

	/**
	 * Data handed to the editor script.
	 *
	 * @return list<array{id: string, title: string, fields: list<array<string, mixed>>}>
	 */
	public function payload(): array {
		$out = array();
		foreach ( $this->fields as $field ) {
			$id = (string) $field['panel'];
			if ( ! isset( $out[ $id ] ) ) {
				$out[ $id ] = array(
					'id'     => $id,
					'title'  => $this->panels[ $id ] ?? ucfirst( str_replace( '-', ' ', $id ) ),
					'fields' => array(),
				);
			}
			$out[ $id ]['fields'][] = array(
				'key'       => $field['key'],
				'type'      => $field['type'],
				'label'     => $field['label'],
				'help'      => $field['help'] ?? '',
				'ui'        => $field['ui'] ?? $this->defaultUi( $field ),
				'enum'      => $field['enum'] ?? null,
				'maxLength' => $field['maxLength'] ?? null,
				'min'       => $field['minimum'] ?? null,
				'max'       => $field['maximum'] ?? null,
				'postTypes' => $field['post_types'],
			);
		}
		return array_values( $out );
	}

	/**
	 * Adds classic-editor meta boxes (used only when the block editor is not active for the post).
	 */
	public function addClassicBoxes(): void {
		$by_panel = array();
		foreach ( $this->fields as $field ) {
			$by_panel[ (string) $field['panel'] ][] = $field;
		}
		foreach ( $by_panel as $panel => $fields ) {
			foreach ( array_unique( array_merge( ...array_column( $fields, 'post_types' ) ) ) as $post_type ) {
				add_meta_box(
					'joo-' . $panel,
					$this->panels[ $panel ] ?? ucfirst( $panel ),
					function ( \WP_Post $post ) use ( $fields ): void {
						if ( function_exists( 'use_block_editor_for_post' ) && use_block_editor_for_post( $post ) ) {
							echo '<p>' . esc_html__( 'These options are in the editor sidebar.', 'joo-core' ) . '</p>';
							return;
						}
						wp_nonce_field( 'joo_panels_' . $post->ID, 'joo_panels_nonce' );
						foreach ( $fields as $field ) {
							if ( in_array( $post->post_type, $field['post_types'], true ) ) {
								$this->renderClassicField( $field, $post->ID );
							}
						}
					},
					$post_type,
					'side'
				);
			}
		}
	}

	/**
	 * Saves classic meta box values.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 */
	public function saveClassic( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['joo_panels_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['joo_panels_nonce'] ) ), 'joo_panels_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		foreach ( $this->fields as $key => $field ) {
			if ( ! in_array( $post->post_type, $field['post_types'], true ) ) {
				continue;
			}
			$raw = isset( $_POST['joo_panel'][ $key ] ) ? wp_unslash( $_POST['joo_panel'][ $key ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by the field sanitiser below.
			update_post_meta( $post_id, $key, $this->sanitize( $field, $raw ) );
		}
	}

	/**
	 * Sanitises a value for a field.
	 *
	 * @param array<string, mixed> $field Definition.
	 * @param mixed                $value Raw value.
	 * @return bool|int|string
	 */
	public function sanitize( array $field, $value ) {
		switch ( $field['type'] ) {
			case 'boolean':
				return in_array( $value, array( true, 1, '1', 'true', 'on' ), true );
			case 'integer':
				$number = (int) $value;
				if ( isset( $field['minimum'] ) ) {
					$number = max( (int) $field['minimum'], $number );
				}
				if ( isset( $field['maximum'] ) ) {
					$number = min( (int) $field['maximum'], $number );
				}
				return $number;
			default:
				$text = is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
				if ( isset( $field['enum'] ) ) {
					return in_array( $text, $field['enum'], true ) ? $text : (string) ( $field['default'] ?? $field['enum'][0] );
				}
				if ( isset( $field['maxLength'] ) ) {
					$text = mb_substr( $text, 0, (int) $field['maxLength'] );
				}
				return $text;
		}
	}

	/**
	 * Default control for a field.
	 *
	 * @param array<string, mixed> $field Definition.
	 */
	private function defaultUi( array $field ): string {
		if ( 'boolean' === $field['type'] ) {
			return 'toggle';
		}
		if ( isset( $field['enum'] ) ) {
			return 'select';
		}
		return 'integer' === $field['type'] ? 'number' : 'text';
	}

	/**
	 * Renders one classic field.
	 *
	 * @param array<string, mixed> $field   Definition.
	 * @param int                  $post_id Post id.
	 */
	private function renderClassicField( array $field, int $post_id ): void {
		$key   = (string) $field['key'];
		$value = get_post_meta( $post_id, $key, true );
		$name  = 'joo_panel[' . $key . ']';
		$id    = 'joo-' . sanitize_html_class( $key );
		echo '<p><label for="' . esc_attr( $id ) . '"><strong>' . esc_html( (string) $field['label'] ) . '</strong></label><br>';
		$ui = $field['ui'] ?? $this->defaultUi( $field );
		if ( 'toggle' === $ui ) {
			echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $value, true, false ) . '>';
		} elseif ( 'select' === $ui ) {
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( (array) $field['enum'] as $option ) {
				echo '<option value="' . esc_attr( (string) $option ) . '"' . selected( (string) $value, (string) $option, false ) . '>' . esc_html( (string) $option ) . '</option>';
			}
			echo '</select>';
		} elseif ( 'textarea' === $ui ) {
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3" class="widefat">' . esc_textarea( (string) $value ) . '</textarea>';
		} else {
			echo '<input type="' . ( 'number' === $ui ? 'number' : 'text' ) . '" class="widefat" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		}
		if ( ! empty( $field['help'] ) ) {
			echo '<br><span class="description">' . esc_html( (string) $field['help'] ) . '</span>';
		}
		echo '</p>';
	}
}
