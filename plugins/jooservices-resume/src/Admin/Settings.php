<?php
/**
 * Resume settings: display defaults and the custom-field builder.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Admin;

use JOOservices\Resume\Model\CompanyRepository;
use JOOservices\Resume\Modules\CompanyDataModel;
use JOOservices\Resume\PostTypes\Company;

/**
 * `Companies > Settings`. Capability `manage_options`; every save has a nonce.
 */
final class Settings {

	public const SLUG   = 'joo-resume-settings';
	public const HOOK   = 'joo_company_page_joo-resume-settings';
	public const OPTION = 'joo_resume_settings';
	public const NONCE  = 'joo_resume_save_settings';

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_joo_resume_save_settings', array( $this, 'save' ) );
	}

	/**
	 * Display defaults.
	 *
	 * @return array{date_format: string, show_duration: bool, show_private_labels: bool}
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return array(
			'date_format'         => in_array( $stored['date_format'] ?? '', array( 'month_year', 'iso' ), true ) ? (string) $stored['date_format'] : 'month_year',
			'show_duration'       => (bool) ( $stored['show_duration'] ?? true ),
			'show_private_labels' => (bool) ( $stored['show_private_labels'] ?? false ),
		);
	}

	/**
	 * Adds the submenu page.
	 */
	public function menu(): void {
		add_submenu_page( 'edit.php?post_type=' . Company::TYPE, __( 'Resume settings', 'jooservices-resume' ), __( 'Settings', 'jooservices-resume' ), 'manage_options', self::SLUG, array( $this, 'render' ) );
	}

	/**
	 * Renders the page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'jooservices-resume' ), '', array( 'response' => 403 ) );
		}
		$settings = self::get();
		$defs     = ( new CompanyRepository() )->definitions();
		$errors   = get_transient( 'joo_resume_settings_errors_' . get_current_user_id() );
		delete_transient( 'joo_resume_settings_errors_' . get_current_user_id() );
		echo '<div class="wrap"><h1>' . esc_html__( 'Resume settings', 'jooservices-resume' ) . '</h1>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice flag.
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'jooservices-resume' ) . '</p></div>';
		}
		if ( is_array( $errors ) ) {
			foreach ( $errors as $message ) {
				echo '<div class="notice notice-error"><p>' . esc_html( (string) $message ) . '</p></div>';
			}
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="joo_resume_save_settings">';
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		echo '<h2>' . esc_html__( 'Display defaults', 'jooservices-resume' ) . '</h2><table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="joo_date_format">' . esc_html__( 'Date format', 'jooservices-resume' ) . '</label></th><td><select id="joo_date_format" name="date_format">';
		foreach ( array(
			'month_year' => __( 'March 2022', 'jooservices-resume' ),
			'iso'        => __( '2022-03', 'jooservices-resume' ),
		) as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $settings['date_format'], $value, false ), esc_html( $label ) );
		}
		echo '</select></td></tr>';
		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="show_duration" value="1" %2$s> %3$s</label><br><label><input type="checkbox" name="show_private_labels" value="1" %4$s> %5$s</label></td></tr>',
			esc_html__( 'Display', 'jooservices-resume' ),
			checked( $settings['show_duration'], true, false ),
			esc_html__( 'Show the duration (for example 2 yrs 3 mos)', 'jooservices-resume' ),
			checked( $settings['show_private_labels'], true, false ),
			esc_html__( 'Mark private fields with a label in the editor', 'jooservices-resume' )
		);
		echo '</tbody></table>';
		echo '<h2>' . esc_html__( 'Custom fields', 'jooservices-resume' ) . '</h2><p class="joo-help">' . esc_html__( 'Fields you add here appear on every company. Private fields never reach visitors.', 'jooservices-resume' ) . '</p>';
		echo '<div class="joo-repeater" data-joo-repeater="joo_def" data-noun="Field"><div class="joo-repeater__rows" role="list">';
		foreach ( array_values( $defs ) as $i => $def ) {
			$this->definitionRow( (string) $i, $def );
		}
		echo '</div><template>';
		$this->definitionRow( '__INDEX__', array() );
		echo '</template><p><button type="button" class="button" data-joo-add>' . esc_html__( 'Add field', 'jooservices-resume' ) . '</button></p><p class="screen-reader-text" role="status" aria-live="polite" data-joo-live></p></div>';
		$this->preview( $defs );
		submit_button( __( 'Save settings', 'jooservices-resume' ) );
		echo '</form></div>';
	}

	/**
	 * One definition row.
	 *
	 * @param string               $index Row index.
	 * @param array<string, mixed> $def   Definition.
	 */
	private function definitionRow( string $index, array $def ): void {
		$id    = 'joo_def_' . $index . '_';
		$types = array(
			'text'   => __( 'Text', 'jooservices-resume' ),
			'number' => __( 'Number', 'jooservices-resume' ),
			'date'   => __( 'Date', 'jooservices-resume' ),
			'url'    => __( 'URL', 'jooservices-resume' ),
			'list'   => __( 'List', 'jooservices-resume' ),
		);
		echo '<fieldset class="joo-repeater__row" role="listitem" data-joo-row><legend><span data-joo-title>' . esc_html__( 'Field', 'jooservices-resume' ) . '</span></legend>';
		printf( '<p class="joo-field"><label for="%1$slabel">%2$s</label><input type="text" class="widefat" id="%1$slabel" name="joo_def[%3$s][label]" value="%4$s" maxlength="60"></p>', esc_attr( $id ), esc_html__( 'Label', 'jooservices-resume' ), esc_attr( $index ), esc_attr( (string) ( $def['label'] ?? '' ) ) );
		printf( '<p class="joo-field"><label for="%1$skey">%2$s</label><input type="text" class="widefat" id="%1$skey" name="joo_def[%3$s][key]" value="%4$s" pattern="[a-z][a-z0-9_]{1,29}" maxlength="30"><span class="joo-help">%5$s</span></p>', esc_attr( $id ), esc_html__( 'Key', 'jooservices-resume' ), esc_attr( $index ), esc_attr( (string) ( $def['key'] ?? '' ) ), esc_html__( 'Lowercase letters, digits and underscores. Never change a key that already holds data.', 'jooservices-resume' ) );
		printf( '<p class="joo-field"><label for="%1$stype">%2$s</label><select id="%1$stype" name="joo_def[%3$s][type]">', esc_attr( $id ), esc_html__( 'Type', 'jooservices-resume' ), esc_attr( $index ) );
		foreach ( $types as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( (string) ( $def['type'] ?? 'text' ), $value, false ), esc_html( $label ) );
		}
		echo '</select></p>';
		printf( '<p class="joo-field joo-field--inline"><label><input type="checkbox" id="%1$spublic" name="joo_def[%2$s][public]" value="1" %3$s> %4$s</label></p>', esc_attr( $id ), esc_attr( $index ), checked( ! empty( $def['public'] ), true, false ), esc_html__( 'Show to visitors', 'jooservices-resume' ) );
		printf(
			'<p class="joo-repeater__actions"><button type="button" class="button" data-joo-up>%1$s</button> <button type="button" class="button" data-joo-down>%2$s</button> <button type="button" class="button-link-delete joo-remove" data-joo-remove>%3$s</button></p></fieldset>',
			esc_html__( 'Move field up', 'jooservices-resume' ),
			esc_html__( 'Move field down', 'jooservices-resume' ),
			esc_html__( 'Remove field', 'jooservices-resume' )
		);
	}

	/**
	 * Preview of the fields as the editor and the front end see them.
	 *
	 * @param list<array<string, mixed>> $defs Definitions.
	 */
	private function preview( array $defs ): void {
		echo '<h3>' . esc_html__( 'Preview', 'jooservices-resume' ) . '</h3>';
		if ( array() === $defs ) {
			echo '<p>' . esc_html__( 'No custom fields yet.', 'jooservices-resume' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped" style="max-width:640px"><thead><tr><th scope="col">' . esc_html__( 'Label', 'jooservices-resume' ) . '</th><th scope="col">' . esc_html__( 'Type', 'jooservices-resume' ) . '</th><th scope="col">' . esc_html__( 'Visibility', 'jooservices-resume' ) . '</th></tr></thead><tbody>';
		foreach ( $defs as $def ) {
			printf(
				'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td></tr>',
				esc_html( (string) $def['label'] ),
				esc_html( (string) $def['type'] ),
				esc_html( empty( $def['public'] ) ? __( 'Private', 'jooservices-resume' ) : __( 'Public', 'jooservices-resume' ) )
			);
		}
		echo '</tbody></table>';
	}

	/**
	 * Saves the settings (nonce, capability, validation).
	 */
	public function save(): void {
		$nonce = isset( $_POST[ self::NONCE . '_nonce' ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE . '_nonce' ] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'jooservices-resume' ), '', array( 'response' => 403 ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$format = isset( $_POST['date_format'] ) ? sanitize_key( wp_unslash( (string) $_POST['date_format'] ) ) : 'month_year';
		update_option(
			self::OPTION,
			array(
				'date_format'         => in_array( $format, array( 'month_year', 'iso' ), true ) ? $format : 'month_year',
				'show_duration'       => isset( $_POST['show_duration'] ),
				'show_private_labels' => isset( $_POST['show_private_labels'] ),
			),
			false
		);
		$posted = isset( $_POST['joo_def'] ) && is_array( $_POST['joo_def'] ) ? wp_unslash( $_POST['joo_def'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned by Repeaters::definitions.
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$rows = array();
		foreach ( array_values( $posted ) as $i => $row ) {
			if ( is_array( $row ) && ( '' !== trim( (string) ( $row['key'] ?? '' ) ) || '' !== trim( (string) ( $row['label'] ?? '' ) ) ) ) {
				$row['order'] = $i;
				$rows[]       = $row;
			}
		}
		$errors = ( new CompanyRepository() )->saveDefinitions( $rows );
		if ( array() !== $errors ) {
			set_transient( 'joo_resume_settings_errors_' . get_current_user_id(), array_unique( array_map( array( CompanyDataModel::class, 'message' ), $errors ) ), 60 );
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . Company::TYPE . '&page=' . self::SLUG ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Company::TYPE . '&page=' . self::SLUG . '&saved=1' ) );
		exit;
	}
}
