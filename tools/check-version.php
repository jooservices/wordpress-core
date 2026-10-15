<?php
/**
 * Compares the core version in composer.json, core.php and CHANGELOG.md.
 *
 * Usage: php tools/check-version.php [core-dir]
 *
 * @package JOOservices
 */

$dir = rtrim( $argv[1] ?? dirname( __DIR__ ) . '/packages/theme-core', '/' );

$read = static fn( string $file ): string => is_readable( $dir . '/' . $file ) ? (string) file_get_contents( $dir . '/' . $file ) : '';

$composer = json_decode( $read( 'composer.json' ), true );
$core     = $read( 'core.php' );
$log      = $read( 'CHANGELOG.md' );

$versions = array(
	'composer.json' => is_array( $composer ) ? (string) ( $composer['version'] ?? '' ) : '',
	'core.php'      => 1 === preg_match( "/define\\(\\s*'JOO_CORE_VERSION',\\s*'([^']+)'/", $core, $m ) ? $m[1] : '',
	'CHANGELOG.md'  => 1 === preg_match( '/^## (\d+\.\d+\.\d+(?:-[0-9A-Za-z.]+)?)\b/m', $log, $m2 ) ? $m2[1] : '',
);

$ok = true;
foreach ( $versions as $file => $version ) {
	if ( 1 !== preg_match( '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.]+)?$/', $version ) ) {
		fwrite( STDERR, "FAIL {$file}: no valid version found\n" );
		$ok = false;
	}
}
if ( 1 !== count( array_unique( $versions ) ) ) {
	fwrite( STDERR, 'FAIL versions differ: ' . json_encode( $versions ) . "\n" );
	$ok = false;
}
if ( $ok ) {
	echo 'ok core version ' . $versions['composer.json'] . " (composer.json, core.php, CHANGELOG.md)\n";
}
exit( $ok ? 0 : 1 );
