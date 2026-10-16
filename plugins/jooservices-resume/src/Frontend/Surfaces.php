<?php
/**
 * Shortcode, block, REST endpoints and structured data of the resume.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Frontend;

use JOOservices\Resume\Model\CompanyRecord;
use JOOservices\Resume\Model\CompanyRepository;
use JOOservices\Resume\Transfer\Mapper;

/**
 * Every surface calls the same renderer or the same repository projection.
 */
final class Surfaces {

	public const SHORTCODE = 'joo_resume';
	public const BLOCK     = 'joo/resume';
	public const REST_NS   = 'jooservices-resume/v1';

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		Renderer::registerStyle();
		add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK ) ) {
			register_block_type(
				dirname( JOO_RESUME_FILE ) . '/blocks/resume',
				array( 'render_callback' => array( $this, 'block' ) )
			);
		}
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'joo/seo/schema/person', array( $this, 'person' ), 20 );
		add_filter( 'wpseo_schema_person', array( $this, 'person' ), 20 );
	}

	/**
	 * `[joo_resume order="manual|date" limit="0" current="no"]`.
	 *
	 * @param array<string, string>|string $atts Attributes.
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'order'   => 'manual',
				'limit'   => '0',
				'current' => 'no',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);
		return ( new Renderer() )->render(
			array(
				'order'        => (string) $atts['order'],
				'limit'        => absint( $atts['limit'] ),
				'current_only' => filter_var( $atts['current'], FILTER_VALIDATE_BOOLEAN ),
			)
		);
	}

	/**
	 * Dynamic block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function block( $attributes ): string {
		$attributes = is_array( $attributes ) ? $attributes : array();
		return ( new Renderer() )->render(
			array(
				'order'        => (string) ( $attributes['order'] ?? 'manual' ),
				'limit'        => absint( $attributes['limit'] ?? 0 ),
				'current_only' => ! empty( $attributes['currentOnly'] ),
			)
		);
	}

	/**
	 * Public, cacheable endpoints.
	 */
	public function routes(): void {
		foreach ( array(
			'/resume'             => 'resume',
			'/resume/json-resume' => 'jsonResume',
		) as $route => $method ) {
			register_rest_route(
				self::REST_NS,
				$route,
				array(
					'methods'             => 'GET',
					'permission_callback' => '__return_true',
					'callback'            => fn( \WP_REST_Request $request ) => $this->cached( $request, $this->{$method}() ),
				)
			);
		}
	}

	/**
	 * The public projection of every published company.
	 *
	 * @return array<string, mixed>
	 */
	public function resume(): array {
		$repo = new CompanyRepository();
		$defs = $repo->definitions();
		$now  = gmdate( 'Y-m' );
		return array(
			'companies' => array_map( static fn( CompanyRecord $record ): array => $record->toPublic( $defs, $now ), $repo->all( 'manual' ) ),
		);
	}

	/**
	 * JSON Resume `work` section (public data only).
	 *
	 * @return array<string, mixed>
	 */
	public function jsonResume(): array {
		$rows = array_map( static fn( CompanyRecord $record ): array => $record->toRow(), ( new CompanyRepository() )->all( 'manual' ) );
		$data = json_decode( Mapper::toJsonResume( $rows ), true );
		return is_array( $data ) ? $data : array( 'work' => array() );
	}

	/**
	 * Adds validators: an ETag, a public Cache-Control and a 304 for a matching If-None-Match.
	 *
	 * @param \WP_REST_Request     $request Request.
	 * @param array<string, mixed> $data    Response data.
	 */
	private function cached( \WP_REST_Request $request, array $data ): \WP_REST_Response {
		$etag     = '"' . md5( (string) wp_json_encode( $data ) ) . '"';
		$matches  = trim( (string) $request->get_header( 'if_none_match' ) ) === $etag;
		$response = new \WP_REST_Response( $matches ? null : $data );
		if ( $matches ) {
			$response->set_status( 304 );
		}
		$response->header( 'ETag', $etag );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		return $response;
	}

	/**
	 * Adds `worksFor` and `hasOccupation` to the site owner Person node (the theme graph and Yoast both call this). The
	 * current company only, or every company on a page that shows the resume.
	 *
	 * @param mixed $node Person node.
	 * @return mixed
	 */
	public function person( $node ) {
		if ( ! is_array( $node ) ) {
			return $node;
		}
		$records = ( new CompanyRepository() )->all( 'manual' );
		if ( ! $this->pageShowsResume() ) {
			$records = array_values( array_filter( $records, static fn( CompanyRecord $record ): bool => $record->isCurrent() ) );
		}
		$organizations = array();
		$occupations   = array();
		foreach ( $records as $record ) {
			$organizations[] = array_filter(
				array(
					'@type' => 'Organization',
					'name'  => $record->name,
					'url'   => $record->url,
				)
			);
			if ( $record->isCurrent() && '' !== $record->role ) {
				$occupations[] = array(
					'@type' => 'Occupation',
					'name'  => $record->role,
				);
			}
		}
		if ( array() !== $organizations ) {
			$node['worksFor'] = $organizations;
		}
		if ( array() !== $occupations ) {
			$node['hasOccupation'] = $occupations;
		}
		return $node;
	}

	/**
	 * Whether the current singular page contains the shortcode or the block.
	 */
	private function pageShowsResume(): bool {
		$post = is_singular() ? get_post() : null;
		return $post instanceof \WP_Post && ( has_shortcode( $post->post_content, self::SHORTCODE ) || has_block( self::BLOCK, $post ) );
	}
}
