<?php
/**
 * Which content provider is used.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Content;

use JOOservices\ThemeCore\Content\ContentModule;
use PHPUnit\Framework\TestCase;

final class ProviderChoiceTest extends TestCase {

	public function test_wordpress_provider_does_not_require_the_optional_companion_plugin(): void {
		$this->assertTrue( ContentModule::wantsWpProvider( 'wp' ) );
	}

	public function test_another_configured_provider_is_never_replaced(): void {
		$this->assertFalse( ContentModule::wantsWpProvider( 'null' ) );
	}
}
