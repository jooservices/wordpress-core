<?php
/**
 * Checks line coverage of a Clover report for files under a path prefix.
 *
 * Usage: php tools/coverage-check.php <clover.xml> <path-prefix> <min-percent>
 * Exit code 0 when covered >= min, 1 otherwise, 2 on usage errors.
 *
 * @package JOOservices
 */

declare(strict_types=1);

$args = $GLOBALS['argv'] ?? array();

if ( count( $args ) < 4 ) {
	fwrite( STDERR, "usage: php tools/coverage-check.php <clover.xml> <path-prefix> <min-percent>\n" );
	exit( 2 );
}

list( , $clover, $prefix, $min ) = $args;

if ( ! is_file( $clover ) ) {
	fwrite( STDERR, "clover file not found: $clover\n" );
	exit( 2 );
}

$xml = simplexml_load_file( $clover );
if ( false === $xml ) {
	fwrite( STDERR, "invalid clover xml\n" );
	exit( 2 );
}

$total   = 0;
$covered = 0;
foreach ( $xml->xpath( '//file' ) ?: array() as $file ) {
	$name = (string) $file['name'];
	if ( false === strpos( $name, $prefix ) ) {
		continue;
	}
	foreach ( $file->line as $line ) {
		if ( 'stmt' !== (string) $line['type'] ) {
			continue;
		}
		++$total;
		if ( (int) $line['count'] > 0 ) {
			++$covered;
		}
	}
}

if ( 0 === $total ) {
	fwrite( STDERR, "no statements found under '$prefix'\n" );
	exit( 2 );
}

$percent = round( 100 * $covered / $total, 2 );
printf( "%s: %s%% (%d/%d statements), required %s%%\n", $prefix, $percent, $covered, $total, $min );
exit( $percent + 0.0 >= (float) $min ? 0 : 1 );
