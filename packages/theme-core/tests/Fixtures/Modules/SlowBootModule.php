<?php
/**
 * Test fixture: a module that deliberately exceeds the boot-time budget.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

use JOOservices\ThemeCore\Module\AbstractModule;

final class SlowBootModule extends AbstractModule {

	public static function id(): string {
		return 'slow-boot';
	}

	protected function doBoot(): void {
		usleep( 50000 );
	}
}
