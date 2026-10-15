<?php
/**
 * Owner profile.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Content\Model;

/**
 * Owner profile. Immutable.
 *
 * @api
 */
final class Profile {

	/**
	 * Display name.
	 *
	 * @var string
	 */
	public readonly string $name;

	/**
	 * Job title.
	 *
	 * @var string
	 */
	public readonly string $title;

	/**
	 * Short biography.
	 *
	 * @var string
	 */
	public readonly string $shortBio;

	/**
	 * Avatar URL.
	 *
	 * @var string
	 */
	public readonly string $avatarUrl;

	/**
	 * Public profile URL.
	 *
	 * @var string
	 */
	public readonly string $url;

	/**
	 * Social links as list of {label, url}.
	 *
	 * @var array<mixed>
	 */
	public readonly array $socials;

	/**
	 * Current company.
	 *
	 * @var ?Company
	 */
	public readonly ?Company $pinnedCompany;

	/**
	 * Creates the value.
	 *
	 * @param string $name Display name.
	 * @param string $title Job title.
	 * @param string $shortBio Short biography.
	 * @param string $avatarUrl Avatar URL.
	 * @param string $url Public profile URL.
	 * @param array<mixed> $socials Social links as list of {label, url}.
	 * @param ?Company $pinnedCompany Current company.
	 */
	public function __construct( string $name, string $title, string $shortBio, string $avatarUrl, string $url, array $socials, ?Company $pinnedCompany ) {
		$this->name          = $name;
		$this->title         = $title;
		$this->shortBio      = $shortBio;
		$this->avatarUrl     = $avatarUrl;
		$this->url           = $url;
		$this->socials       = $socials;
		$this->pinnedCompany = $pinnedCompany;
	}

	/**
	 * Array form used by the REST API.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'name'           => $this->name,
			'title'          => $this->title,
			'short_bio'      => $this->shortBio,
			'avatar_url'     => $this->avatarUrl,
			'url'            => $this->url,
			'socials'        => $this->socials,
			'pinned_company' => null === $this->pinnedCompany ? null : $this->pinnedCompany->toArray(),
		);
	}
}
