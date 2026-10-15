<?php
/**
 * Test fixture module "c".
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

final class ModC extends RecordingModule {

	protected const DEPS = array('a', 'b');

	public static function id(): string {
		return 'c';
	}
}
