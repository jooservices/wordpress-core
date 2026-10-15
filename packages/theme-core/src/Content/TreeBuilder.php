<?php
/**
 * Category tree from flat terms.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Content;

use JOOservices\ThemeCore\Content\Model\CategoryNode;

/**
 * Builds nested nodes with cumulative counts in PHP from a single flat list.
 *
 * @internal
 */
final class TreeBuilder {

	/**
	 * Builds the tree.
	 *
	 * @param list<array{id: int, parent: int, slug: string, name: string, description: string, short: string, count: int, url: string}> $flat      Flat terms.
	 * @param bool                                                                                                                         $hideEmpty Hide nodes whose cumulative count is 0.
	 * @return list<CategoryNode>
	 */
	public static function build( array $flat, bool $hideEmpty = true ): array {
		$byParent = array();
		foreach ( $flat as $term ) {
			$byParent[ $term['parent'] ][] = $term;
		}
		$known = array_column( $flat, 'id' );
		// Orphans (parent missing from the list) are treated as roots.
		foreach ( $flat as $term ) {
			if ( $term['parent'] > 0 && ! in_array( $term['parent'], $known, true ) ) {
				$byParent[0][] = $term;
			}
		}
		return self::level( $byParent, 0, $hideEmpty, array() );
	}

	/**
	 * Builds one level.
	 *
	 * @param array<int, list<array{id: int, parent: int, slug: string, name: string, description: string, short: string, count: int, url: string}>> $byParent  Terms grouped by parent.
	 * @param int                                                                                                                                      $parent    Parent id.
	 * @param bool                                                                                                                                     $hideEmpty Hide empty.
	 * @param list<int>                                                                                                                                $path      Ids on the way here (cycle guard).
	 * @return list<CategoryNode>
	 */
	private static function level( array $byParent, int $parent, bool $hideEmpty, array $path ): array {
		$nodes = array();
		$terms = $byParent[ $parent ] ?? array();
		usort( $terms, static fn( array $a, array $b ) => strcasecmp( $a['name'], $b['name'] ) );
		foreach ( $terms as $term ) {
			if ( in_array( $term['id'], $path, true ) ) {
				continue;
			}
			$children = self::level( $byParent, $term['id'], $hideEmpty, array_merge( $path, array( $term['id'] ) ) );
			$count    = $term['count'];
			foreach ( $children as $child ) {
				$count += $child->count;
			}
			if ( $hideEmpty && 0 === $count ) {
				continue;
			}
			$nodes[] = new CategoryNode( $term['id'], $term['slug'], $term['name'], $term['description'], $term['short'], $count, $term['url'], $children );
		}
		return $nodes;
	}
}
