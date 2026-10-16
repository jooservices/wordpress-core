<?php
/**
 * WP-CLI commands for demo import, structural comparison, and rollback.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Demo;

use RuntimeException;

/**
 * Buyer-facing command group. WP-CLI runs outside shared-hosting request time limits.
 */
final class DemoCommand {

	/**
	 * Registers the command group.
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'joo demo', self::class );
	}

	/**
	 * Shows a no-write package review or imports the package in progress batches.
	 *
	 * ## OPTIONS
	 *
	 * <package>
	 * : Path to the downloaded demo ZIP.
	 *
	 * [--dry-run]
	 * : Check the archive and conflicts without changing the site.
	 *
	 * [--batch-size=<count>]
	 * : Number of WXR records between progress checkpoints. Default: 10.
	 *
	 * ## EXAMPLES
	 *
	 *     wp joo demo import jooservices-demo-content.zip --dry-run
	 *     wp joo demo import jooservices-demo-content.zip --batch-size=10
	 *
	 * @param list<string>             $args       Positional arguments.
	 * @param array<string, string|bool> $assoc_args Associative arguments.
	 */
	public function import( array $args, array $assoc_args ): void {
		$path     = $this->packagePath( $args );
		$importer = new DemoImporter();
		try {
			if ( isset( $assoc_args['dry-run'] ) ) {
				$report = $importer->inspect( $path );
				\WP_CLI::line( (string) wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
				if ( array() !== $report['conflicts'] ) {
					\WP_CLI::error( 'Dry run found conflicts. Resolve them or use a clean site, then retry.' );
				}
				\WP_CLI::success( $report['alreadyImported'] ? 'This package is already imported.' : 'Dry run passed. No site data was changed.' );
				return;
			}
			$batch_size = isset( $assoc_args['batch-size'] ) ? absint( $assoc_args['batch-size'] ) : 10;
			$result     = $importer->import(
				$path,
				false,
				max( 1, min( 100, $batch_size ) ),
				static function ( int $processed, int $total ): void {
					\WP_CLI::log( sprintf( 'Imported %d of %d WXR records.', $processed, $total ) );
				}
			);
			if ( 'already-imported' === $result['status'] ) {
				\WP_CLI::success( 'This package is already imported; no content was duplicated.' );
				return;
			}
			\WP_CLI::line( (string) wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			\WP_CLI::success( 'Demo import completed. Run `wp joo demo diff` to verify the structure.' );
		} catch ( RuntimeException $error ) {
			\WP_CLI::error( $error->getMessage() );
		}
	}

	/**
	 * Compares the installed demo with the structural baseline in its package.
	 *
	 * ## OPTIONS
	 *
	 * <package>
	 * : Path to the imported demo ZIP.
	 *
	 * ## EXAMPLES
	 *
	 *     wp joo demo diff jooservices-demo-content.zip
	 *
	 * @param list<string>              $args       Positional arguments.
	 * @param array<string, string|bool> $assoc_args Associative arguments.
	 */
	public function diff( array $args, array $assoc_args ): void {
		unset( $assoc_args );
		try {
			$result = ( new DemoImporter() )->diff( $this->packagePath( $args ) );
			\WP_CLI::line( (string) wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			if ( array() !== $result['differences'] ) {
				\WP_CLI::error( sprintf( 'Structural diff found %d difference(s).', count( $result['differences'] ) ) );
			}
			\WP_CLI::success( 'Structural diff: zero differences.' );
		} catch ( RuntimeException $error ) {
			\WP_CLI::error( $error->getMessage() );
		}
	}

	/**
	 * Removes only the objects created by the current demo package.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp joo demo rollback
	 *     wp joo demo rollback --yes
	 *
	 * @param list<string>              $args       Positional arguments.
	 * @param array<string, string|bool> $assoc_args Associative arguments.
	 */
	public function rollback( array $args, array $assoc_args ): void {
		unset( $args );
		$ledger = get_option( DemoImporter::LEDGER_OPTION, array() );
		$yes    = isset( $assoc_args['yes'] ) && true === $assoc_args['yes'];
		if ( is_array( $ledger ) && isset( $ledger['hash'] ) && ! $yes ) {
			\WP_CLI::confirm( 'Remove only items created by the current demo package?' );
		}
		$result = ( new DemoImporter() )->rollback();
		\WP_CLI::line( (string) wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		\WP_CLI::success( 'Demo rollback completed.' );
	}

	/**
	 * Resolves an existing local path.
	 *
	 * @param list<string> $args Positional arguments.
	 */
	private function packagePath( array $args ): string {
		$path = isset( $args[0] ) ? realpath( $args[0] ) : false;
		if ( false === $path || ! is_file( $path ) ) {
			throw new RuntimeException( 'Pass the path to the downloaded demo ZIP.' );
		}
		return $path;
	}
}
