<?php
/**
 * Deliberately bad module: adds a hook and writes an option inside register().
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Module\AbstractModule;

final class BadRegisterModule extends AbstractModule {

	public static function id(): string {
		return 'bad-register';
	}

	public function register( Container $container ): void {
		add_action( 'init', static function (): void {} );
		update_option( 'joo_bad_register_option', '1' );
	}
}
