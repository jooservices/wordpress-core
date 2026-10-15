<?php
/**
 * Validates theme style.css and plugin headers. Exit code 1 on any problem.
 *
 * Usage: php tools/check-headers.php [repo-root]
 */

declare(strict_types=1);

$root = rtrim( $argv[1] ?? dirname( __DIR__ ), '/' );

/**
 * Reads the given header fields from the top of a file.
 *
 * @param string   $file   File path.
 * @param string[] $fields Header field names.
 * @return array<string,string>
 */
function joo_read_header( string $file, array $fields ): array {
	$data = (string) file_get_contents( $file, false, null, 0, 8192 );
	$out  = array();
	foreach ( $fields as $name ) {
		$out[ $name ] = preg_match( '/^[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':(.*)$/mi', $data, $m ) ? trim( $m[1] ) : '';
	}
	return $out;
}

$errors = array();

foreach ( glob( $root . '/themes/*/style.css' ) ?: array() as $style ) {
	$slug   = basename( dirname( $style ) );
	$header = joo_read_header( $style, array( 'Theme Name', 'Version', 'Requires at least', 'Requires PHP', 'License', 'Text Domain' ) );
	foreach ( $header as $field => $value ) {
		if ( '' === $value ) {
			$errors[] = "themes/$slug/style.css: missing header '$field'";
		}
	}
	if ( '' !== $header['Text Domain'] && $header['Text Domain'] !== $slug ) {
		$errors[] = "themes/$slug/style.css: Text Domain '{$header['Text Domain']}' must equal the folder name '$slug'";
	}
	if ( '' !== $header['Version'] && ! preg_match( '/^\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?$/', $header['Version'] ) ) {
		$errors[] = "themes/$slug/style.css: Version '{$header['Version']}' is not semver";
	}
	if ( ! is_file( dirname( $style ) . '/theme.json' ) ) {
		$errors[] = "themes/$slug: theme.json is missing";
	} elseif ( null === json_decode( (string) file_get_contents( dirname( $style ) . '/theme.json' ) ) ) {
		$errors[] = "themes/$slug/theme.json: invalid JSON";
	}
	if ( ! is_file( dirname( $style ) . '/templates/index.html' ) ) {
		$errors[] = "themes/$slug: templates/index.html is missing";
	}
}

foreach ( glob( $root . '/plugins/*/*.php' ) ?: array() as $file ) {
	$header = joo_read_header( $file, array( 'Plugin Name', 'Version', 'Requires at least', 'Requires PHP', 'Text Domain' ) );
	if ( '' === $header['Plugin Name'] ) {
		continue; // not the main plugin file.
	}
	$plugin = basename( dirname( $file ) );
	foreach ( $header as $field => $value ) {
		if ( '' === $value ) {
			$errors[] = "plugins/$plugin: missing header '$field'";
		}
	}
	if ( '' !== $header['Text Domain'] && $header['Text Domain'] !== $plugin ) {
		$errors[] = "plugins/$plugin: Text Domain '{$header['Text Domain']}' must equal '$plugin'";
	}
}

if ( $errors ) {
	fwrite( STDERR, implode( "\n", $errors ) . "\n" );
	exit( 1 );
}
echo "headers ok\n";
