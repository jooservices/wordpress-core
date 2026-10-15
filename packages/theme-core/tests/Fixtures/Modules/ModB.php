<?php
/**
 * Test fixture module "b".
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

final class ModB extends RecordingModule {

	protected const DEPS = array('a');

	public static function id(): string {
		return 'b';
	}
}
