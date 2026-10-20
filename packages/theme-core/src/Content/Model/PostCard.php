<?php
/**
 * Compact post for lists and sliders.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Content\Model;

/**
 * Compact post for lists and sliders. Immutable.
 *
 * @internal
 */
final class PostCard {

	/**
	 * Post id.
	 *
	 * @var int
	 */
	public readonly int $id;

	/**
	 * Title.
	 *
	 * @var string
	 */
	public readonly string $title;

	/**
	 * Excerpt.
	 *
	 * @var string
	 */
	public readonly string $excerpt;

	/**
	 * Permalink.
	 *
	 * @var string
	 */
	public readonly string $url;

	/**
	 * Published (ISO 8601).
	 *
	 * @var string
	 */
	public readonly string $date;

	/**
	 * Modified (ISO 8601).
	 *
	 * @var string
	 */
	public readonly string $modified;

	/**
	 * Primary category name.
	 *
	 * @var string
	 */
	public readonly string $category;

	/**
	 * Estimated reading minutes.
	 *
	 * @var int
	 */
	public readonly int $readingMinutes;

	/**
	 * Image.
	 *
	 * @var ?ImageRef
	 */
	public readonly ?ImageRef $image;

	/**
	 * Creates the value.
	 *
	 * @param int $id Post id.
	 * @param string $title Title.
	 * @param string $excerpt Excerpt.
	 * @param string $url Permalink.
	 * @param string $date Published (ISO 8601).
	 * @param string $modified Modified (ISO 8601).
	 * @param string $category Primary category name.
	 * @param int $readingMinutes Estimated reading minutes.
	 * @param ?ImageRef $image Image.
	 */
	public function __construct( int $id, string $title, string $excerpt, string $url, string $date, string $modified, string $category, int $readingMinutes, ?ImageRef $image ) {
		$this->id             = $id;
		$this->title          = $title;
		$this->excerpt        = $excerpt;
		$this->url            = $url;
		$this->date           = $date;
		$this->modified       = $modified;
		$this->category       = $category;
		$this->readingMinutes = $readingMinutes;
		$this->image          = $image;
	}

	/**
	 * Array form used by the REST API.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'              => $this->id,
			'title'           => $this->title,
			'excerpt'         => $this->excerpt,
			'url'             => $this->url,
			'date'            => $this->date,
			'modified'        => $this->modified,
			'category'        => $this->category,
			'reading_minutes' => $this->readingMinutes,
			'image'           => null === $this->image ? null : $this->image->toArray(),
		);
	}
}
