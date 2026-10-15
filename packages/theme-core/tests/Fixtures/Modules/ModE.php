<?php
/**
 * Test fixture module "e".
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

final class ModE extends RecordingModule {

	protected const DEPS = array('b');

	public static function id(): string {
		return 'e';
	}
}
