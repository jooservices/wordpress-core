<?php
/**
 * Employer entry.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Content\Model;

/**
 * Employer entry. Immutable.
 *
 * @internal
 */
final class Company {

	/**
	 * Post id.
	 *
	 * @var int
	 */
	public readonly int $id;

	/**
	 * Company name.
	 *
	 * @var string
	 */
	public readonly string $name;

	/**
	 * Role.
	 *
	 * @var string
	 */
	public readonly string $role;

	/**
	 * Start month (YYYY-MM).
	 *
	 * @var string
	 */
	public readonly string $from;

	/**
	 * End month (YYYY-MM, empty = present).
	 *
	 * @var string
	 */
	public readonly string $to;

	/**
	 * Whether the role is current.
	 *
	 * @var bool
	 */
	public readonly bool $current;

	/**
	 * Whether the company is pinned.
	 *
	 * @var bool
	 */
	public readonly bool $pinned;

	/**
	 * Short description.
	 *
	 * @var string
	 */
	public readonly string $description;

	/**
	 * Website (https).
	 *
	 * @var string
	 */
	public readonly string $url;

	/**
	 * Logo.
	 *
	 * @var ?ImageRef
	 */
	public readonly ?ImageRef $logo;

	/**
	 * Creates the value.
	 *
	 * @param int $id Post id.
	 * @param string $name Company name.
	 * @param string $role Role.
	 * @param string $from Start month (YYYY-MM).
	 * @param string $to End month (YYYY-MM, empty = present).
	 * @param bool $current Whether the role is current.
	 * @param bool $pinned Whether the company is pinned.
	 * @param string $description Short description.
	 * @param string $url Website (https).
	 * @param ?ImageRef $logo Logo.
	 */
	public function __construct( int $id, string $name, string $role, string $from, string $to, bool $current, bool $pinned, string $description, string $url, ?ImageRef $logo ) {
		$this->id          = $id;
		$this->name        = $name;
		$this->role        = $role;
		$this->from        = $from;
		$this->to          = $to;
		$this->current     = $current;
		$this->pinned      = $pinned;
		$this->description = $description;
		$this->url         = $url;
		$this->logo        = $logo;
	}

	/**
	 * Array form used by the REST API.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'          => $this->id,
			'name'        => $this->name,
			'role'        => $this->role,
			'from'        => $this->from,
			'to'          => $this->to,
			'current'     => $this->current,
			'pinned'      => $this->pinned,
			'description' => $this->description,
			'url'         => $this->url,
			'logo'        => null === $this->logo ? null : $this->logo->toArray(),
		);
	}
}
