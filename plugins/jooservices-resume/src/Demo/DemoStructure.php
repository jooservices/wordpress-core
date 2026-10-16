<?php
/**
 * Compares the installed demo with the package structural baseline.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Demo;

/**
 * Structural comparison intentionally ignores database IDs and content wording.
 */
final class DemoStructure {

	/**
	 * Returns a stable list of structural differences.
	 *
	 * @param array<string, mixed> $expected Package baseline.
	 * @param array<string, mixed> $actual   Installed snapshot.
	 * @return list<string>
	 */
	public static function differences( array $expected, array $actual ): array {
		$differences = array();
		self::compareValue( '', $expected, $actual, $differences );
		return $differences;
	}

	/**
	 * Compares nested values without depending on associative key order.
	 *
	 * @param string               $path        Current data path.
	 * @param mixed                $expected    Expected value.
	 * @param mixed                $actual      Actual value.
	 * @param list<string>         $differences Differences, appended in place.
	 */
	private static function compareValue( string $path, $expected, $actual, array &$differences ): void {
		if ( is_array( $expected ) && is_array( $actual ) ) {
			$keys = array_unique( array_merge( array_keys( $expected ), array_keys( $actual ) ) );
			sort( $keys );
			foreach ( $keys as $key ) {
				$child = '' === $path ? (string) $key : $path . '.' . (string) $key;
				if ( ! array_key_exists( $key, $expected ) ) {
					$differences[] = $child . ': unexpected value';
				} elseif ( ! array_key_exists( $key, $actual ) ) {
					$differences[] = $child . ': missing value';
				} else {
					self::compareValue( $child, $expected[ $key ], $actual[ $key ], $differences );
				}
			}
			return;
		}
		if ( $expected !== $actual ) {
			$differences[] = sprintf( '%s: expected %s, found %s', $path, (string) wp_json_encode( $expected ), (string) wp_json_encode( $actual ) );
		}
	}
}
