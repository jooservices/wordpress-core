<?php
/**
 * wp joo doctor
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Admin\Cli;

use JOOservices\ThemeCore\Admin\SiteHealth;
use JOOservices\ThemeCore\Container\Container;

/**
 * Runs the Site Health tests of core in the terminal.
 *
 * @internal
 */
final class DoctorCommand {

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
	 * Checks requirements, manifest, modules and more. Exit code 0 = ok, 1 = recommendations, 2 = critical problems.
	 *
	 * @param array<int, string> $args Positional arguments.
	 */
	public function __invoke( array $args ): void {
		unset( $args );
		/** @var SiteHealth $health */
		$health   = $this->container->get( SiteHealth::class );
		$worst    = 0;
		$statuses = array(
			'good'        => 0,
			'recommended' => 1,
			'critical'    => 2,
		);
		foreach ( $health->ids() as $id ) {
			$result = $health->run( $id );
			$level  = $statuses[ $result['status'] ] ?? 0;
			$worst  = max( $worst, $level );
			\WP_CLI::line( sprintf( '[%s] %s: %s', strtoupper( (string) $result['status'] ), $id, wp_specialchars_decode( wp_strip_all_tags( (string) $result['label'] ) ) ) );
			if ( $level > 0 ) {
				\WP_CLI::line( '        ' . wp_specialchars_decode( wp_strip_all_tags( (string) $result['description'] ) ) );
			}
		}
		if ( 0 === $worst ) {
			\WP_CLI::success( 'No problems found.' );
			return;
		}
		\WP_CLI::halt( $worst );
	}
}
