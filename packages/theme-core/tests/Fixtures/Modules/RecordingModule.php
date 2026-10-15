<?php
/**
 * Test fixture: records lifecycle calls and can be told to fail.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

use JOOservices\ThemeCore\Container\Container;
use JOOservices\ThemeCore\Module\AbstractModule;

abstract class RecordingModule extends AbstractModule {

	/** @var list<string> */
	public static array $events = array();

	/** @var array<string, bool> e.g. 'boot:a' => true */
	public static array $fail = array();

	/** @var list<string> */
	protected const DEPS = array();

	public static function reset(): void {
		self::$events = array();
		self::$fail   = array();
	}

	public function dependencies(): array {
		return static::DEPS;
	}

	public function register( Container $container ): void {
		self::$events[] = 'register:' . static::id();
		if ( isset( self::$fail[ 'register:' . static::id() ] ) ) {
			throw new \RuntimeException( 'register failed' );
		}
	}

	protected function doBoot(): void {
		self::$events[] = 'boot:' . static::id();
		if ( isset( self::$fail[ 'boot:' . static::id() ] ) ) {
			throw new \RuntimeException( 'boot failed' );
		}
	}
}
