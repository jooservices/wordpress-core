<?php
/**
 * A company of the resume as a typed value.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Model;

/**
 * Immutable company read from the repository. `toPublic()` is the only projection visitors may see.
 */
final class CompanyRecord {

	/**
	 * Private fields the owner can switch to public one by one.
	 */
	public const PRIVATE_FIELDS = array( 'compensation', 'equity' );

	/**
	 * @param int                              $id           Post id.
	 * @param string                           $name         Company name.
	 * @param string                           $role         Main role.
	 * @param string                           $from         Start month.
	 * @param string                           $to           End month (empty = current).
	 * @param bool                             $pinned       Pinned current company.
	 * @param string                           $url          Website.
	 * @param int                              $logoId       Logo attachment.
	 * @param string                           $summary      Card summary.
	 * @param string                           $description  Limited rich text.
	 * @param string                           $city         City.
	 * @param string                           $country      Country.
	 * @param string                           $employment   One of Repeaters::EMPLOYMENT or empty.
	 * @param string                           $industry     Industry.
	 * @param bool                             $remote       Remote flag.
	 * @param list<array<string, string>>      $roles        Roles with period.
	 * @param list<array<string, string>>      $achievements Achievements.
	 * @param list<string>                     $technologies Technologies.
	 * @param array<string, mixed>             $custom       Owner-defined values by key.
	 * @param string                           $compensation Private.
	 * @param string                           $equity       Private.
	 * @param list<string>                     $publicFields Private fields the owner made public.
	 * @param int                              $menuOrder    Manual order.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly string $role,
		public readonly string $from,
		public readonly string $to,
		public readonly bool $pinned,
		public readonly string $url,
		public readonly int $logoId,
		public readonly string $summary,
		public readonly string $description,
		public readonly string $city,
		public readonly string $country,
		public readonly string $employment,
		public readonly string $industry,
		public readonly bool $remote,
		public readonly array $roles,
		public readonly array $achievements,
		public readonly array $technologies,
		public readonly array $custom,
		public readonly string $compensation,
		public readonly string $equity,
		public readonly array $publicFields,
		public readonly int $menuOrder
	) {}

	/**
	 * Whether this is a current company (no end month).
	 */
	public function isCurrent(): bool {
		return '' === $this->to;
	}

	/**
	 * Months worked at the company.
	 *
	 * @param string $now Current month `YYYY-MM`.
	 */
	public function months( string $now ): int {
		return Period::months( $this->from, $this->to, $now );
	}

	/**
	 * Duration as years and months, for example "2 yrs 3 mos".
	 *
	 * @param string $now Current month `YYYY-MM`.
	 */
	public function durationLabel( string $now ): string {
		$parts = Period::parts( $this->months( $now ) );
		$out   = array();
		if ( $parts['years'] > 0 ) {
			/* translators: %d: number of years. */
			$out[] = sprintf( _n( '%d yr', '%d yrs', $parts['years'], 'jooservices-resume' ), $parts['years'] );
		}
		if ( $parts['months'] > 0 || array() === $out ) {
			/* translators: %d: number of months. */
			$out[] = sprintf( _n( '%d mo', '%d mos', $parts['months'], 'jooservices-resume' ), $parts['months'] );
		}
		return implode( ' ', $out );
	}

	/**
	 * The fields a visitor may see. Compensation and equity appear only when the owner switched them to public; custom
	 * values only when their definition is public.
	 *
	 * @param list<array<string, mixed>> $definitions Custom field definitions.
	 * @param string                     $now         Current month `YYYY-MM`.
	 * @return array<string, mixed>
	 */
	public function toPublic( array $definitions, string $now ): array {
		$public = array(
			'id'           => $this->id,
			'name'         => $this->name,
			'role'         => $this->role,
			'from'         => $this->from,
			'to'           => $this->to,
			'current'      => $this->isCurrent(),
			'duration'     => $this->durationLabel( $now ),
			'pinned'       => $this->pinned,
			'url'          => $this->url,
			'logo_id'      => $this->logoId,
			'summary'      => $this->summary,
			'description'  => $this->description,
			'location'     => array(
				'city'    => $this->city,
				'country' => $this->country,
			),
			'employment'   => $this->employment,
			'industry'     => $this->industry,
			'remote'       => $this->remote,
			'roles'        => $this->roles,
			'achievements' => $this->achievements,
			'technologies' => $this->technologies,
			'custom'       => self::publicCustom( $this->custom, $definitions ),
		);
		foreach ( self::PRIVATE_FIELDS as $field ) {
			if ( in_array( $field, $this->publicFields, true ) && '' !== $this->{$field} ) {
				$public[ $field ] = $this->{$field};
			}
		}
		return $public;
	}

	/**
	 * Flat row with every field (the own export format and the importer share it). Includes private fields: only for
	 * the owner.
	 *
	 * @return array<string, mixed>
	 */
	public function toRow(): array {
		return array(
			'name'          => $this->name,
			'role'          => $this->role,
			'from'          => $this->from,
			'to'            => $this->to,
			'pinned'        => $this->pinned,
			'url'           => $this->url,
			'summary'       => $this->summary,
			'description'   => $this->description,
			'city'          => $this->city,
			'country'       => $this->country,
			'employment'    => $this->employment,
			'industry'      => $this->industry,
			'remote'        => $this->remote,
			'roles'         => $this->roles,
			'achievements'  => $this->achievements,
			'technologies'  => $this->technologies,
			'custom'        => $this->custom,
			'compensation'  => $this->compensation,
			'equity'        => $this->equity,
			'public_fields' => $this->publicFields,
			'menu_order'    => $this->menuOrder,
		);
	}

	/**
	 * Keeps the custom values whose definition is public.
	 *
	 * @param array<string, mixed>       $values      Values by key.
	 * @param list<array<string, mixed>> $definitions Definitions.
	 * @return array<string, mixed>
	 */
	public static function publicCustom( array $values, array $definitions ): array {
		$out = array();
		foreach ( $definitions as $definition ) {
			$key = (string) $definition['key'];
			if ( ! empty( $definition['public'] ) && array_key_exists( $key, $values ) ) {
				$out[ $key ] = $values[ $key ];
			}
		}
		return $out;
	}
}
