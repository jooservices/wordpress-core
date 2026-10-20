<?php
/**
 * Test fixture: a module that throws while registering.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Module\AbstractModule;

final class FailingRegisterModule extends AbstractModule {

	public static function id(): string {
		return 'register-failing';
	}

	public function register( Container $container ): void {
		unset( $container );
		throw new \RuntimeException( 'intentional register failure' );
	}
}
