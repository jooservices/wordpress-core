<?php
/**
 * Company data model: fields, validation and REST privacy.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Modules;

use JOOservices\Resume\Model\CompanyRecord;
use JOOservices\Resume\Model\CompanyRepository;
use JOOservices\Resume\Model\Keys;
use JOOservices\Resume\Model\Period;
use JOOservices\Resume\Model\Repeaters;
use JOOservices\Resume\PostTypes\Company;

/**
 * Registers the extended company fields, validates them on REST writes and strips private fields from REST responses
 * for visitors. Repeaters are JSON meta with a schema version; the REST output is the registered whitelist.
 */
final class CompanyDataModel {

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		$this->registerMeta();
		add_filter( 'rest_pre_insert_' . Company::TYPE, array( $this, 'validate' ), 11, 2 );
		add_filter( 'rest_prepare_' . Company::TYPE, array( $this, 'hidePrivate' ), 10, 2 );
	}

	/**
	 * Meta keys that never leave the server for visitors unless the owner switches them to public.
	 *
	 * @return list<string>
	 */
	public static function privateKeys(): array {
		return array_map( static fn( string $field ): string => Keys::META[ $field ], CompanyRecord::PRIVATE_FIELDS );
	}

	/**
	 * Registers the new meta.
	 */
	private function registerMeta(): void {
		$auth   = static fn( $allowed, $key, $postId ) => current_user_can( 'edit_post', (int) $postId );
		$string = static fn( int $max ): array => array(
			'type'              => 'string',
			'schema'            => array( 'maxLength' => $max ),
			'sanitize_callback' => static fn( $value ): string => Repeaters::text( $value, $max ),
		);
		$json   = static fn( callable $clean ): array => array(
			'type'              => 'string',
			'schema'            => array( 'maxLength' => 20000 ),
			'sanitize_callback' => $clean,
		);
		$fields = array(
			'city'          => $string( 80 ),
			'country'       => $string( 80 ),
			'industry'      => $string( 80 ),
			'summary'       => $string( 200 ),
			'compensation'  => $string( 120 ),
			'equity'        => $string( 120 ),
			'employment'    => array(
				'type'              => 'string',
				'schema'            => array( 'enum' => array_merge( array( '' ), Repeaters::EMPLOYMENT ) ),
				'sanitize_callback' => static fn( $value ): string => in_array( $value, Repeaters::EMPLOYMENT, true ) ? (string) $value : '',
			),
			'remote'        => array(
				'type'              => 'boolean',
				'sanitize_callback' => static fn( $value ): bool => filter_var( $value, FILTER_VALIDATE_BOOLEAN ),
			),
			'description'   => array(
				'type'              => 'string',
				'schema'            => array( 'maxLength' => 5000 ),
				'sanitize_callback' => array( self::class, 'cleanDescription' ),
			),
			'roles'         => $json( static fn( $value ): string => Repeaters::encode( Repeaters::roles( Repeaters::decode( $value ), '', '' )['items'] ) ),
			'achievements'  => $json( static fn( $value ): string => Repeaters::encode( Repeaters::achievements( Repeaters::decode( $value ) )['items'] ) ),
			'technologies'  => $json( array( self::class, 'cleanTechnologies' ) ),
			'custom'        => $json( array( self::class, 'cleanCustom' ) ),
			'public_fields' => $json( array( self::class, 'cleanPublicFields' ) ),
		);
		foreach ( $fields as $key => $def ) {
			register_post_meta(
				Company::TYPE,
				Keys::META[ $key ],
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
	 * Limited rich text: paragraphs, line breaks, emphasis, lists and https links.
	 *
	 * @param mixed $value Raw description.
	 */
	public static function cleanDescription( $value ): string {
		return wp_kses(
			(string) $value,
			array(
				'p'      => array(),
				'br'     => array(),
				'strong' => array(),
				'em'     => array(),
				'ul'     => array(),
				'ol'     => array(),
				'li'     => array(),
				'a'      => array(
					'href' => true,
					'rel'  => true,
				),
			),
			array( 'https', 'mailto' )
		);
	}

	/**
	 * Technologies as a JSON list of unique tags.
	 *
	 * @param mixed $value Raw JSON or list.
	 */
	public static function cleanTechnologies( $value ): string {
		$list = is_string( $value ) ? json_decode( $value, true ) : $value;
		$tags = Repeaters::technologies( is_array( $list ) ? $list : array() );
		return array() === $tags ? '' : (string) wp_json_encode( $tags );
	}

	/**
	 * Custom values reduced to the defined fields and cleaned by type.
	 *
	 * @param mixed $value Raw JSON or map.
	 */
	public static function cleanCustom( $value ): string {
		$map    = is_string( $value ) ? json_decode( $value, true ) : $value;
		$result = Repeaters::customValues( is_array( $map ) ? $map : array(), ( new CompanyRepository() )->definitions() );
		return array() === $result['values'] ? '' : (string) wp_json_encode( $result['values'] );
	}

	/**
	 * Only the private field names are valid here.
	 *
	 * @param mixed $value Raw JSON or list.
	 */
	public static function cleanPublicFields( $value ): string {
		$list = is_string( $value ) ? json_decode( $value, true ) : $value;
		$keep = array_values( array_intersect( CompanyRecord::PRIVATE_FIELDS, is_array( $list ) ? $list : array() ) );
		return array() === $keep ? '' : (string) wp_json_encode( $keep );
	}

	/**
	 * REST: rejects bad roles, achievements, custom values and an unknown employment type with a clear message.
	 *
	 * @param \stdClass        $prepared Prepared post.
	 * @param \WP_REST_Request $request  Request.
	 * @return \stdClass|\WP_Error
	 */
	public function validate( $prepared, $request ) {
		$meta   = $request->get_param( 'meta' );
		$meta   = is_array( $meta ) ? $meta : array();
		$id     = isset( $prepared->ID ) ? (int) $prepared->ID : 0;
		$read   = static fn( string $key ) => $meta[ Keys::META[ $key ] ] ?? ( $id > 0 ? get_post_meta( $id, Keys::META[ $key ], true ) : '' );
		$from   = (string) $read( 'from' );
		$to     = (string) $read( 'to' );
		$errors = array();
		if ( array_key_exists( Keys::META['roles'], $meta ) ) {
			$errors = array_merge( $errors, Repeaters::roles( $this->items( $meta[ Keys::META['roles'] ] ), $from, $to )['errors'] );
		}
		if ( array_key_exists( Keys::META['achievements'], $meta ) ) {
			$errors = array_merge( $errors, Repeaters::achievements( $this->items( $meta[ Keys::META['achievements'] ] ) )['errors'] );
		}
		if ( array_key_exists( Keys::META['custom'], $meta ) ) {
			$map    = is_string( $meta[ Keys::META['custom'] ] ) ? json_decode( $meta[ Keys::META['custom'] ], true ) : $meta[ Keys::META['custom'] ];
			$errors = array_merge( $errors, Repeaters::customValues( is_array( $map ) ? $map : array(), ( new CompanyRepository() )->definitions() )['errors'] );
		}
		if ( array_key_exists( Keys::META['employment'], $meta ) && '' !== $meta[ Keys::META['employment'] ] && ! in_array( $meta[ Keys::META['employment'] ], Repeaters::EMPLOYMENT, true ) ) {
			$errors[] = array(
				'code'  => 'employment',
				'field' => 'employment',
			);
		}
		if ( array() === $errors ) {
			return $prepared;
		}
		return new \WP_Error(
			'rest_invalid_param',
			implode( ' ', array_map( array( self::class, 'message' ), $errors ) ),
			array(
				'status' => 400,
				'errors' => $errors,
			)
		);
	}

	/**
	 * Visitors never receive private meta; the owner can switch compensation or equity to public per field.
	 *
	 * @param \WP_REST_Response $response Response.
	 * @param \WP_Post          $post     Post.
	 * @return \WP_REST_Response
	 */
	public function hidePrivate( $response, $post ) {
		if ( ! $response instanceof \WP_REST_Response || ! $post instanceof \WP_Post || current_user_can( 'edit_post', $post->ID ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) || ! isset( $data['meta'] ) || ! is_array( $data['meta'] ) ) {
			return $response;
		}
		$public = json_decode( (string) get_post_meta( $post->ID, Keys::META['public_fields'], true ), true );
		$public = is_array( $public ) ? $public : array();
		foreach ( CompanyRecord::PRIVATE_FIELDS as $field ) {
			if ( ! in_array( $field, $public, true ) ) {
				unset( $data['meta'][ Keys::META[ $field ] ] );
			}
		}
		if ( isset( $data['meta'][ Keys::META['custom'] ] ) ) {
			$values                               = json_decode( (string) $data['meta'][ Keys::META['custom'] ], true );
			$visible                              = CompanyRecord::publicCustom( is_array( $values ) ? $values : array(), ( new CompanyRepository() )->definitions() );
			$data['meta'][ Keys::META['custom'] ] = array() === $visible ? '' : (string) wp_json_encode( $visible );
		}
		$response->set_data( $data );
		return $response;
	}

	/**
	 * Items of a REST value that is a JSON string or an already decoded list.
	 *
	 * @param mixed $value Value.
	 * @return list<array<string, mixed>>
	 */
	private function items( $value ): array {
		return Repeaters::decode( $value );
	}

	/**
	 * Translated message of a validation error.
	 *
	 * @param array{code: string, field: string} $error Error.
	 */
	public static function message( array $error ): string {
		$position = preg_match( '/\.(\d+)$/', $error['field'], $m ) ? (int) $m[1] + 1 : 0;
		switch ( $error['code'] ) {
			case 'role_title':
				/* translators: %d: position in the list. */
				return sprintf( __( 'Role %d needs a title.', 'jooservices-resume' ), $position );
			case 'role_month':
				/* translators: %d: position in the list. */
				return sprintf( __( 'Role %d needs a start month like 2022-03 and an end month like 2025-06 or none.', 'jooservices-resume' ), $position );
			case 'role_order':
				/* translators: %d: position in the list. */
				return sprintf( __( 'The end month of role %d cannot be before its start month.', 'jooservices-resume' ), $position );
			case 'role_outside':
				/* translators: %d: position in the list. */
				return sprintf( __( 'Role %d must lie inside the period at the company.', 'jooservices-resume' ), $position );
			case 'role_overlap':
				/* translators: %d: position in the list. */
				return sprintf( __( 'Role %d overlaps another role.', 'jooservices-resume' ), $position );
			case 'achievement_text':
				/* translators: %d: position in the list. */
				return sprintf( __( 'Achievement %d needs a text.', 'jooservices-resume' ), $position );
			case 'achievement_metric':
				/* translators: %d: position in the list. */
				return sprintf( __( 'The metric of achievement %d must be a number.', 'jooservices-resume' ), $position );
			case 'achievement_url':
				/* translators: %d: position in the list. */
				return sprintf( __( 'The link of achievement %d must be an https URL.', 'jooservices-resume' ), $position );
			case 'employment':
				return __( 'The employment type must be full time, contract, freelance or founder.', 'jooservices-resume' );
			case 'definition_key':
				return __( 'A custom field key uses lowercase letters, digits and underscores and starts with a letter.', 'jooservices-resume' );
			case 'definition_label':
				return __( 'A custom field needs a label and one of the types text, number, date, url or list.', 'jooservices-resume' );
			case 'definition_duplicate':
				return __( 'Custom field keys must be unique.', 'jooservices-resume' );
			case 'custom_number':
				return __( 'A custom number field holds a number.', 'jooservices-resume' );
			case 'custom_date':
				return __( 'A custom date field looks like 2025-06-30.', 'jooservices-resume' );
			case 'company_name':
				return __( 'Every company needs a name.', 'jooservices-resume' );
			case 'company_month':
				return __( 'The start month must look like 2022-03 and the end month like 2025-06 or be empty.', 'jooservices-resume' );
			case 'company_order':
				return __( 'The end month cannot be before the start month.', 'jooservices-resume' );
			case 'company_url':
				return __( 'The website must be an https URL.', 'jooservices-resume' );
			case 'import_json':
				return __( 'The file is not valid JSON.', 'jooservices-resume' );
			case 'import_format':
				return __( 'The file is neither a JOOservices Resume export nor a JSON Resume document with a work section.', 'jooservices-resume' );
			case 'import_rows':
				return __( 'The file has more than 200 companies.', 'jooservices-resume' );
			case 'import_empty':
				return __( 'The file contains no companies.', 'jooservices-resume' );
			case 'custom_url':
				return __( 'A custom URL field must be an https URL.', 'jooservices-resume' );
		}
		return __( 'The company data is not valid.', 'jooservices-resume' );
	}
}
