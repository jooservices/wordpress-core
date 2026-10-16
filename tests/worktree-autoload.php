<?php
/**
 * Loads project classes from this checkout before Composer's shared worktree dependencies.
 *
 * @package JOOservices
 */

declare(strict_types=1);

$joo_worktree_root     = dirname( __DIR__ );
$joo_worktree_prefixes = array(
	'JOOservices\\Resume\\Tests\\'    => 'plugins/jooservices-resume/tests/',
	'JOOservices\\Resume\\'           => 'plugins/jooservices-resume/src/',
	'JOOservices\\ThemeCore\\Tests\\' => 'packages/theme-core/tests/',
	'JOOservices\\ThemeCore\\'        => 'packages/theme-core/src/',
	'JOOservices\\TestKit\\Tests\\'   => 'packages/test-kit/tests/',
	'JOOservices\\TestKit\\'          => 'packages/test-kit/src/',
);

spl_autoload_register(
	static function ( string $class ) use ( $joo_worktree_root, $joo_worktree_prefixes ): void {
		foreach ( $joo_worktree_prefixes as $prefix => $directory ) {
			if ( 0 !== strpos( $class, $prefix ) ) {
				continue;
			}
			$file = $joo_worktree_root . '/' . $directory . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
			return;
		}
	},
	true,
	true
);
