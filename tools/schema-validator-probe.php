<?php
/**
 * Runs the runtime manifest validator for the schema differential tooling.
 *
 * @package JOOservices
 */

declare(strict_types=1);

use JOOservices\ThemeCore\Config\SchemaValidator;

require_once dirname( __DIR__ ) . '/packages/theme-core/src/Config/SchemaValidator.php';

try {
	$input = file_get_contents( 'php://stdin' );
	if ( false === $input ) {
		throw new RuntimeException( 'Unable to read probe input.' );
	}
	$request = json_decode( $input, true, 512, JSON_THROW_ON_ERROR );
	if ( ! is_array( $request ) || ! isset( $request['samples'] ) || ! is_array( $request['samples'] ) ) {
		throw new RuntimeException( 'Probe input must contain a samples array.' );
	}

	$schema = json_decode(
		(string) file_get_contents( dirname( __DIR__ ) . '/packages/theme-core/schema/manifest.schema.json' ),
		true,
		512,
		JSON_THROW_ON_ERROR
	);
	if ( ! is_array( $schema ) ) {
		throw new RuntimeException( 'Manifest schema must be a JSON object.' );
	}

	$validator = new SchemaValidator();
	$results   = array();
	foreach ( $request['samples'] as $sample ) {
		if ( ! is_array( $sample ) || ! isset( $sample['label'], $sample['json'] ) ) {
			throw new RuntimeException( 'Each sample must contain a label and raw JSON string.' );
		}
		$data = json_decode( (string) $sample['json'], false, 512, JSON_THROW_ON_ERROR );
		if ( ! ( $data instanceof stdClass ) ) {
			throw new RuntimeException( sprintf( 'Sample %s must be a JSON object.', $sample['label'] ) );
		}
		$errors    = $validator->validate( $data, $schema );
		$results[] = array(
			'label'  => (string) $sample['label'],
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	echo json_encode(
		array(
			'supported'   => SchemaValidator::SUPPORTED,
			'annotations' => SchemaValidator::ANNOTATIONS,
			'results'     => $results,
		),
		JSON_THROW_ON_ERROR
	);
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
}
