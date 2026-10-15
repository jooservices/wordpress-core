<?php
/**
 * Test fixture module "y".
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

final class ModCycleY extends RecordingModule {

	protected const DEPS = array('x');

	public static function id(): string {
		return 'y';
	}
}
