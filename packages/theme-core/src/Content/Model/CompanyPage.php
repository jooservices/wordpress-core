<?php
/**
 * One page of companies.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Content\Model;

/**
 * One page of companies. Immutable.
 *
 * @api
 */
final class CompanyPage {

	/**
	 * Companies on this page (list of Company).
	 *
	 * @var array<mixed>
	 */
	public readonly array $items;

	/**
	 * Total companies.
	 *
	 * @var int
	 */
	public readonly int $total;

	/**
	 * Total pages.
	 *
	 * @var int
	 */
	public readonly int $totalPages;

	/**
	 * Creates the value.
	 *
	 * @param array<mixed> $items Companies on this page (list of Company).
	 * @param int $total Total companies.
	 * @param int $totalPages Total pages.
	 */
	public function __construct( array $items, int $total, int $totalPages ) {
		$this->items      = $items;
		$this->total      = $total;
		$this->totalPages = $totalPages;
	}

	/**
	 * Array form used by the REST API.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'items'       => array_map( static fn( Company $c ) => $c->toArray(), $this->items ),
			'total'       => $this->total,
			'total_pages' => $this->totalPages,
		);
	}
}
