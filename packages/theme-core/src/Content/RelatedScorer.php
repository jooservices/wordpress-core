<?php
/**
 * Related posts scoring.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Content;

/**
 * score = 3 x same primary category + 2 x shared tags + 1 x shared category ancestor; ties: newest, then highest id.
 * Pure, so the ranking is deterministic and unit-testable.
 *
 * @internal
 */
final class RelatedScorer {

	/**
	 * Score of one candidate.
	 *
	 * @param array{primary: int, tags: list<int>, ancestors: list<int>} $source    Source post.
	 * @param array{categories: list<int>, tags: list<int>}              $candidate Candidate.
	 */
	public static function score( array $source, array $candidate ): int {
		$score = 0;
		if ( $source['primary'] > 0 && in_array( $source['primary'], $candidate['categories'], true ) ) {
			$score += 3;
		}
		$score += 2 * count( array_intersect( $source['tags'], $candidate['tags'] ) );
		if ( array() !== array_intersect( $source['ancestors'], $candidate['categories'] ) ) {
			++$score;
		}
		return $score;
	}

	/**
	 * Ranks candidates (score desc, date desc, id desc) and drops those with score 0 unless nothing scores.
	 *
	 * @param array{primary: int, tags: list<int>, ancestors: list<int>}                                           $source     Source post.
	 * @param list<array{id: int, date: int, categories: list<int>, tags: list<int>}>                              $candidates Candidates.
	 * @param int                                                                                                  $limit      Maximum results.
	 * @return list<int> Post ids.
	 */
	public static function rank( array $source, array $candidates, int $limit ): array {
		$scored = array();
		foreach ( $candidates as $candidate ) {
			$scored[] = array(
				'id'    => $candidate['id'],
				'date'  => $candidate['date'],
				'score' => self::score( $source, $candidate ),
			);
		}
		usort(
			$scored,
			static fn( array $a, array $b ) => array( $b['score'], $b['date'], $b['id'] ) <=> array( $a['score'], $a['date'], $a['id'] )
		);
		$positive = array_values( array_filter( $scored, static fn( array $row ) => $row['score'] > 0 ) );
		$use      = array() === $positive ? $scored : $positive;
		return array_map( static fn( array $row ) => $row['id'], array_slice( $use, 0, max( 0, $limit ) ) );
	}
}
