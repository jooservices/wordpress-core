<?php
/**
 * Test fixture module "a".
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

final class ModA extends RecordingModule {

	protected const DEPS = array();

	public static function id(): string {
		return 'a';
	}
}
