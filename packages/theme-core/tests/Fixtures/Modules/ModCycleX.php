<?php
/**
 * Test fixture module "x".
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

final class ModCycleX extends RecordingModule {

	protected const DEPS = array('y');

	public static function id(): string {
		return 'x';
	}
}
