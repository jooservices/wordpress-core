<?php
/**
 * Manifest schema coverage tests.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Config;

use JOOservices\ThemeCore\Config\ManifestLoader;
use PHPUnit\Framework\TestCase;

final class ManifestSchemaCoverageTest extends TestCase {

	public function test_fixture_manifests_cover_every_schema_property_and_enum_value(): void {
		$root       = dirname( __DIR__, 5 );
		$schemaPath = $root . '/packages/theme-core/schema/manifest.schema.json';
		$schema     = json_decode( (string) file_get_contents( $schemaPath ), true, 512, JSON_THROW_ON_ERROR );
		$paths      = array_merge(
			glob( $root . '/themes/*/joo-theme.json' ) ?: array(),
			glob( $root . '/tests/fixtures/themes/*/joo-theme.json' ) ?: array(),
			glob( $root . '/tests/fixtures/manifests/*.json' ) ?: array(),
			glob( $root . '/docs/spec/examples/*.json' ) ?: array()
		);
		$paths      = array_values( array_unique( $paths ) );
		sort( $paths, SORT_STRING );

		$this->assertNotEmpty( $paths, 'At least one fixture manifest must be discovered.' );

		$loader    = new ManifestLoader( null, $schemaPath );
		$manifests = array();
		foreach ( $paths as $path ) {
			$json     = (string) file_get_contents( $path );
			$manifest = $loader->parse( $json );
			$this->assertTrue( $manifest->isValid(), $path . ': ' . implode( '; ', $manifest->errors() ) );
			$manifests[ $path ] = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
		}

		$propertyPaths = array();
		$enumRules     = array();
		$patternRules  = array();
		$this->collectSchemaCoverage( $schema, array(), $propertyPaths, $enumRules, $patternRules );

		$missingProperties = array();
		foreach ( $propertyPaths as $path ) {
			$used = false;
			foreach ( $manifests as $manifest ) {
				if ( array() !== $this->valuesAtPath( $manifest, $path ) ) {
					$used = true;
					break;
				}
			}
			if ( ! $used ) {
				$missingProperties[] = $this->formatPath( $path );
			}
		}

		$missingEnums = array();
		foreach ( $enumRules as $rule ) {
			$observed = array();
			foreach ( $manifests as $manifest ) {
				$observed = array_merge( $observed, $this->valuesAtPath( $manifest, $rule['path'] ) );
			}
			foreach ( $rule['values'] as $value ) {
				if ( ! in_array( $value, $observed, true ) ) {
					$missingEnums[] = $this->formatPath( $rule['path'] ) . ' = ' . json_encode( $value, JSON_THROW_ON_ERROR );
				}
			}
		}

		$missingPatterns = array();
		foreach ( $patternRules as $rule ) {
			$matched = false;
			foreach ( $manifests as $manifest ) {
				foreach ( $this->valuesAtPath( $manifest, $rule['path'] ) as $object ) {
					if ( ! is_array( $object ) ) {
						continue;
					}
					foreach ( array_keys( $object ) as $key ) {
						$pattern = '~' . str_replace( '~', '\\~', $rule['pattern'] ) . '~u';
						if ( 1 === preg_match( $pattern, (string) $key ) ) {
							$matched = true;
							break 3;
						}
					}
				}
			}
			if ( ! $matched ) {
				$missingPatterns[] = $this->formatPath( $rule['path'] ) . ' key matching ' . $rule['pattern'];
			}
		}

		$this->assertSame( array(), $missingProperties, "Schema properties without fixture coverage:\n" . implode( "\n", $missingProperties ) );
		$this->assertSame( array(), $missingEnums, "Schema enum values without fixture coverage:\n" . implode( "\n", $missingEnums ) );
		$this->assertSame( array(), $missingPatterns, "Schema key patterns without fixture coverage:\n" . implode( "\n", $missingPatterns ) );
	}

	/**
	 * Collects declared object properties, enum values, and representative pattern keys.
	 *
	 * @param array<mixed>              $schema         Schema node.
	 * @param array<int, string>        $path           Manifest path to this schema node.
	 * @param array<string, array<int, string>> $propertyPaths Declared property paths.
	 * @param array<int, array{path: array<int, string>, values: array<mixed>}> $enumRules Enum rules.
	 * @param array<int, array{path: array<int, string>, pattern: string}> $patternRules Pattern key rules.
	 */
	private function collectSchemaCoverage( array $schema, array $path, array &$propertyPaths, array &$enumRules, array &$patternRules ): void {
		if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) ) {
			$enumRules[] = array(
				'path'   => $path,
				'values' => $schema['enum'],
			);
		}

		foreach ( $schema['properties'] ?? array() as $key => $childSchema ) {
			$childPath                                        = array_merge( $path, array( (string) $key ) );
			$propertyPaths[ $this->formatPath( $childPath ) ] = $childPath;
			if ( is_array( $childSchema ) ) {
				$this->collectSchemaCoverage( $childSchema, $childPath, $propertyPaths, $enumRules, $patternRules );
			}
		}

		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$this->collectSchemaCoverage( $schema['items'], array_merge( $path, array( '*' ) ), $propertyPaths, $enumRules, $patternRules );
		}

		if ( isset( $schema['additionalProperties'] ) && is_array( $schema['additionalProperties'] ) ) {
			$this->collectSchemaCoverage( $schema['additionalProperties'], array_merge( $path, array( '*' ) ), $propertyPaths, $enumRules, $patternRules );
		}

		foreach ( $schema['patternProperties'] ?? array() as $pattern => $childSchema ) {
			$patternRules[] = array(
				'path'    => $path,
				'pattern' => (string) $pattern,
			);
			if ( is_array( $childSchema ) ) {
				$this->collectSchemaCoverage( $childSchema, array_merge( $path, array( '*' ) ), $propertyPaths, $enumRules, $patternRules );
			}
		}
	}

	/**
	 * Gets all values at a schema path. An asterisk matches each array item or object value.
	 *
	 * @param mixed                $value Current value.
	 * @param array<int, string>   $path  Remaining path segments.
	 * @return array<int, mixed>
	 */
	private function valuesAtPath( $value, array $path ): array {
		if ( array() === $path ) {
			return array( $value );
		}

		$segment = array_shift( $path );
		if ( '*' === $segment ) {
			if ( ! is_array( $value ) ) {
				return array();
			}

			$values = array();
			foreach ( $value as $child ) {
				$values = array_merge( $values, $this->valuesAtPath( $child, $path ) );
			}
			return $values;
		}

		if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
			return array();
		}

		return $this->valuesAtPath( $value[ $segment ], $path );
	}

	/**
	 * Formats a schema path for failure output.
	 *
	 * @param array<int, string> $path Schema path.
	 */
	private function formatPath( array $path ): string {
		return '/' . implode( '/', array_map( static fn( string $segment ): string => str_replace( array( '~', '/' ), array( '~0', '~1' ), $segment ), $path ) );
	}
}
