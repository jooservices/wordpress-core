<?php
/**
 * Company edit screen: profile, roles, achievements, technologies, custom fields and private details.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Admin;

use JOOservices\Resume\Model\CompanyRepository;
use JOOservices\Resume\Model\Keys;
use JOOservices\Resume\Model\Repeaters;
use JOOservices\Resume\Modules\CompanyDataModel;
use JOOservices\Resume\PostTypes\Company;

/**
 * Classic meta boxes plus a small vanilla script (no build step): every control is a native field with a label, the
 * repeaters add, remove and move rows with buttons (keyboard friendly) and announce each change.
 */
final class CompanyEditor {

	public const NONCE = 'joo_company_editor';

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_action( 'add_meta_boxes_' . Company::TYPE, array( $this, 'addBoxes' ) );
		add_action( 'save_post_' . Company::TYPE, array( $this, 'save' ), 20, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Adds the boxes below the basic details box.
	 */
	public function addBoxes(): void {
		add_meta_box( 'joo_company_profile', __( 'Profile and location', 'jooservices-resume' ), array( $this, 'renderProfile' ), Company::TYPE, 'normal', 'default' );
		add_meta_box( 'joo_company_roles', __( 'Roles at this company', 'jooservices-resume' ), array( $this, 'renderRoles' ), Company::TYPE, 'normal', 'default' );
		add_meta_box( 'joo_company_achievements', __( 'Achievements and technologies', 'jooservices-resume' ), array( $this, 'renderAchievements' ), Company::TYPE, 'normal', 'default' );
		add_meta_box( 'joo_company_custom', __( 'Custom fields', 'jooservices-resume' ), array( $this, 'renderCustom' ), Company::TYPE, 'normal', 'default' );
		add_meta_box( 'joo_company_private', __( 'Private details and visibility', 'jooservices-resume' ), array( $this, 'renderPrivate' ), Company::TYPE, 'normal', 'default' );
	}

	/**
	 * Script and style on the company edit screens.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function assets( $hook ): void {
		$screen = get_current_screen();
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php', Settings::HOOK ), true ) || ! $screen || ( Company::TYPE !== $screen->post_type && ! str_contains( (string) $screen->id, 'joo-resume-settings' ) ) ) {
			return;
		}
		wp_enqueue_style( 'joo-resume-admin', plugins_url( 'assets/admin.css', JOO_RESUME_FILE ), array(), \JOOservices\Resume\Plugin::VERSION );
		wp_enqueue_script( 'joo-resume-admin', plugins_url( 'assets/company-editor.js', JOO_RESUME_FILE ), array(), \JOOservices\Resume\Plugin::VERSION, true );
	}

	/**
	 * Value of a single meta key of the edited company.
	 *
	 * @param \WP_Post $post Post.
	 * @param string   $key  Short key.
	 */
	private function meta( \WP_Post $post, string $key ): string {
		return (string) get_post_meta( $post->ID, Keys::META[ $key ], true );
	}

	/**
	 * One labelled input row.
	 *
	 * @param string $name  Field name.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $type  Input type.
	 * @param string $attrs Static attributes (already safe).
	 * @param string $help  Help text.
	 */
	private function row( string $name, string $label, string $value, string $type = 'text', string $attrs = '', string $help = '' ): void {
		printf(
			'<p class="joo-field"><label for="%1$s">%2$s</label><input type="%3$s" class="widefat" id="%1$s" name="%1$s" value="%4$s" %5$s>%6$s</p>',
			esc_attr( $name ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $value ),
			$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute strings defined by callers.
			'' === $help ? '' : '<span class="joo-help">' . esc_html( $help ) . '</span>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here.
		);
	}

	/**
	 * Profile and location box.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function renderProfile( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		$this->row( 'joo_city', __( 'City', 'jooservices-resume' ), $this->meta( $post, 'city' ), 'text', 'maxlength="80"' );
		$this->row( 'joo_country', __( 'Country', 'jooservices-resume' ), $this->meta( $post, 'country' ), 'text', 'maxlength="80"' );
		$this->row( 'joo_industry', __( 'Industry', 'jooservices-resume' ), $this->meta( $post, 'industry' ), 'text', 'maxlength="80"' );
		$labels  = array(
			''          => __( 'Not set', 'jooservices-resume' ),
			'full_time' => __( 'Full time', 'jooservices-resume' ),
			'contract'  => __( 'Contract', 'jooservices-resume' ),
			'freelance' => __( 'Freelance', 'jooservices-resume' ),
			'founder'   => __( 'Founder', 'jooservices-resume' ),
		);
		$current = $this->meta( $post, 'employment' );
		echo '<p class="joo-field"><label for="joo_employment">' . esc_html__( 'Employment type', 'jooservices-resume' ) . '</label><select id="joo_employment" name="joo_employment">';
		foreach ( $labels as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}
		echo '</select></p>';
		printf(
			'<p class="joo-field"><label><input type="checkbox" name="joo_remote" value="1" %1$s> %2$s</label></p>',
			checked( (bool) get_post_meta( $post->ID, Keys::META['remote'], true ), true, false ),
			esc_html__( 'Remote position', 'jooservices-resume' )
		);
		printf(
			'<p class="joo-field"><label for="joo_summary">%1$s</label><textarea class="widefat" id="joo_summary" name="joo_summary" rows="2" maxlength="200">%2$s</textarea><span class="joo-help">%3$s</span></p>',
			esc_html__( 'Summary for cards (200 characters)', 'jooservices-resume' ),
			esc_textarea( $this->meta( $post, 'summary' ) ),
			''
		);
		printf(
			'<p class="joo-field"><label for="joo_description">%1$s</label><textarea class="widefat" id="joo_description" name="joo_description" rows="6" maxlength="5000">%2$s</textarea><span class="joo-help">%3$s</span></p>',
			esc_html__( 'Description', 'jooservices-resume' ),
			esc_textarea( $this->meta( $post, 'description' ) ),
			esc_html__( 'Allowed: p, br, strong, em, ul, ol, li and https links.', 'jooservices-resume' )
		);
		$this->row( 'joo_order', __( 'Manual order (lower first)', 'jooservices-resume' ), (string) $post->menu_order, 'number', 'min="0" max="9999"' );
	}

	/**
	 * Roles repeater.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function renderRoles( \WP_Post $post ): void {
		$rows = Repeaters::decode( get_post_meta( $post->ID, Keys::META['roles'], true ) );
		$this->repeater(
			'joo_roles',
			__( 'Role', 'jooservices-resume' ),
			$rows,
			array(
				'title'   => array( __( 'Title', 'jooservices-resume' ), 'text', 'maxlength="80"' ),
				'from'    => array( __( 'From (YYYY-MM)', 'jooservices-resume' ), 'text', 'pattern="\d{4}-(0[1-9]|1[0-2])" placeholder="2022-03"' ),
				'to'      => array( __( 'To (YYYY-MM, empty = present)', 'jooservices-resume' ), 'text', 'pattern="(\d{4}-(0[1-9]|1[0-2]))?" placeholder="2025-06"' ),
				'summary' => array( __( 'Summary', 'jooservices-resume' ), 'text', 'maxlength="400"' ),
			),
			__( 'Roles must lie inside the period at the company and cannot overlap.', 'jooservices-resume' )
		);
	}

	/**
	 * Achievements repeater and technologies.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function renderAchievements( \WP_Post $post ): void {
		$this->repeater(
			'joo_achievements',
			__( 'Achievement', 'jooservices-resume' ),
			Repeaters::decode( get_post_meta( $post->ID, Keys::META['achievements'], true ) ),
			array(
				'text'   => array( __( 'What was achieved', 'jooservices-resume' ), 'text', 'maxlength="240"' ),
				'metric' => array( __( 'Metric (number)', 'jooservices-resume' ), 'text', 'maxlength="30"' ),
				'unit'   => array( __( 'Unit', 'jooservices-resume' ), 'text', 'maxlength="20"' ),
				'url'    => array( __( 'Link (https)', 'jooservices-resume' ), 'url', 'placeholder="https://"' ),
			),
			''
		);
		$tags = json_decode( $this->meta( $post, 'technologies' ), true );
		printf(
			'<p class="joo-field"><label for="joo_technologies">%1$s</label><input type="text" class="widefat" id="joo_technologies" name="joo_technologies" value="%2$s"><span class="joo-help">%3$s</span></p>',
			esc_html__( 'Technologies', 'jooservices-resume' ),
			esc_attr( implode( ', ', is_array( $tags ) ? array_map( 'strval', $tags ) : array() ) ),
			esc_html__( 'Separate with commas. At most 40.', 'jooservices-resume' )
		);
	}

	/**
	 * Owner-defined fields.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function renderCustom( \WP_Post $post ): void {
		$definitions = ( new CompanyRepository() )->definitions();
		if ( array() === $definitions ) {
			printf(
				'<p>%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'No custom fields are defined yet.', 'jooservices-resume' ),
				esc_url( admin_url( 'edit.php?post_type=' . Company::TYPE . '&page=' . Settings::SLUG ) ),
				esc_html__( 'Define them in the settings.', 'jooservices-resume' )
			);
			return;
		}
		$values = json_decode( $this->meta( $post, 'custom' ), true );
		$values = is_array( $values ) ? $values : array();
		foreach ( $definitions as $definition ) {
			$key   = (string) $definition['key'];
			$value = $values[ $key ] ?? '';
			$value = is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
			$label = (string) $definition['label'] . ( empty( $definition['public'] ) ? ' ' . __( '(private)', 'jooservices-resume' ) : '' );
			$kind  = array(
				'number' => array( 'number', 'step="any"' ),
				'date'   => array( 'date', '' ),
				'url'    => array( 'url', '' ),
			)[ $definition['type'] ] ?? array( 'text', 'maxlength="300"' );
			$help  = 'list' === $definition['type'] ? __( 'Separate with commas.', 'jooservices-resume' ) : '';
			$this->row( 'joo_custom_' . $key, $label, $value, $kind[0], $kind[1], $help );
		}
	}

	/**
	 * Compensation and equity, with a per-field switch to public.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function renderPrivate( \WP_Post $post ): void {
		echo '<p class="joo-help">' . esc_html__( 'These values are never shown to visitors unless you switch them to public.', 'jooservices-resume' ) . '</p>';
		$public = json_decode( $this->meta( $post, 'public_fields' ), true );
		$public = is_array( $public ) ? $public : array();
		foreach ( array(
			'compensation' => __( 'Compensation', 'jooservices-resume' ),
			'equity'       => __( 'Equity', 'jooservices-resume' ),
		) as $field => $label ) {
			$this->row( 'joo_' . $field, $label, $this->meta( $post, $field ), 'text', 'maxlength="120"' );
			printf(
				'<p class="joo-field joo-field--inline"><label><input type="checkbox" name="joo_public_%1$s" value="1" %2$s> %3$s</label></p>',
				esc_attr( $field ),
				checked( in_array( $field, $public, true ), true, false ),
				/* translators: %s: field label. */
				esc_html( sprintf( __( 'Show %s to visitors', 'jooservices-resume' ), strtolower( $label ) ) )
			);
		}
	}

	/**
	 * Renders a repeater: existing rows plus a template the script clones.
	 *
	 * @param string                                         $name   Field name prefix.
	 * @param string                                         $noun   Singular noun for the row title.
	 * @param list<array<string, mixed>>                     $rows   Existing rows.
	 * @param array<string, array{0: string, 1: string, 2: string}> $fields Field label, type and attributes.
	 * @param string                                         $help   Help text.
	 */
	private function repeater( string $name, string $noun, array $rows, array $fields, string $help ): void {
		if ( '' !== $help ) {
			echo '<p class="joo-help">' . esc_html( $help ) . '</p>';
		}
		printf( '<div class="joo-repeater" data-joo-repeater="%1$s" data-noun="%2$s">', esc_attr( $name ), esc_attr( $noun ) );
		echo '<div class="joo-repeater__rows" role="list">';
		foreach ( array_values( $rows ) as $i => $row ) {
			$this->repeaterRow( $name, $noun, (string) $i, $fields, $row );
		}
		echo '</div>';
		echo '<template>';
		$this->repeaterRow( $name, $noun, '__INDEX__', $fields, array() );
		echo '</template>';
		/* translators: %s: item noun, for example Role. */
		printf( '<p><button type="button" class="button" data-joo-add>%s</button></p>', esc_html( sprintf( __( 'Add %s', 'jooservices-resume' ), strtolower( $noun ) ) ) );
		echo '<p class="screen-reader-text" role="status" aria-live="polite" data-joo-live></p></div>';
	}

	/**
	 * One repeater row.
	 *
	 * @param string                                         $name   Field name prefix.
	 * @param string                                         $noun   Noun.
	 * @param string                                         $index  Row index or the placeholder.
	 * @param array<string, array{0: string, 1: string, 2: string}> $fields Fields.
	 * @param array<string, mixed>                           $row    Values.
	 */
	private function repeaterRow( string $name, string $noun, string $index, array $fields, array $row ): void {
		echo '<fieldset class="joo-repeater__row" role="listitem" data-joo-row><legend><span data-joo-title>' . esc_html( $noun ) . '</span></legend>';
		foreach ( $fields as $key => $field ) {
			$id = $name . '_' . $index . '_' . $key;
			printf(
				'<p class="joo-field"><label for="%1$s">%2$s</label><input type="%3$s" class="widefat" id="%1$s" name="%4$s[%5$s][%6$s]" value="%7$s" %8$s></p>',
				esc_attr( $id ),
				esc_html( $field[0] ),
				esc_attr( $field[1] ),
				esc_attr( $name ),
				esc_attr( $index ),
				esc_attr( $key ),
				esc_attr( (string) ( $row[ $key ] ?? '' ) ),
				$field[2] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute strings defined above.
			);
		}
		/* translators: %s: item noun. */
		$up = sprintf( __( 'Move %s up', 'jooservices-resume' ), strtolower( $noun ) );
		/* translators: %s: item noun. */
		$down = sprintf( __( 'Move %s down', 'jooservices-resume' ), strtolower( $noun ) );
		/* translators: %s: item noun. */
		$remove = sprintf( __( 'Remove %s', 'jooservices-resume' ), strtolower( $noun ) );
		printf(
			'<p class="joo-repeater__actions"><button type="button" class="button" data-joo-up>%1$s</button> <button type="button" class="button" data-joo-down>%2$s</button> <button type="button" class="button-link-delete joo-remove" data-joo-remove>%3$s</button></p></fieldset>',
			esc_html( $up ),
			esc_html( $down ),
			esc_html( $remove )
		);
	}

	/**
	 * Reads a posted repeater as a list of rows in screen order.
	 *
	 * @param string $name Field name.
	 * @return list<array<string, mixed>>
	 */
	private function posted( string $name ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified and cleaned by Repeaters in save().
		$raw = isset( $_POST[ $name ] ) && is_array( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : array();
		$out = array();
		foreach ( $raw as $row ) {
			if ( is_array( $row ) && array() !== array_filter( $row, static fn( $cell ): bool => is_scalar( $cell ) && '' !== trim( (string) $cell ) ) ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * Saves the boxes with a nonce and capability check. Each group is saved only when it is valid; messages tell the
	 * author what to fix.
	 *
	 * @param int $postId Post id.
	 */
	public function save( int $postId ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		$nonce = isset( $_POST[ self::NONCE . '_nonce' ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE . '_nonce' ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE ) || ! current_user_can( 'edit_post', $postId ) ) {
			return;
		}
		$text   = static fn( string $key, int $length ): string => Repeaters::text( isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '', $length ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified above, cleaned by Repeaters::text.
		$errors = array();
		$from   = (string) get_post_meta( $postId, '_joo_company_from', true );
		$to     = (string) get_post_meta( $postId, '_joo_company_to', true );

		$employment = $text( 'joo_employment', 20 );
		if ( '' !== $employment && ! in_array( $employment, Repeaters::EMPLOYMENT, true ) ) {
			$errors[]   = array(
				'code'  => 'employment',
				'field' => 'employment',
			);
			$employment = '';
		}
		$simple = array(
			'city'         => $text( 'joo_city', 80 ),
			'country'      => $text( 'joo_country', 80 ),
			'industry'     => $text( 'joo_industry', 80 ),
			'summary'      => $text( 'joo_summary', 200 ),
			'employment'   => $employment,
			'compensation' => $text( 'joo_compensation', 120 ),
			'equity'       => $text( 'joo_equity', 120 ),
		);
		foreach ( $simple as $key => $value ) {
			update_post_meta( $postId, Keys::META[ $key ], $value );
		}
		update_post_meta( $postId, Keys::META['remote'], isset( $_POST['joo_remote'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified above; cleaned by CompanyDataModel::cleanDescription.
		update_post_meta( $postId, Keys::META['description'], CompanyDataModel::cleanDescription( isset( $_POST['joo_description'] ) ? wp_unslash( $_POST['joo_description'] ) : '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$order = isset( $_POST['joo_order'] ) ? absint( wp_unslash( $_POST['joo_order'] ) ) : 0;
		if ( (int) get_post_field( 'menu_order', $postId ) !== $order ) {
			remove_action( 'save_post_' . Company::TYPE, array( $this, 'save' ), 20 );
			wp_update_post(
				array(
					'ID'         => $postId,
					'menu_order' => min( 9999, $order ),
				)
			);
			add_action( 'save_post_' . Company::TYPE, array( $this, 'save' ), 20, 1 );
		}
		$public = array();
		foreach ( array( 'compensation', 'equity' ) as $field ) {
			if ( isset( $_POST[ 'joo_public_' . $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
				$public[] = $field;
			}
		}
		update_post_meta( $postId, Keys::META['public_fields'], array() === $public ? '' : (string) wp_json_encode( $public ) );

		$roles = Repeaters::roles( $this->posted( 'joo_roles' ), $from, $to );
		if ( array() === $roles['errors'] ) {
			update_post_meta( $postId, Keys::META['roles'], Repeaters::encode( $roles['items'] ) );
		}
		$achievements = Repeaters::achievements( $this->posted( 'joo_achievements' ) );
		if ( array() === $achievements['errors'] ) {
			update_post_meta( $postId, Keys::META['achievements'], Repeaters::encode( $achievements['items'] ) );
		}
		$tags = Repeaters::technologies( array_map( 'trim', explode( ',', $text( 'joo_technologies', 2000 ) ) ) );
		update_post_meta( $postId, Keys::META['technologies'], array() === $tags ? '' : (string) wp_json_encode( $tags ) );

		$definitions = ( new CompanyRepository() )->definitions();
		$rawCustom   = array();
		foreach ( $definitions as $definition ) {
			$key               = (string) $definition['key'];
			$value             = $text( 'joo_custom_' . $key, 600 );
			$rawCustom[ $key ] = 'list' === $definition['type'] ? array_map( 'trim', explode( ',', $value ) ) : $value;
		}
		$custom = Repeaters::customValues( $rawCustom, $definitions );
		if ( array() === $custom['errors'] ) {
			update_post_meta( $postId, Keys::META['custom'], array() === $custom['values'] ? '' : (string) wp_json_encode( $custom['values'] ) );
		}

		$errors = array_merge( $errors, $roles['errors'], $achievements['errors'], $custom['errors'] );
		if ( array() !== $errors ) {
			$key      = 'joo_company_errors_' . get_current_user_id();
			$existing = get_transient( $key );
			$messages = array_map( array( CompanyDataModel::class, 'message' ), $errors );
			set_transient( $key, array_merge( is_array( $existing ) ? $existing : array(), $messages ), 60 );
		}
	}
}
