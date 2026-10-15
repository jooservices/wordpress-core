<?php
/**
 * Test fixture with an invalid module id.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Fixtures\Modules;

final class BadIdModule extends RecordingModule {

	public static function id(): string {
		return 'Bad Id';
	}
}
