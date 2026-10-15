<?php
/**
 * JSON-LD graph engine.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Seo;

use Brain\Monkey\Functions;
use JOOservices\TestKit\UnitTestCase;
use JOOservices\ThemeCore\Seo\Context;
use JOOservices\ThemeCore\Seo\Schema\Graph;
use JOOservices\ThemeCore\Seo\Schema\Piece;
use JOOservices\ThemeCore\Support\Logger;

final class GraphTest extends UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_json_encode' )->alias( static fn( $v, $f = 0 ) => json_encode( $v, $f ) );
	}

	/**
	 * @param array<string, mixed> $node Node.
	 */
	private function piece( string $id, array $node, bool $needed = true, bool $always = false, int $priority = 10 ): Piece {
		return new class( $id, $node, $needed, $always, $priority ) implements Piece {
			/** @var array<string, mixed> */
			private array $node;
			private string $id;
			private bool $needed;
			private bool $always;
			private int $priority;

			/** @param array<string, mixed> $node */
			public function __construct( string $id, array $node, bool $needed, bool $always, int $priority ) {
				$this->id       = $id;
				$this->node     = $node;
				$this->needed   = $needed;
				$this->always   = $always;
				$this->priority = $priority;
			}
			public function id( Context $context ): string {
				return $this->id;
			}
			public function isNeeded( Context $context ): bool {
				return $this->needed;
			}
			public function generate( Context $context ): array {
				return $this->node;
			}
			public function alwaysOn(): bool {
				return $this->always;
			}
			public function priority(): int {
				return $this->priority;
			}
		};
	}

	private function logger(): Logger {
		return new Logger( static fn() => true );
	}

	private function context(): Context {
		return new Context( 'single' );
	}

	public function test_empty_values_are_stripped_but_zero_and_false_stay(): void {
		$graph = new Graph(
			array(
				$this->piece(
					'https://x.test/#a',
					array(
						'@type'  => 'Thing',
						'name'   => '',
						'n'      => 0,
						'b'      => false,
						'nil'    => null,
						'list'   => array(),
						'nested' => array(
							'a' => '',
							'b' => 'keep',
						),
					)
				),
			),
			$this->logger()
		);
		$doc   = $graph->build( $this->context() );
		$this->assertSame(
			array(
				'@id'    => 'https://x.test/#a',
				'@type'  => 'Thing',
				'n'      => 0,
				'b'      => false,
				'nested' => array( 'b' => 'keep' ),
			),
			$doc['@graph'][0]
		);
		$this->assertSame( 'https://schema.org', $doc['@context'] );
	}

	public function test_duplicate_ids_keep_the_first_and_unneeded_pieces_are_skipped(): void {
		$graph = new Graph(
			array(
				$this->piece( 'https://x.test/#a', array( '@type' => 'A' ) ),
				$this->piece( 'https://x.test/#a', array( '@type' => 'B' ) ),
				$this->piece( 'https://x.test/#c', array( '@type' => 'C' ), false ),
			),
			$this->logger()
		);
		$doc   = $graph->build( $this->context() );
		$this->assertCount( 1, $doc['@graph'] );
		$this->assertSame( 'A', $doc['@graph'][0]['@type'] );
	}

	public function test_dangling_references_are_removed_and_logged(): void {
		$logger = $this->logger();
		$graph  = new Graph(
			array(
				$this->piece(
					'https://x.test/#a',
					array(
						'@type' => 'A',
						'ok'    => array( '@id' => 'https://x.test/#b' ),
						'gone'  => array( '@id' => 'https://x.test/#missing' ),
						'many'  => array( array( '@id' => 'https://x.test/#b' ), array( '@id' => 'https://x.test/#nope' ) ),
					)
				),
				$this->piece( 'https://x.test/#b', array( '@type' => 'B' ) ),
			),
			$logger
		);
		$a      = $graph->build( $this->context() )['@graph'][0];
		$this->assertArrayHasKey( 'ok', $a );
		$this->assertArrayNotHasKey( 'gone', $a );
		$this->assertSame( array( array( '@id' => 'https://x.test/#b' ) ), $a['many'] );
		$this->assertNotEmpty( array_filter( $logger->entries(), static fn( $e ) => str_contains( json_encode( $e ), 'reference removed' ) ) );
	}

	public function test_only_always_on_pieces_when_an_seo_plugin_owns_the_graph(): void {
		$graph = new Graph( array( $this->piece( 'https://x.test/#a', array( '@type' => 'A' ) ), $this->piece( 'https://x.test/#b', array( '@type' => 'B' ), true, true ) ), $this->logger() );
		$doc   = $graph->build( $this->context(), true );
		$this->assertCount( 1, $doc['@graph'] );
		$this->assertSame( 'B', $doc['@graph'][0]['@type'] );
	}

	public function test_nothing_needed_returns_null(): void {
		$graph = new Graph( array( $this->piece( 'https://x.test/#a', array( '@type' => 'A' ), false ) ), $this->logger() );
		$this->assertNull( $graph->build( $this->context() ) );
	}

	public function test_size_guard_drops_low_priority_nodes_first(): void {
		$big   = str_repeat( 'x', 40000 );
		$graph = new Graph(
			array(
				$this->piece(
					'https://x.test/#keep',
					array(
						'@type' => 'K',
						'text'  => $big,
					),
					true,
					false,
					1
				),
				$this->piece(
					'https://x.test/#drop',
					array(
						'@type' => 'D',
						'text'  => $big,
					),
					true,
					false,
					20
				),
			),
			$this->logger()
		);
		$doc   = $graph->build( $this->context() );
		$this->assertCount( 1, $doc['@graph'] );
		$this->assertSame( 'K', $doc['@graph'][0]['@type'] );
	}

	public function test_encoding_never_contains_a_raw_script_end_tag(): void {
		$json = Graph::encode(
			array(
				'name' => '</script><script>alert(1)</script> & "q"',
				'url'  => 'https://x.test/a/b',
			)
		);
		$this->assertStringNotContainsString( '</script>', $json );
		$this->assertStringNotContainsString( '<', $json );
		$this->assertStringContainsString( 'https://x.test/a/b', $json, 'slashes stay readable' );
		$this->assertSame( '</script><script>alert(1)</script> & "q"', json_decode( $json, true )['name'] );
	}
}
