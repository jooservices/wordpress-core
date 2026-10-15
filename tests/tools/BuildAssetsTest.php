<?php
/**
 * Tests tools/build-assets.mjs on a throwaway package.
 *
 * @package JOOservices
 */

declare(strict_types=1);

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- the tests run the Node build tool.

namespace JOOservices\Tests\Tools;

use PHPUnit\Framework\TestCase;

final class BuildAssetsTest extends TestCase {

	private string $dir = '';

	protected function setUp(): void {
		// The tool resolves --root relative to the repository, so the sandbox lives inside it (git-ignored .tooling-tmp).
		$this->dir = dirname( __DIR__, 2 ) . '/.tooling-tmp/build-' . uniqid();
		mkdir( $this->dir . '/resources/js/lib', 0777, true );
		mkdir( $this->dir . '/resources/css', 0777, true );
		mkdir( $this->dir . '/resources/blocks/demo', 0777, true );
		file_put_contents( $this->dir . '/resources/js/app.js', "import { x } from './lib/lib.js';\nexport const y = x + 1;\nconsole.log(y);\n" );
		file_put_contents( $this->dir . '/resources/js/lib/lib.js', "export const x = 41;\n" );
		file_put_contents( $this->dir . '/resources/css/main.css', ".joo-a {\n  color: red;\n  &:hover { color: blue; }\n}\n" );
		file_put_contents( $this->dir . '/resources/blocks/demo/view.js', "export default () => 'demo';\n" );
		file_put_contents( $this->dir . '/resources/blocks/demo/style.css', ".joo-demo { display: block; }\n" );
	}

	protected function tearDown(): void {
		exec( 'rm -rf ' . escapeshellarg( $this->dir ) );
	}

	/**
	 * @return array{0: int, 1: string, 2: array<string, mixed>}
	 */
	private function build( string $extra = '' ): array {
		$root = dirname( __DIR__, 2 );
		exec( sprintf( 'cd %s && node tools/build-assets.mjs --root %s %s 2>&1', escapeshellarg( $root ), escapeshellarg( '.tooling-tmp/' . basename( $this->dir ) ), $extra ), $output, $code );
		$manifest = is_file( $this->dir . '/resources/build/assets.json' ) ? json_decode( (string) file_get_contents( $this->dir . '/resources/build/assets.json' ), true ) : array();
		return array( $code, implode( "\n", $output ), (array) $manifest );
	}

	public function test_builds_hashed_files_and_a_manifest_with_sizes(): void {
		list( $code, , $manifest ) = $this->build();
		$this->assertSame( 0, $code );
		$names = array_keys( $manifest['assets'] );
		$this->assertSame( array( 'app', 'blocks/demo/style', 'blocks/demo/view', 'main' ), $names );
		foreach ( $manifest['assets'] as $asset ) {
			$this->assertMatchesRegularExpression( '/-[A-Za-z0-9]{8}\.(js|css)$/', $asset['file'] );
			$this->assertFileExists( $this->dir . '/resources/build/' . $asset['file'] );
			$this->assertGreaterThan( 0, $asset['gzip'] );
		}
	}

	public function test_two_builds_produce_identical_names(): void {
		list( , , $first )  = $this->build();
		list( , , $second ) = $this->build();
		$this->assertSame( $first, $second );
	}

	public function test_css_nesting_is_processed_for_the_target_browsers(): void {
		list( , , $manifest ) = $this->build();
		$css                  = (string) file_get_contents( $this->dir . '/resources/build/' . $manifest['assets']['main']['file'] );
		$this->assertStringContainsString( '.joo-a', $css );
	}

	public function test_an_oversized_entry_fails_the_build(): void {
		file_put_contents( $this->dir . '/build.config.json', '{"maxJsGzip": 10}' );
		file_put_contents( $this->dir . '/resources/js/big.js', 'export const data = ' . json_encode( array_map( fn( $i ) => hash( 'sha256', (string) $i ), range( 1, 200 ) ) ) . ";\nconsole.log(data);\n" );
		list( $code, $output ) = $this->build();
		$this->assertSame( 1, $code );
		$this->assertStringContainsString( 'exceeds', $output );
	}

	public function test_shared_code_goes_into_chunks_not_into_each_entry(): void {
		file_put_contents( $this->dir . '/resources/js/second.js', "import { x } from './lib/lib.js';\nconsole.log(x);\n" );
		list( , , $manifest ) = $this->build();
		$this->assertArrayHasKey( 'second', $manifest['assets'] );
		$this->assertNotEmpty( glob( $this->dir . '/resources/build/js/chunks/*.js' ) ?: array(), 'a shared chunk is emitted' );
	}
}
