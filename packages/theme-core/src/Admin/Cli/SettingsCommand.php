<?php
/**
 * wp joo settings
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Admin\Cli;

use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Container\Container;

/**
 * Reads and writes core settings.
 *
 * @internal
 */
final class SettingsCommand {

	/**
	 * Container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Creates the command.
	 *
	 * @param Container $container Container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Lists settings with their effective values.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv or yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * [--source]
	 * : Include where each value comes from.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function list( array $args, array $assoc_args ): void {
		unset( $args );
		$settings = $this->settings();
		$rows     = array();
		foreach ( array_keys( $settings->schema()->all() ) as $key ) {
			$row = array(
				'key'   => $key,
				'value' => $this->display( $settings->get( $key ) ),
			);
			if ( isset( $assoc_args['source'] ) ) {
				$row['source'] = $settings->source( $key );
			}
			$rows[] = $row;
		}
		\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, isset( $assoc_args['source'] ) ? array( 'key', 'value', 'source' ) : array( 'key', 'value' ) );
	}

	/**
	 * Prints one setting.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Dotted key such as lazy.images.
	 *
	 * @param array<int, string> $args Positional arguments.
	 */
	public function get( array $args ): void {
		$settings = $this->settings();
		if ( null === $settings->schema()->get( $args[0] ) ) {
			\WP_CLI::error( sprintf( 'Unknown setting "%s".', $args[0] ) );
		}
		\WP_CLI::line( $this->display( $settings->get( $args[0] ) ) );
	}

	/**
	 * Sets one setting.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Dotted key.
	 *
	 * <value>
	 * : New value (true/false, number, text, JSON or comma separated list).
	 *
	 * @param array<int, string> $args Positional arguments.
	 */
	public function set( array $args ): void {
		$settings   = $this->settings();
		$definition = $settings->schema()->get( $args[0] );
		if ( null === $definition ) {
			\WP_CLI::error( sprintf( 'Unknown setting "%s".', $args[0] ) );
			return;
		}
		$result = $settings->save( array( $args[0] => $this->parse( $definition, $args[1] ) ) );
		if ( isset( $result['errors'][ $args[0] ] ) ) {
			\WP_CLI::error( $result['errors'][ $args[0] ] );
		}
		\WP_CLI::success( sprintf( '%s = %s', $args[0], $this->display( $settings->get( $args[0] ) ) ) );
	}

	/**
	 * Removes saved overrides.
	 *
	 * ## OPTIONS
	 *
	 * [<key>]
	 * : Key to reset.
	 *
	 * [--all]
	 * : Reset every setting.
	 *
	 * [--yes]
	 * : Skip the confirmation.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function reset( array $args, array $assoc_args ): void {
		$settings = $this->settings();
		if ( isset( $assoc_args['all'] ) ) {
			\WP_CLI::confirm( 'Remove every saved setting?', $assoc_args );
			$settings->repository()->reset();
			\WP_CLI::success( 'All saved settings removed.' );
			return;
		}
		if ( ! isset( $args[0] ) || null === $settings->schema()->get( $args[0] ) ) {
			\WP_CLI::error( 'Pass a known key or --all.' );
		}
		$settings->repository()->reset( $args[0] );
		\WP_CLI::success( sprintf( '%s reset.', $args[0] ) );
	}

	/**
	 * Settings service.
	 */
	private function settings(): Settings {
		/** @var Settings $settings */
		$settings = $this->container->get( Settings::class );
		return $settings;
	}

	/**
	 * Parses CLI text into the type the descriptor expects.
	 *
	 * @param array<string, mixed> $definition Definition.
	 * @param string               $value      Text.
	 * @return mixed
	 */
	private function parse( array $definition, string $value ) {
		switch ( $definition['type'] ?? 'string' ) {
			case 'boolean':
				return in_array( strtolower( $value ), array( '1', 'true', 'on', 'yes' ), true ) ? '1' : '0';
			case 'array':
				$decoded = json_decode( $value, true );
				return is_array( $decoded ) ? $decoded : $value;
			default:
				return $value;
		}
	}

	/**
	 * Printable form of a value.
	 *
	 * @param mixed $value Value.
	 */
	private function display( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_array( $value ) ) {
			return (string) wp_json_encode( $value );
		}
		return (string) $value;
	}
}
