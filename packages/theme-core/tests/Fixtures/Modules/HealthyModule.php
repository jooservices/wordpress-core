<?php
/**
 * Test fixture: a module that adds one hook.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

use JOOservices\ThemeCore\Module\AbstractModule;

final class HealthyModule extends AbstractModule {

	public static int $boots = 0;

	public static function id(): string {
		return 'healthy';
	}

	public function boot(): void {
		++self::$boots;
		add_action( 'wp_head', array( $this, 'print_marker' ) );
	}

	public function print_marker(): void {
		echo '<!-- healthy-module -->';
	}
}
