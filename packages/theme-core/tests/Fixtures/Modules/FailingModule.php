<?php
/**
 * Test fixture: a module that throws while booting.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

use JOOservices\ThemeCore\Module\AbstractModule;

final class FailingModule extends AbstractModule {

	public static function id(): string {
		return 'failing';
	}

	public function boot(): void {
		throw new \RuntimeException( 'intentional boot failure' );
	}
}
