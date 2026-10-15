<?php
/**
 * Block joo/companies.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\FragmentBlock;
use JOOservices\ThemeCore\Content\ContentProviderInterface;

/**
 * Pinned current company plus a paginated list: real links render any page server-side (`?joo_companies_page=N`),
 * the view module swaps pages in place.
 *
 * @internal
 */
final class Companies extends FragmentBlock {

	public const QUERY_VAR = 'joo_companies_page';

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/companies';
	}

	/**
	 * Fragment name.
	 */
	public static function fragment(): string {
		return 'companies';
	}

	/**
	 * Fragment height.
	 */
	public static function height(): int {
		return 420;
	}

	/**
	 * Fragment schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function schema(): array {
		return array(
			'page'   => array(
				'type'    => 'int',
				'default' => 1,
				'min'     => 1,
			),
			'per'    => array(
				'type'    => 'int',
				'default' => 2,
				'min'     => 1,
				'max'     => 10,
			),
			'desc'   => array(
				'type'    => 'bool',
				'default' => true,
			),
			'pinned' => array(
				'type'      => 'string',
				'maxLength' => 60,
			),
			'list'   => array(
				'type'      => 'string',
				'maxLength' => 80,
			),
			'title'  => array(
				'type'      => 'string',
				'maxLength' => 60,
			),
		);
	}

	/**
	 * Fragment arguments (the page comes from the query var).
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return array<string, int|string|bool>
	 */
	protected function fragmentArgs( array $attrs ): array {
		$page = self::requestedPage();
		return array(
			'page'   => $page,
			'per'    => max( 1, min( 10, (int) ( $attrs['perPage'] ?? 2 ) ) ),
			'desc'   => ! array_key_exists( 'showDescription', $attrs ) || (bool) $attrs['showDescription'],
			'pinned' => (string) ( $attrs['pinnedLabel'] ?? '' ),
			'list'   => (string) ( $attrs['listLabel'] ?? '' ),
			'title'  => (string) ( $attrs['title'] ?? '' ),
		);
	}

	/**
	 * Page asked for in the URL (public read-only pagination, no state is changed).
	 */
	public static function requestedPage(): int {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only, absint() below.
		return isset( $_GET[ self::QUERY_VAR ] ) ? max( 1, absint( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) ) : 1;
		// phpcs:enable
	}

	/**
	 * Attributes from fragment arguments.
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @return array<string, mixed>
	 */
	protected function attrsFromArgs( array $args ): array {
		return array(
			'perPage'         => (int) ( $args['per'] ?? 2 ),
			'showDescription' => (bool) ( $args['desc'] ?? true ),
			'pinnedLabel'     => (string) ( $args['pinned'] ?? '' ),
			'listLabel'       => (string) ( $args['list'] ?? '' ),
			'title'           => (string) ( $args['title'] ?? '' ),
			'_page'           => max( 1, (int) ( $args['page'] ?? 1 ) ),
		);
	}

	/**
	 * Data.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @param \WP_Block            $block Block.
	 * @return array<string, mixed>
	 */
	protected function data( array $attrs, \WP_Block $block ): array {
		unset( $block );
		/** @var ContentProviderInterface $provider */
		$provider = $this->container->get( ContentProviderInterface::class );
		$per      = max( 1, min( 10, (int) ( $attrs['perPage'] ?? 2 ) ) );
		$page     = max( 1, (int) ( $attrs['_page'] ?? 1 ) );
		$first    = $provider->companies( 1, $per, true );
		$page     = min( $page, max( 1, $first->totalPages ) );
		$current  = 1 === $page ? $first : $provider->companies( $page, $per, true );
		return array(
			'pinned'      => $provider->pinnedCompany(),
			'companies'   => $current->items,
			'page'        => $page,
			'totalPages'  => $first->totalPages,
			'per'         => $per,
			'title'       => '' !== (string) ( $attrs['title'] ?? '' ) ? (string) $attrs['title'] : __( 'Companies', 'joo-core' ),
			'pinnedLabel' => '' !== (string) ( $attrs['pinnedLabel'] ?? '' ) ? (string) $attrs['pinnedLabel'] : __( 'Current company', 'joo-core' ),
			'listLabel'   => '' !== (string) ( $attrs['listLabel'] ?? '' ) ? (string) $attrs['listLabel'] : __( 'Companies I have worked on', 'joo-core' ),
			'showDesc'    => ! array_key_exists( 'showDescription', $attrs ) || (bool) $attrs['showDescription'],
			'endpoint'    => rest_url( 'joo/v1/companies' ),
			'_class'      => 'joo-companies',
		);
	}

	/**
	 * Formats a YYYY-MM month ("Mar 2022"); empty input gives an empty string.
	 *
	 * @param string $month Month.
	 */
	public static function month( string $month ): string {
		if ( 1 !== preg_match( '/^(\d{4})-(0[1-9]|1[0-2])$/', $month, $m ) ) {
			return '';
		}
		return (string) wp_date( 'M Y', (int) gmmktime( 0, 0, 0, (int) $m[2], 1, (int) $m[1] ) );
	}

	/**
	 * "Mar 2022 – Jun 2025" or "Mar 2022 – Present".
	 *
	 * @param string $from From month.
	 * @param string $to   To month (empty = present).
	 */
	public static function range( string $from, string $to ): string {
		$start = self::month( $from );
		$end   = '' === $to ? __( 'Present', 'joo-core' ) : self::month( $to );
		return '' === $start ? $end : $start . ' – ' . $end;
	}
}
