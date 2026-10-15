<?php
/**
 * Node of the category tree.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Content\Model;

/**
 * Node of the category tree. Immutable.
 *
 * @api
 */
final class CategoryNode {

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
	 * Full description.
	 *
	 * @var string
	 */
	public readonly string $description;

	/**
	 * Short description.
	 *
	 * @var string
	 */
	public readonly string $shortDescription;

	/**
	 * Published posts including descendants.
	 *
	 * @var int
	 */
	public readonly int $count;

	/**
	 * Archive URL.
	 *
	 * @var string
	 */
	public readonly string $url;

	/**
	 * Child nodes (list of CategoryNode).
	 *
	 * @var array<mixed>
	 */
	public readonly array $children;

	/**
	 * Creates the value.
	 *
	 * @param int $id Term id.
	 * @param string $slug Slug.
	 * @param string $name Name.
	 * @param string $description Full description.
	 * @param string $shortDescription Short description.
	 * @param int $count Published posts including descendants.
	 * @param string $url Archive URL.
	 * @param array<mixed> $children Child nodes (list of CategoryNode).
	 */
	public function __construct( int $id, string $slug, string $name, string $description, string $shortDescription, int $count, string $url, array $children ) {
		$this->id               = $id;
		$this->slug             = $slug;
		$this->name             = $name;
		$this->description      = $description;
		$this->shortDescription = $shortDescription;
		$this->count            = $count;
		$this->url              = $url;
		$this->children         = $children;
	}

	/**
	 * Array form used by the REST API.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'                => $this->id,
			'slug'              => $this->slug,
			'name'              => $this->name,
			'description'       => $this->description,
			'short_description' => $this->shortDescription,
			'count'             => $this->count,
			'url'               => $this->url,
			'children'          => array_map( static fn( CategoryNode $c ) => $c->toArray(), $this->children ),
		);
	}
}
