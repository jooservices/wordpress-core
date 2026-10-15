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

	public function test_registered_post_type_selects_the_wordpress_provider(): void {
		$this->assertTrue( ContentModule::wantsWpProvider( 'wp', true, true, false ) );
	}

	public function test_a_loaded_companion_plugin_counts_before_init(): void {
		$this->assertTrue( ContentModule::wantsWpProvider( 'wp', false, false, true ) );
	}

	public function test_without_plugin_or_after_init_without_type_the_null_provider_is_used(): void {
		$this->assertFalse( ContentModule::wantsWpProvider( 'wp', false, false, false ) );
		$this->assertFalse( ContentModule::wantsWpProvider( 'wp', false, true, true ) );
	}

	public function test_another_configured_provider_is_never_replaced(): void {
		$this->assertFalse( ContentModule::wantsWpProvider( 'null', true, true, true ) );
	}
}
