<?php
/**
 * Owner profile fields.
 *
 * @package JOOservices\Content
 */

declare(strict_types=1);

namespace JOOservices\Content\Meta;

use JOOservices\Content\Support\Validate;

/**
 * Title, short bio, avatar and social links on the user profile.
 */
final class UserMeta {

	public const NONCE       = 'joo_user_meta';
	public const MAX_SOCIALS = 8;

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		$this->register();
		add_action( 'show_user_profile', array( $this, 'render' ) );
		add_action( 'edit_user_profile', array( $this, 'render' ) );
		add_action( 'personal_options_update', array( $this, 'save' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save' ) );
		add_filter( 'get_avatar_data', array( $this, 'avatar' ), 10, 2 );
	}

	/**
	 * Registers the user meta (only public-safe fields reach REST).
	 */
	public function register(): void {
		$auth = static fn( $allowed, $key, $userId ) => current_user_can( 'edit_user', (int) $userId );
		register_meta(
			'user',
			'joo_title',
			array(
				'single'            => true,
				'type'              => 'string',
				'show_in_rest'      => array(
					'schema' => array(
						'type'      => 'string',
						'maxLength' => 80,
					),
				),
				'sanitize_callback' => static fn( $v ) => mb_substr( sanitize_text_field( (string) $v ), 0, 80 ),
				'auth_callback'     => $auth,
			)
		);
		register_meta(
			'user',
			'joo_short_bio',
			array(
				'single'            => true,
				'type'              => 'string',
				'show_in_rest'      => array(
					'schema' => array(
						'type'      => 'string',
						'maxLength' => 240,
					),
				),
				'sanitize_callback' => static fn( $v ) => mb_substr( sanitize_text_field( (string) $v ), 0, 240 ),
				'auth_callback'     => $auth,
			)
		);
		register_meta(
			'user',
			'joo_avatar_id',
			array(
				'single'            => true,
				'type'              => 'integer',
				'show_in_rest'      => true,
				'sanitize_callback' => array( Validate::class, 'imageId' ),
				'auth_callback'     => $auth,
			)
		);
		register_meta(
			'user',
			'joo_socials',
			array(
				'single'            => true,
				'type'              => 'array',
				'show_in_rest'      => array(
					'schema' => array(
						'type'     => 'array',
						'maxItems' => self::MAX_SOCIALS,
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'label' => array( 'type' => 'string' ),
								'url'   => array( 'type' => 'string' ),
							),
						),
					),
				),
				'sanitize_callback' => array( self::class, 'cleanSocials' ),
				'auth_callback'     => $auth,
			)
		);
	}

	/**
	 * Keeps at most 8 entries with a label and an https URL.
	 *
	 * @param mixed $value Raw value.
	 * @return list<array{label: string, url: string}>
	 */
	public static function cleanSocials( $value ): array {
		$out = array();
		foreach ( is_array( $value ) ? $value : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$url   = Validate::httpsUrl( $row['url'] ?? '' );
			$label = mb_substr( sanitize_text_field( (string) ( $row['label'] ?? '' ) ), 0, 40 );
			if ( '' === $url ) {
				continue;
			}
			$out[] = array(
				'label' => '' === $label ? (string) wp_parse_url( $url, PHP_URL_HOST ) : $label,
				'url'   => $url,
			);
			if ( count( $out ) >= self::MAX_SOCIALS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Fields on the profile screens.
	 *
	 * @param \WP_User $user User.
	 */
	public function render( \WP_User $user ): void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		$socials = get_user_meta( $user->ID, 'joo_socials', true );
		$socials = is_array( $socials ) ? $socials : array();
		echo '<h2>' . esc_html__( 'JOOservices profile', 'jooservices-content' ) . '</h2><table class="form-table" role="presentation"><tbody>';
		$rows = array(
			array( 'joo_title', __( 'Title', 'jooservices-content' ), 'text', 80 ),
			array( 'joo_short_bio', __( 'Short bio', 'jooservices-content' ), 'text', 240 ),
			array( 'joo_avatar_id', __( 'Avatar (attachment id)', 'jooservices-content' ), 'number', 0 ),
		);
		foreach ( $rows as $row ) {
			printf(
				'<tr><th><label for="%1$s">%2$s</label></th><td><input type="%3$s" class="regular-text" id="%1$s" name="%1$s" value="%4$s"%5$s></td></tr>',
				esc_attr( $row[0] ),
				esc_html( $row[1] ),
				esc_attr( $row[2] ),
				esc_attr( (string) get_user_meta( $user->ID, $row[0], true ) ),
				$row[3] > 0 ? ' maxlength="' . (int) $row[3] . '"' : ''
			);
		}
		for ( $i = 0; $i < self::MAX_SOCIALS; $i++ ) {
			printf(
				'<tr><th>%1$s</th><td><input type="text" name="joo_socials[%2$d][label]" value="%3$s" placeholder="%5$s" class="regular-text"> <input type="url" name="joo_socials[%2$d][url]" value="%4$s" placeholder="https://" class="regular-text"></td></tr>',
				0 === $i ? esc_html__( 'Social links', 'jooservices-content' ) : '',
				(int) $i,
				esc_attr( (string) ( $socials[ $i ]['label'] ?? '' ) ),
				esc_attr( (string) ( $socials[ $i ]['url'] ?? '' ) ),
				esc_attr__( 'Label', 'jooservices-content' )
			);
		}
		echo '</tbody></table>';
	}

	/**
	 * Saves the fields with nonce and capability checks.
	 *
	 * @param int $userId User id.
	 */
	public function save( $userId ): void {
		$userId = (int) $userId;
		$nonce  = isset( $_POST[ self::NONCE . '_nonce' ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE . '_nonce' ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE ) || ! current_user_can( 'edit_user', $userId ) ) {
			return;
		}
		$text = static fn( string $key ): string => isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : '';
		update_user_meta( $userId, 'joo_title', mb_substr( $text( 'joo_title' ), 0, 80 ) );
		update_user_meta( $userId, 'joo_short_bio', mb_substr( $text( 'joo_short_bio' ), 0, 240 ) );
		update_user_meta( $userId, 'joo_avatar_id', Validate::imageId( $text( 'joo_avatar_id' ) ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every cell is sanitised in cleanSocials().
		$raw = isset( $_POST['joo_socials'] ) && is_array( $_POST['joo_socials'] ) ? wp_unslash( $_POST['joo_socials'] ) : array();
		update_user_meta( $userId, 'joo_socials', self::cleanSocials( $raw ) );
	}

	/**
	 * Uses the profile avatar when one is set.
	 *
	 * @param array<string, mixed> $args Avatar data.
	 * @param mixed                $idOrEmail User id, email, comment or post.
	 * @return array<string, mixed>
	 */
	public function avatar( $args, $idOrEmail ): array {
		$user = null;
		if ( is_numeric( $idOrEmail ) ) {
			$user = get_user_by( 'id', (int) $idOrEmail );
		} elseif ( $idOrEmail instanceof \WP_User ) {
			$user = $idOrEmail;
		} elseif ( is_string( $idOrEmail ) && is_email( $idOrEmail ) ) {
			$user = get_user_by( 'email', $idOrEmail );
		}
		if ( ! $user instanceof \WP_User ) {
			return $args;
		}
		$id = (int) get_user_meta( $user->ID, 'joo_avatar_id', true );
		if ( $id < 1 ) {
			return $args;
		}
		$size = max( 16, (int) ( $args['size'] ?? 96 ) );
		$src  = wp_get_attachment_image_src( $id, array( $size, $size ) );
		if ( is_array( $src ) ) {
			$args['url']          = $src[0];
			$args['found_avatar'] = true;
		}
		return $args;
	}
}
