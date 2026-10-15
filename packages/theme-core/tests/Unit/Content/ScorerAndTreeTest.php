<?php
/**
 * Related scoring and category tree building.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Content;

use JOOservices\ThemeCore\Content\Model\CategoryNode;
use JOOservices\ThemeCore\Content\RelatedScorer;
use JOOservices\ThemeCore\Content\TreeBuilder;
use PHPUnit\Framework\TestCase;

final class ScorerAndTreeTest extends TestCase {

	private function source(): array {
		return array(
			'primary'   => 10,
			'tags'      => array( 1, 2 ),
			'ancestors' => array( 10, 5 ),
		);
	}

	public function test_score_weights(): void {
		$s = $this->source();
		$this->assertSame(
			3 + 1,
			RelatedScorer::score(
				$s,
				array(
					'categories' => array( 10 ),
					'tags'       => array(),
				)
			),
			'primary + ancestor'
		);
		$this->assertSame(
			4,
			RelatedScorer::score(
				$s,
				array(
					'categories' => array( 99 ),
					'tags'       => array( 1, 2 ),
				)
			),
			'two shared tags'
		);
		$this->assertSame(
			1,
			RelatedScorer::score(
				$s,
				array(
					'categories' => array( 5 ),
					'tags'       => array(),
				)
			),
			'shared ancestor only'
		);
		$this->assertSame(
			0,
			RelatedScorer::score(
				$s,
				array(
					'categories' => array( 77 ),
					'tags'       => array( 9 ),
				)
			)
		);
		$this->assertSame(
			3 + 2 + 1,
			RelatedScorer::score(
				$s,
				array(
					'categories' => array( 10 ),
					'tags'       => array( 1 ),
				)
			) - 0
		);
	}

	public function test_rank_orders_by_score_then_newest_then_id_and_is_deterministic(): void {
		$c     = array(
			array(
				'id'         => 1,
				'date'       => 100,
				'categories' => array( 10 ),
				'tags'       => array(),
			),
			array(
				'id'         => 2,
				'date'       => 300,
				'categories' => array( 10 ),
				'tags'       => array(),
			),
			array(
				'id'         => 3,
				'date'       => 300,
				'categories' => array( 10 ),
				'tags'       => array(),
			),
			array(
				'id'         => 4,
				'date'       => 50,
				'categories' => array( 10 ),
				'tags'       => array( 1, 2 ),
			),
			array(
				'id'         => 5,
				'date'       => 999,
				'categories' => array( 77 ),
				'tags'       => array(),
			),
		);
		$first = RelatedScorer::rank( $this->source(), $c, 10 );
		$this->assertSame( array( 4, 3, 2, 1 ), $first, 'score desc, then date desc, then id desc; zero scores dropped' );
		$this->assertSame( $first, RelatedScorer::rank( $this->source(), array_reverse( $c ), 10 ) );
		$this->assertSame( array( 4, 3 ), RelatedScorer::rank( $this->source(), $c, 2 ) );
	}

	public function test_rank_falls_back_to_unscored_candidates_when_nothing_matches(): void {
		$c = array(
			array(
				'id'         => 1,
				'date'       => 1,
				'categories' => array( 77 ),
				'tags'       => array(),
			),
			array(
				'id'         => 2,
				'date'       => 2,
				'categories' => array( 78 ),
				'tags'       => array(),
			),
		);
		$this->assertSame( array( 2, 1 ), RelatedScorer::rank( $this->source(), $c, 5 ) );
		$this->assertSame( array(), RelatedScorer::rank( $this->source(), array(), 5 ) );
		$this->assertSame( array(), RelatedScorer::rank( $this->source(), $c, 0 ) );
	}

	/**
	 * @return list<array{id: int, parent: int, slug: string, name: string, description: string, short: string, count: int, url: string}>
	 */
	private function flat(): array {
		$t = static fn( int $id, int $parent, string $name, int $count ) => array(
			'id'          => $id,
			'parent'      => $parent,
			'slug'        => strtolower( $name ),
			'name'        => $name,
			'description' => '',
			'short'       => '',
			'count'       => $count,
			'url'         => 'https://x.test/' . $id . '/',
		);
		return array(
			$t( 1, 0, 'Uncategorized', 0 ),
			$t( 2, 0, 'Research', 1 ),
			$t( 3, 2, 'Homelab', 4 ),
			$t( 4, 3, 'Rack', 2 ),
			$t( 5, 2, 'Empty child', 0 ),
			$t( 6, 0, 'Alpha', 3 ),
		);
	}

	public function test_tree_nests_counts_cumulatively_sorts_by_name_and_hides_empty(): void {
		$tree = TreeBuilder::build( $this->flat() );
		$this->assertSame( array( 'Alpha', 'Research' ), array_map( static fn( CategoryNode $n ) => $n->name, $tree ), 'Uncategorized is empty and hidden' );
		$research = $tree[1];
		$this->assertSame( 1 + 4 + 2, $research->count );
		$this->assertSame( array( 'Homelab' ), array_map( static fn( CategoryNode $n ) => $n->name, $research->children ), 'empty child hidden' );
		$this->assertSame( 6, $research->children[0]->count );
		$this->assertSame( 'Rack', $research->children[0]->children[0]->name );
	}

	public function test_tree_can_keep_empty_nodes_and_survives_orphans_and_cycles(): void {
		$all = TreeBuilder::build( $this->flat(), false );
		$this->assertCount( 3, $all );
		$orphan = TreeBuilder::build(
			array(
				array(
					'id'          => 9,
					'parent'      => 404,
					'slug'        => 'o',
					'name'        => 'Orphan',
					'description' => '',
					'short'       => '',
					'count'       => 1,
					'url'         => '',
				),
			)
		);
		$this->assertSame( 'Orphan', $orphan[0]->name );
		$cycle = TreeBuilder::build(
			array(
				array(
					'id'          => 1,
					'parent'      => 2,
					'slug'        => 'a',
					'name'        => 'A',
					'description' => '',
					'short'       => '',
					'count'       => 1,
					'url'         => '',
				),
				array(
					'id'          => 2,
					'parent'      => 1,
					'slug'        => 'b',
					'name'        => 'B',
					'description' => '',
					'short'       => '',
					'count'       => 1,
					'url'         => '',
				),
			)
		);
		$this->assertIsArray( $cycle, 'a cycle does not loop forever' );
	}

	public function test_node_array_form(): void {
		$tree = TreeBuilder::build( $this->flat() );
		$data = $tree[1]->toArray();
		$this->assertSame( array( 'id', 'slug', 'name', 'description', 'short_description', 'count', 'url', 'children' ), array_keys( $data ) );
		$this->assertSame( 'Homelab', $data['children'][0]['name'] );
	}
}
