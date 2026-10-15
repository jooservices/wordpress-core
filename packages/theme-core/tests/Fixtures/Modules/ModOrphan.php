<?php
/**
 * Test fixture module "orphan".
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

final class ModOrphan extends RecordingModule {

	protected const DEPS = array('missing');

	public static function id(): string {
		return 'orphan';
	}
}
