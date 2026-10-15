<?php
/**
 * Lists the @api symbols of the core as markdown (the public API baseline).
 *
 * Usage: php tools/api-list.php [core-dir] > docs/api/baseline-<version>.md
 *
 * @package JOOservices
 */

$dir     = rtrim( $argv[1] ?? dirname( __DIR__ ) . '/packages/theme-core', '/' );
$version = json_decode( (string) file_get_contents( $dir . '/composer.json' ), true )['version'] ?? '0.0.0';
$rows    = array();
$iter    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir . '/src', FilesystemIterator::SKIP_DOTS ) );
foreach ( $iter as $file ) {
	if ( ! str_ends_with( $file->getPathname(), '.php' ) ) {
		continue;
	}
	$source = (string) file_get_contents( $file->getPathname() );
	if ( 1 !== preg_match( '/^namespace\s+([^;]+);/m', $source, $ns ) ) {
		continue;
	}
	if ( 1 === preg_match( '/\/\*\*(?:(?!\*\/).)*@api(?:(?!\*\/).)*\*\/\s*(?:final\s+|abstract\s+)?(class|interface|trait)\s+(\w+)/s', $source, $m ) ) {
		$rows[] = array( $ns[1] . '\\' . $m[2], $m[1], str_replace( $dir . '/', '', $file->getPathname() ) );
	}
}
usort( $rows, static fn( $a, $b ) => strcmp( $a[0], $b[0] ) );
echo "# Public API baseline {$version}\n\n";
echo "Symbols marked `@api` in `packages/theme-core/src`. Everything else is `@internal` and may change in any release. Check changes with `tools/bc-check.sh <last-tag>` before releasing.\n\n";
echo "| Symbol | Kind | File |\n|---|---|---|\n";
foreach ( $rows as $row ) {
	echo "| `{$row[0]}` | {$row[1]} | `{$row[2]}` |\n";
}
echo "\n" . count( $rows ) . " symbols.\n";
