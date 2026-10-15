<?php
/**
 * Test fixture module "d".
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

final class ModD extends RecordingModule {

	protected const DEPS = array('c');

	public static function id(): string {
		return 'd';
	}
}
