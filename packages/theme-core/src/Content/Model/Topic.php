<?php
/**
 * Featured topic (tag).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Content\Model;

/**
 * Featured topic (tag). Immutable.
 *
 * @internal
 */
final class Topic {

	/**
	 * Term id.
	 *
	 * @var int
	 */
	public readonly int $id;

	/**
	 * Slug.
	 *
	 * @var string
	 */
	public readonly string $slug;

	/**
	 * Name.
	 *
	 * @var string
	 */
	public readonly string $name;

	/**
	 * Archive URL.
	 *
	 * @var string
	 */
	public readonly string $url;

	/**
	 * Published posts.
	 *
	 * @var int
	 */
	public readonly int $count;

	/**
	 * Short intro.
	 *
	 * @var string
	 */
	public readonly string $intro;

	/**
	 * Small image.
	 *
	 * @var ?ImageRef
	 */
	public readonly ?ImageRef $image;

	/**
	 * Creates the value.
	 *
	 * @param int $id Term id.
	 * @param string $slug Slug.
	 * @param string $name Name.
	 * @param string $url Archive URL.
	 * @param int $count Published posts.
	 * @param string $intro Short intro.
	 * @param ?ImageRef $image Small image.
	 */
	public function __construct( int $id, string $slug, string $name, string $url, int $count, string $intro, ?ImageRef $image ) {
		$this->id    = $id;
		$this->slug  = $slug;
		$this->name  = $name;
		$this->url   = $url;
		$this->count = $count;
		$this->intro = $intro;
		$this->image = $image;
	}

	/**
	 * Array form used by the REST API.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'    => $this->id,
			'slug'  => $this->slug,
			'name'  => $this->name,
			'url'   => $this->url,
			'count' => $this->count,
			'intro' => $this->intro,
			'image' => null === $this->image ? null : $this->image->toArray(),
		);
	}
}
