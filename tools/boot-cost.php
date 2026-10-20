<?php
/**
 * Measures empty and default JOOservices core boot costs in a WordPress CLI request.
 * Run through `make boot-cost` so the worktree WordPress environment and database are selected.
 *
 * @package JOOservices\Tools
 */

use JOOservices\ThemeCore\Admin\Notices;
use JOOservices\ThemeCore\Kernel;
use JOOservices\Tools\BootCostStatistics;

if ( ! class_exists( 'WP_CLI' ) || ! defined( 'ABSPATH' ) ) {
	if ( defined( 'STDERR' ) ) {
		fwrite( STDERR, "Run this measurement through make boot-cost.\n" );
	}
	exit( 2 );
}

if ( ! function_exists( 'memory_reset_peak_usage' ) ) {
	WP_CLI::error( 'Boot-cost measurement requires PHP 8.2 or newer for per-boot peak memory.' );
}

$root = dirname( __DIR__ );
require_once $root . '/packages/theme-core/core.php';
require_once __DIR__ . '/lib/boot-cost-statistics.php';

$budgetDocument = (string) file_get_contents( $root . '/docs/reference/budgets.md' );
$budgetMatch    = array();
if ( 1 !== preg_match( '/<!-- enforced-code-budgets:start -->\s*```json\s*(.*?)\s*```\s*<!-- enforced-code-budgets:end -->/s', $budgetDocument, $budgetMatch ) ) {
	WP_CLI::error( 'The enforced code budgets are missing from docs/reference/budgets.md.' );
}
$codeBudgets = json_decode( $budgetMatch[1] ?? '', true );
if ( ! is_array( $codeBudgets ) || ! isset( $codeBudgets['boot']['emptyModuleSetMedianMilliseconds'], $codeBudgets['boot']['emptyModuleSetPeakBytes'] ) ) {
	WP_CLI::error( 'The empty-module boot budget is invalid in docs/reference/budgets.md.' );
}
$timeBudget   = (float) $codeBudgets['boot']['emptyModuleSetMedianMilliseconds'];
$memoryBudget = (int) $codeBudgets['boot']['emptyModuleSetPeakBytes'];

$manifest = $root . '/themes/jooservices/joo-theme.json';
if ( ! is_file( $manifest ) ) {
	WP_CLI::error( 'The jooservices default manifest is missing.' );
}

if ( 0 === did_action( 'after_setup_theme' ) ) {
	do_action( 'after_setup_theme' );
}

$hooks = $GLOBALS['wp_filter'] ?? array();
foreach ( $hooks as $hook => $callbacks ) {
	if ( $callbacks instanceof WP_Hook ) {
		$hooks[ $hook ] = clone $callbacks;
	}
}

$restoreHooks = static function ( array $snapshot ): void {
	$names = array_unique( array_merge( array_keys( $GLOBALS['wp_filter'] ?? array() ), array_keys( $snapshot ) ) );
	foreach ( $names as $name ) {
		remove_all_filters( (string) $name );
	}

	foreach ( $snapshot as $name => $callbacks ) {
		if ( ! $callbacks instanceof WP_Hook ) {
			continue;
		}
		foreach ( $callbacks->callbacks as $priority => $entries ) {
			foreach ( $entries as $entry ) {
				if ( isset( $entry['function'] ) && is_callable( $entry['function'] ) ) {
					add_filter( (string) $name, $entry['function'], (int) $priority, (int) $entry['accepted_args'] );
				}
			}
		}
	}
};

$measure = static function ( string $name, array $modules ) use ( $hooks, $restoreHooks, $manifest ): array {
	$samples        = array(
		'milliseconds' => array(),
		'peak_bytes'   => array(),
	);
	$expectedStatus = null;

	for ( $sample = 0; $sample < 30; ++$sample ) {
		$restoreHooks( $hooks );
		remove_all_filters( 'joo/core/modules' );
		add_filter(
			'joo/core/modules',
			static fn( array $defaults ): array => array() === $modules ? array() : $defaults,
			PHP_INT_MAX
		);
		Kernel::reset();
		Notices::reset();
		gc_collect_cycles();

		$memoryBefore = memory_get_usage( false );
		memory_reset_peak_usage();
		$started = hrtime( true );
		Kernel::boot( $manifest );
		$samples['milliseconds'][] = ( hrtime( true ) - $started ) / 1e6;
		$samples['peak_bytes'][]   = max( 0, memory_get_peak_usage( false ) - $memoryBefore );

		if ( null === Kernel::container() ) {
			WP_CLI::error( sprintf( 'The %s boot did not create the core container.', $name ) );
		}

		$status = Kernel::status();
		if ( count( $status ) !== count( $modules ) ) {
			WP_CLI::error( sprintf( 'The %s boot returned %d of %d selected modules.', $name, count( $status ), count( $modules ) ) );
		}

		if ( null === $expectedStatus ) {
			$expectedStatus = $status;
		} elseif ( $expectedStatus !== $status ) {
			WP_CLI::error( sprintf( 'The %s boot sample returned an inconsistent module status map.', $name ) );
		}

		if ( array_diff( $status, array( 'booted' ) ) ) {
			WP_CLI::error( sprintf( 'The %s boot sample did not boot every selected module.', $name ) );
		}
	}

	return array(
		'milliseconds' => $samples['milliseconds'],
		'peak_bytes'   => $samples['peak_bytes'],
		'modules'      => array_keys( $expectedStatus ),
	);
};

try {
	$empty   = $measure( 'empty-module-set', array() );
	$default = $measure( 'default-module-set', Kernel::BUILTIN_MODULES );
} finally {
	$restoreHooks( $hooks );
	Kernel::reset();
	Notices::reset();
}

$emptyMedianMs   = BootCostStatistics::median( $empty['milliseconds'] );
$emptyPeakBytes  = BootCostStatistics::peak( $empty['peak_bytes'] );
$defaultMedianMs = BootCostStatistics::median( $default['milliseconds'] );
$defaultPeak     = BootCostStatistics::peak( $default['peak_bytes'] );
WP_CLI::line( sprintf( 'WordPress %s; PHP %s; samples per set: 30', get_bloginfo( 'version' ), PHP_VERSION ) );
WP_CLI::line( sprintf( 'Empty modules (%d modules): median %.3f ms; peak %d B (%.1f KiB)', count( $empty['modules'] ), $emptyMedianMs, $emptyPeakBytes, $emptyPeakBytes / 1024 ) );
WP_CLI::line( sprintf( 'Default modules (%d: %s): median %.3f ms; peak %d B (%.1f KiB)', count( $default['modules'] ), implode( ', ', $default['modules'] ), $defaultMedianMs, $defaultPeak, $defaultPeak / 1024 ) );

if ( $emptyMedianMs >= $timeBudget || $emptyPeakBytes >= $memoryBudget ) {
	WP_CLI::error( sprintf( 'The empty-module-set boot exceeds the %.3g ms or %d B budget.', $timeBudget, $memoryBudget ) );
}

WP_CLI::success( sprintf( 'The empty-module-set boot is within the %.3g ms and %d B budgets.', $timeBudget, $memoryBudget ) );
