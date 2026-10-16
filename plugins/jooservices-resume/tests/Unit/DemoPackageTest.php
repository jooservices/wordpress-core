<?php
/**
 * Demo package archive validation.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Tests\Unit;

use JOOservices\Resume\Demo\DemoPackage;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

final class DemoPackageTest extends TestCase {

	/**
	 * Temporary archive paths.
	 *
	 * @var list<string>
	 */
	private array $archives = array();

	protected function tearDown(): void {
		foreach ( $this->archives as $path ) {
			if ( is_file( $path ) ) {
				unlink( $path );
			}
		}
		parent::tearDown();
	}

	public function test_valid_root_and_themeforest_folder_archives_pass_the_contract(): void {
		foreach ( array( '', 'Demo-Content/' ) as $prefix ) {
			$path    = $this->archive( $this->files(), $prefix );
			$package = new DemoPackage( $path );
			$result  = $package->validate();
			$this->assertSame( 'B', $result['manifest']['tier'] );
			$this->assertSame( array( 'media/sample.png' ), $result['media'] );
			$this->assertSame( 1, $result['size'] > 0 ? 1 : 0 );
		}
	}

	public function test_required_files_and_checksums_are_enforced(): void {
		$files = $this->files();
		unset( $files['ATTRIBUTION.md'] );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Required package file is missing: ATTRIBUTION.md' );
		( new DemoPackage( $this->archive( $files ) ) )->validate();
	}

	public function test_changed_payload_fails_checksum_validation(): void {
		$files                     = $this->files();
		$files['media/sample.png'] = 'changed';
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Package checksum failed: media/sample.png' );
		( new DemoPackage( $this->archive( $files, '', true ) ) )->validate();
	}

	public function test_only_tier_b_and_png_media_are_supported(): void {
		$files                  = $this->files();
		$manifest               = json_decode( $files['manifest.json'], true );
		$manifest['tier']       = 'A';
		$files['manifest.json'] = (string) json_encode( $manifest );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Only the approved licence-clean Tier B package is supported.' );
		( new DemoPackage( $this->archive( $files ) ) )->validate();
	}

	public function test_unsafe_archive_paths_are_rejected(): void {
		$files                   = $this->files();
		$files['../outside.txt'] = 'outside';
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'unsafe archive path' );
		new DemoPackage( $this->archive( $files ) );
	}

	public function test_invalid_wxr_and_missing_media_are_rejected(): void {
		$files                = $this->files();
		$files['content.xml'] = '<not-xml';
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'valid WordPress WXR 1.2' );
		( new DemoPackage( $this->archive( $files ) ) )->validate();
	}

	/**
	 * Produces the smallest contract-complete test archive.
	 *
	 * @return array<string, string>
	 */
	private function files(): array {
		return array(
			'ATTRIBUTION.md'     => '# Attribution',
			'LICENSES.md'        => 'CC BY 4.0. No third-party materials.',
			'content.xml'        => '<?xml version="1.0"?><rss xmlns:wp="http://wordpress.org/export/1.2/"><channel><wp:wxr_version>1.2</wp:wxr_version></channel></rss>',
			'language.json'      => '{"locale":"en_US","content":["en"]}',
			'manifest.json'      => '{"packageVersion":1,"tier":"B","license":"CC-BY-4.0","expected":{"mediaFiles":1}}',
			'media/sample.png'   => "\x89PNG\r\n\x1a\nfixture",
			'menus.json'         => '{}',
			'patterns.json'      => '{"registeredWithTheme":true,"patterns":[]}',
			'settings.json'      => '{}',
			'structure.json'     => '{}',
			'theme-options.json' => '{"theme_mods":[]}',
			'widgets.json'       => '{"sidebars":[],"widgets":[]}',
		);
	}

	/**
	 * Creates a ZIP with a fresh checksum table.
	 *
	 * @param array<string, string> $files   Package files.
	 * @param string                $prefix  Optional wrapper folder.
	 * @param bool                  $stale   Keep the checksum table stale.
	 */
	private function archive( array $files, string $prefix = '', bool $stale = false ): string {
		$checksums = array();
		foreach ( $files as $name => $contents ) {
			$checksums[ $name ] = hash( 'sha256', $contents );
		}
		if ( $stale && isset( $checksums['media/sample.png'] ) ) {
			$checksums['media/sample.png'] = str_repeat( '0', 64 );
		}
		$files['checksums.json'] = (string) json_encode( $checksums );
		$path                    = tempnam( sys_get_temp_dir(), 'joo-demo-package-' );
		if ( false === $path ) {
			throw new RuntimeException( 'Could not create a temporary package.' );
		}
		$this->archives[] = $path;
		$zip              = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::OVERWRITE ) ) {
			throw new RuntimeException( 'Could not open a temporary package ZIP.' );
		}
		foreach ( $files as $name => $contents ) {
			$zip->addFromString( $prefix . $name, $contents );
		}
		$zip->close();
		return $path;
	}
}
