<?php
/**
 * wp joo modules
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Admin\Cli;

use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Kernel;

/**
 * Module status.
 *
 * @internal
 */
final class ModulesCommand {

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
	 * Lists modules with their status.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv or yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function list( array $args, array $assoc_args ): void {
		unset( $args );
		$registry = Kernel::registry();
		$rows     = array();
		foreach ( Kernel::status() as $id => $status ) {
			$rows[] = array(
				'id'      => $id,
				'status'  => $status,
				'message' => null === $registry ? '' : $registry->message( (string) $id ),
			);
		}
		unset( $this->container );
		\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'id', 'status', 'message' ) );
	}
}
