<?php
/**
 * Block joo/category-tree.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\FragmentBlock;
use JOOservices\ThemeCore\Content\ContentProviderInterface;
use JOOservices\ThemeCore\Content\Model\CategoryNode;
use JOOservices\ThemeCore\Seo\Text;

/**
 * Nested category list with counts and descriptions. All branches are visible without JavaScript; the view module
 * collapses them (the branch holding the current page stays open).
 *
 * @internal
 */
final class CategoryTree extends FragmentBlock {

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/category-tree';
	}

	/**
	 * Fragment name.
	 */
	public static function fragment(): string {
		return 'category-tree';
	}

	/**
	 * Fragment height.
	 */
	public static function height(): int {
		return 360;
	}

	/**
	 * Fragment schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function schema(): array {
		return array(
			'counts'  => array(
				'type'    => 'bool',
				'default' => true,
			),
			'desc'    => array(
				'type'    => 'bool',
				'default' => true,
			),
			'open'    => array(
				'type'    => 'int',
				'default' => 0,
				'min'     => 0,
				'max'     => 3,
			),
			'empty'   => array(
				'type'    => 'bool',
				'default' => true,
			),
			'depth'   => array(
				'type'    => 'int',
				'default' => 3,
				'min'     => 1,
				'max'     => 4,
			),
			'current' => array(
				'type'    => 'int',
				'default' => 0,
				'min'     => 0,
			),
			'title'   => array(
				'type'      => 'string',
				'maxLength' => 60,
			),
		);
	}

	/**
	 * Fragment arguments.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @return array<string, int|string|bool>
	 */
	protected function fragmentArgs( array $attrs ): array {
		$current = 0;
		if ( is_category() ) {
			$current = (int) get_queried_object_id();
		} elseif ( is_singular( 'post' ) ) {
			$cats    = get_the_category();
			$current = array() === $cats ? 0 : (int) $cats[0]->term_id;
		}
		return array(
			'counts'  => ! array_key_exists( 'showCounts', $attrs ) || (bool) $attrs['showCounts'],
			'desc'    => ! array_key_exists( 'showDescriptions', $attrs ) || (bool) $attrs['showDescriptions'],
			'open'    => max( 0, min( 3, (int) ( $attrs['expandedDepth'] ?? 0 ) ) ),
			'empty'   => ! array_key_exists( 'hideEmpty', $attrs ) || (bool) $attrs['hideEmpty'],
			'depth'   => max( 1, min( 4, (int) ( $attrs['maxDepth'] ?? 3 ) ) ),
			'current' => $current,
			'title'   => (string) ( $attrs['title'] ?? '' ),
		);
	}

	/**
	 * Attributes from fragment arguments.
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @return array<string, mixed>
	 */
	protected function attrsFromArgs( array $args ): array {
		return array(
			'showCounts'       => (bool) ( $args['counts'] ?? true ),
			'showDescriptions' => (bool) ( $args['desc'] ?? true ),
			'expandedDepth'    => (int) ( $args['open'] ?? 0 ),
			'hideEmpty'        => (bool) ( $args['empty'] ?? true ),
			'maxDepth'         => (int) ( $args['depth'] ?? 3 ),
			'title'            => (string) ( $args['title'] ?? '' ),
			'_current'         => (int) ( $args['current'] ?? 0 ),
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
		$tree     = $provider->categoryTree( ! array_key_exists( 'hideEmpty', $attrs ) || (bool) $attrs['hideEmpty'] );
		$current  = (int) ( $attrs['_current'] ?? 0 );
		$max      = max( 1, min( 4, (int) ( $attrs['maxDepth'] ?? 3 ) ) );
		return array(
			'nodes'      => $this->prepare( $tree, 1, $max, $current, (int) ( $attrs['expandedDepth'] ?? 0 ) ),
			'showCounts' => ! array_key_exists( 'showCounts', $attrs ) || (bool) $attrs['showCounts'],
			'showDesc'   => ! array_key_exists( 'showDescriptions', $attrs ) || (bool) $attrs['showDescriptions'],
			'expanded'   => max( 0, min( 3, (int) ( $attrs['expandedDepth'] ?? 0 ) ) ),
			'title'      => '' !== (string) ( $attrs['title'] ?? '' ) ? (string) $attrs['title'] : __( 'Categories', 'joo-core' ),
			'_class'     => 'joo-tree',
		);
	}

	/**
	 * Flattens nodes beyond the maximum depth and marks the current branch.
	 *
	 * @param list<CategoryNode> $nodes    Nodes.
	 * @param int                $depth    Depth of this level (1-based).
	 * @param int                $max      Maximum depth.
	 * @param int                $current  Current category id.
	 * @param int                $expanded Depth that starts expanded.
	 * @return list<array<string, mixed>>
	 */
	private function prepare( array $nodes, int $depth, int $max, int $current, int $expanded ): array {
		$out = array();
		foreach ( $nodes as $node ) {
			/** @var list<CategoryNode> $kids */
			$kids     = array_values( $node->children );
			$children = $depth < $max ? $this->prepare( $kids, $depth + 1, $max, $current, $expanded ) : array();
			$inBranch = $node->id === $current || $this->contains( $children, $current );
			$out[]    = array(
				'id'       => $node->id,
				'name'     => $node->name,
				'url'      => $node->url,
				'count'    => $node->count,
				'desc'     => Text::truncate( '' !== $node->shortDescription ? $node->shortDescription : $node->description, 140 ),
				'fullDesc' => '' !== $node->shortDescription ? $node->shortDescription : $node->description,
				'current'  => $node->id === $current,
				'open'     => $inBranch || $depth <= $expanded,
				'depth'    => $depth,
				'children' => $children,
			);
		}
		return $out;
	}

	/**
	 * Whether a prepared list contains a category id (at any depth).
	 *
	 * @param list<array<string, mixed>> $nodes Prepared nodes.
	 * @param int                        $id    Category id.
	 */
	private function contains( array $nodes, int $id ): bool {
		if ( $id < 1 ) {
			return false;
		}
		foreach ( $nodes as $node ) {
			/** @var list<array<string, mixed>> $kids */
			$kids = (array) $node['children'];
			if ( $node['id'] === $id || $this->contains( $kids, $id ) ) {
				return true;
			}
		}
		return false;
	}
}
