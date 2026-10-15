<?php
/**
 * Test fixture module "f".
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

final class ModF extends RecordingModule {

	protected const DEPS = array('d', 'e');

	public static function id(): string {
		return 'f';
	}
}
