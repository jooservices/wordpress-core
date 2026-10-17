<?php
/**
 * Data migrations of the demo and staging sites (development only; product upgrades belong in the plugin/theme-core).
 *
 * Run: wp eval-file docker/scripts/migrate.php [status] [dir=<path>]
 * Files in migrations/ are applied in name order: <UTC timestamp>-<ticket>-<name>.sql|php. Each applied file is recorded in
 * {prefix}joo_dev_migrations, so a file that was merged out of order is still applied once. SQL files use {prefix} and run
 * statement by statement; PHP files run with $wpdb in scope. A failing file stops the run with a non-zero exit.
 *
 * @package JOOservices
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL,WordPress.WP.GlobalVariablesOverride,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

global $wpdb;

$joo_args   = isset( $args ) ? (array) $args : array();
$joo_status = in_array( 'status', $joo_args, true );
$joo_dir    = dirname( __DIR__, 2 ) . '/migrations';
foreach ( $joo_args as $joo_arg ) {
	if ( 0 === strpos( (string) $joo_arg, 'dir=' ) ) {
		$joo_dir = rtrim( substr( (string) $joo_arg, 4 ), '/' );
	}
}

$joo_table = $wpdb->prefix . 'joo_dev_migrations';
$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$joo_table}` (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL)" );

$joo_files = array_merge( glob( $joo_dir . '/*.sql' ) ?: array(), glob( $joo_dir . '/*.php' ) ?: array() );
usort(
	$joo_files,
	static function ( $a, $b ) {
		return strcmp( basename( $a ), basename( $b ) );
	}
);
$joo_applied = (array) $wpdb->get_col( "SELECT name FROM `{$joo_table}`" );

$joo_pending = array();
foreach ( $joo_files as $joo_file ) {
	$joo_name = basename( $joo_file );
	if ( ! preg_match( '/^\d{14}-[A-Za-z]+-\d+-[a-z0-9-]+\.(sql|php)$/', $joo_name ) ) {
		WP_CLI::error( "Migration {$joo_name} does not match <YYYYMMDDHHMMSS>-<TICKET>-<name>.sql|php" );
	}
	if ( ! in_array( $joo_name, $joo_applied, true ) ) {
		$joo_pending[] = $joo_file;
	}
}

if ( $joo_status ) {
	WP_CLI::log( sprintf( '%d applied, %d pending', count( $joo_applied ), count( $joo_pending ) ) );
	foreach ( $joo_pending as $joo_file ) {
		WP_CLI::log( 'pending: ' . basename( $joo_file ) );
	}
	return;
}

foreach ( $joo_pending as $joo_file ) {
	$joo_name = basename( $joo_file );
	WP_CLI::log( "Applying {$joo_name}" );
	if ( str_ends_with( $joo_name, '.sql' ) ) {
		$joo_sql = str_replace( '{prefix}', $wpdb->prefix, (string) file_get_contents( $joo_file ) );
		foreach ( array_filter( array_map( 'trim', preg_split( '/;\s*(\r?\n|$)/', $joo_sql ) ?: array() ) ) as $joo_statement ) {
			if ( false === $wpdb->query( $joo_statement ) ) {
				WP_CLI::error( "Migration {$joo_name} failed: " . $wpdb->last_error );
			}
		}
	} else {
		( static function ( $file ) {
			global $wpdb; // In scope for the migration file.
			require $file;
		} )( $joo_file );
	}
	$wpdb->insert(
		$joo_table,
		array(
			'name'       => $joo_name,
			'applied_at' => gmdate( 'Y-m-d H:i:s' ),
		)
	);
}

WP_CLI::success( sprintf( 'Migrations done (%d applied now).', count( $joo_pending ) ) );
