<?php
/**
 * JSON-LD graph builder.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Seo\Schema;

use JOOservices\ThemeCore\Seo\Context;
use JOOservices\ThemeCore\Support\Hooks;
use JOOservices\ThemeCore\Support\Logger;

/**
 * Collects pieces, generates nodes, removes empty values, dedupes by id and drops dangling references.
 *
 * @internal
 */
final class Graph {

	/**
	 * Maximum size of the printed JSON in bytes.
	 */
	public const MAX_BYTES = 65536;

	/**
	 * Registered pieces.
	 *
	 * @var list<Piece>
	 */
	private array $pieces;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Creates the graph builder.
	 *
	 * @param list<Piece> $pieces Pieces.
	 * @param Logger      $logger Logger.
	 */
	public function __construct( array $pieces, Logger $logger ) {
		$this->pieces = $pieces;
		$this->logger = $logger;
	}

	/**
	 * Builds the document.
	 *
	 * @param Context $context      Context.
	 * @param bool    $onlyAlwaysOn Only pieces flagged alwaysOn() (used while an SEO plugin owns the graph).
	 * @return array<string, mixed>|null Null when nothing is needed.
	 */
	public function build( Context $context, bool $onlyAlwaysOn = false ): ?array {
		$nodes = array();
		foreach ( $this->collect() as $piece ) {
			if ( $onlyAlwaysOn && ! $piece->alwaysOn() ) {
				continue;
			}
			if ( ! $piece->isNeeded( $context ) ) {
				continue;
			}
			$id = $piece->id( $context );
			if ( '' === $id || isset( $nodes[ $id ] ) ) {
				continue;
			}
			$node = self::clean( $piece->generate( $context ) );
			if ( ! is_array( $node ) || array() === $node ) {
				continue;
			}
			$nodes[ $id ] = array(
				'piece'    => $piece,
				'node'     => array( '@id' => $id ) + $node,
				'priority' => $piece->priority(),
			);
		}
		if ( array() === $nodes ) {
			return null;
		}
		$nodes = $this->fitSize( $nodes );
		$known = array_keys( $nodes );
		$graph = array();
		foreach ( $nodes as $entry ) {
			$graph[] = $this->dropDanglingReferences( $entry['node'], $known );
		}
		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
	}

	/**
	 * Encodes a document for a script block (no raw "</script>", stable flags).
	 *
	 * @param array<string, mixed> $document Document.
	 * @param bool                 $pretty   Pretty print.
	 */
	public static function encode( array $document, bool $pretty = false ): string {
		$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | ( $pretty ? JSON_PRETTY_PRINT : 0 );
		return (string) wp_json_encode( $document, $flags );
	}

	/**
	 * Removes null, empty strings and empty arrays (keeps 0 and false).
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public static function clean( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$out = array();
		foreach ( $value as $key => $item ) {
			$item = self::clean( $item );
			if ( null === $item || '' === $item || array() === $item ) {
				continue;
			}
			$out[ $key ] = $item;
		}
		return $out;
	}

	/**
	 * Registered pieces plus those added through the filter (instances or instantiable class names).
	 *
	 * @return list<Piece>
	 */
	private function collect(): array {
		$pieces = $this->pieces;
		$extra  = Hooks::filter( 'joo/seo/graph/pieces', array() );
		foreach ( is_array( $extra ) ? $extra : array() as $item ) {
			if ( is_string( $item ) && class_exists( $item ) ) {
				$item = new $item();
			}
			if ( $item instanceof Piece ) {
				$pieces[] = $item;
			}
		}
		return $pieces;
	}

	/**
	 * Drops the least important nodes while the JSON is over the size limit.
	 *
	 * @param array<string, array{piece: Piece, node: array<string, mixed>, priority: int}> $nodes Nodes.
	 * @return array<string, array{piece: Piece, node: array<string, mixed>, priority: int}>
	 */
	private function fitSize( array $nodes ): array {
		$size = static fn( array $set ): int => strlen( (string) wp_json_encode( array_column( $set, 'node' ) ) );
		if ( $size( $nodes ) <= self::MAX_BYTES ) {
			return $nodes;
		}
		$order = $nodes;
		uasort( $order, static fn( array $a, array $b ) => $b['priority'] <=> $a['priority'] );
		foreach ( array_keys( $order ) as $id ) {
			if ( $size( $nodes ) <= self::MAX_BYTES ) {
				break;
			}
			if ( $nodes[ $id ]['priority'] <= 10 ) {
				break;
			}
			unset( $nodes[ $id ] );
			$this->logger->warning( 'Structured data too large, dropped node ' . $id );
		}
		return $nodes;
	}

	/**
	 * Removes `{"@id": X}` references whose target is not in the graph.
	 *
	 * @param array<string, mixed> $node  Node.
	 * @param list<string>         $known Known ids.
	 * @return array<string, mixed>
	 */
	private function dropDanglingReferences( array $node, array $known ): array {
		foreach ( $node as $key => $value ) {
			if ( '@id' === $key || ! is_array( $value ) ) {
				continue;
			}
			if ( 1 === count( $value ) && isset( $value['@id'] ) && is_string( $value['@id'] ) ) {
				if ( ! in_array( $value['@id'], $known, true ) ) {
					$this->logger->debug( 'Structured data reference removed: ' . $value['@id'] );
					unset( $node[ $key ] );
				}
				continue;
			}
			$node[ $key ] = $this->dropDanglingInList( $value, $known );
		}
		return $node;
	}

	/**
	 * Same for arrays that hold several references or nested objects.
	 *
	 * @param array<mixed> $list  List or object.
	 * @param list<string> $known Known ids.
	 * @return array<mixed>
	 */
	private function dropDanglingInList( array $list, array $known ): array {
		$out = array();
		foreach ( $list as $key => $item ) {
			if ( is_array( $item ) && 1 === count( $item ) && isset( $item['@id'] ) && is_string( $item['@id'] ) && ! in_array( $item['@id'], $known, true ) ) {
				continue;
			}
			$out[ $key ] = is_array( $item ) ? $this->dropDanglingInList( $item, $known ) : $item;
		}
		return array_keys( $list ) === range( 0, count( $list ) - 1 ) ? array_values( $out ) : $out;
	}
}
