<?php
/**
 * Test fixture: a module that depends on a module which fails during boot.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

use JOOservices\ThemeCore\Module\AbstractModule;

final class DependentModule extends AbstractModule {

	public static function id(): string {
		return 'dependent';
	}

	public function dependencies(): array {
		return array( 'failing' );
	}
}
