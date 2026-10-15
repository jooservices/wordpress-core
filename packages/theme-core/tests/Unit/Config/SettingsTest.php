<?php
/**
 * Settings stack tests.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Config;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use JOOservices\TestKit\UnitTestCase;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\Sanitizer;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Config\SettingsRepository;
use JOOservices\ThemeCore\Config\SettingsSchema;

final class SettingsTest extends UnitTestCase {

	/** @var array<string, mixed> */
	private array $options = array();

	protected function setUp(): void {
		parent::setUp();
		$this->options = array();
		Functions\when( 'get_option' )->alias( fn( $name, $default = false ) => $this->options[ $name ] ?? $default );
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				$this->options[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( $name ) {
				unset( $this->options[ $name ] );
				return true;
			}
		);
		Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
	}

	private function settings( array $manifest = array() ): Settings {
		$schema = new SettingsSchema();
		return new Settings( $schema, new Manifest( $manifest ), new SettingsRepository( 'demo-theme', $schema ) );
	}

	public function test_core_default_is_the_last_resort(): void {
		$s = $this->settings();
		$this->assertTrue( $s->get( 'lazy.images' ) );
		$this->assertSame( 'core', $s->source( 'lazy.images' ) );
		$this->assertSame( 1, $s->get( 'lazy.eager_count' ) );
	}

	public function test_manifest_beats_core_default_even_when_false(): void {
		$s = $this->settings(
			array(
				'lazy' => array(
					'images'     => false,
					'eagerCount' => 2,
				),
			)
		);
		$this->assertFalse( $s->get( 'lazy.images' ) );
		$this->assertSame( 2, $s->get( 'lazy.eager_count' ) );
		$this->assertSame( 'manifest', $s->source( 'lazy.images' ) );
	}

	public function test_saved_value_beats_manifest(): void {
		$s = $this->settings( array( 'lazy' => array( 'images' => false ) ) );
		$s->save( array( 'lazy.images' => '1' ) );
		$this->assertTrue( $s->get( 'lazy.images' ) );
		$this->assertSame( 'user', $s->source( 'lazy.images' ) );
	}

	public function test_filter_beats_everything(): void {
		$s = $this->settings();
		$s->repository()->save( array( 'seo.yield' => 'never' ) );
		Filters\expectApplied( 'joo/settings/value' )->andReturnUsing( static fn( $value, $key ) => 'seo.yield' === $key ? 'always' : $value );
		$this->assertSame( 'always', $s->get( 'seo.yield' ) );
		$this->assertSame( 'filter', $s->source( 'seo.yield' ) );
		$this->assertTrue( $s->get( 'lazy.images' ) );
	}

	public function test_unknown_keys_return_the_default_and_unknown_source(): void {
		$s = $this->settings();
		$this->assertSame( 'fallback', $s->get( 'nope.nothing', 'fallback' ) );
		$this->assertSame( 'unknown', $s->source( 'nope.nothing' ) );
	}

	public function test_defaults_filter_changes_core_defaults(): void {
		Filters\expectApplied( 'joo/settings/defaults' )->once()->andReturnUsing(
			static function ( array $defaults ) {
				$defaults['lazy.eager_count'] = 3;
				return $defaults;
			}
		);
		$this->assertSame( 3, $this->settings()->get( 'lazy.eager_count' ) );
	}

	public function test_only_values_that_differ_from_defaults_are_stored(): void {
		$s = $this->settings();
		$s->repository()->save(
			array(
				'lazy.images'  => true,
				'lazy.iframes' => false,
			)
		);
		$this->assertSame( array( 'lazy.iframes' => false ), $this->options['joo_demo_theme_settings'] );
		$s->repository()->save( array( 'lazy.iframes' => true ) );
		$this->assertArrayNotHasKey( 'joo_demo_theme_settings', $this->options, 'the option is removed when nothing differs' );
	}

	public function test_saving_the_core_default_is_kept_when_the_manifest_differs(): void {
		$s = $this->settings( array( 'lazy' => array( 'images' => false ) ) );
		$s->save( array( 'lazy.images' => '1' ) );
		$this->assertSame( array( 'lazy.images' => true ), $this->options['joo_demo_theme_settings'] );
		$s->save( array( 'lazy.images' => '0' ) );
		$this->assertArrayNotHasKey( 'joo_demo_theme_settings', $this->options, 'equal to the manifest value: nothing to store' );
	}

	public function test_invalid_values_are_reported_and_not_stored(): void {
		$result = $this->settings()->repository()->save(
			array(
				'seo.yield'        => 'sometimes',
				'lazy.eager_count' => '99',
				'lazy.images'      => '0',
			)
		);
		$this->assertArrayHasKey( 'seo.yield', $result['errors'] );
		$this->assertArrayHasKey( 'lazy.eager_count', $result['errors'] );
		$this->assertSame( array( 'lazy.images' => false ), $result['saved'] );
	}

	public function test_unknown_keys_are_ignored_on_save(): void {
		$result = $this->settings()->repository()->save( array( 'made.up' => 'x' ) );
		$this->assertSame( array(), $result['saved'] );
		$this->assertSame( array(), $result['errors'] );
	}

	public function test_reset_one_key_and_all(): void {
		$s = $this->settings();
		$s->repository()->save(
			array(
				'lazy.images'  => '0',
				'lazy.iframes' => '0',
			)
		);
		$s->repository()->reset( 'lazy.images' );
		$this->assertSame( array( 'lazy.iframes' => false ), $this->options['joo_demo_theme_settings'] );
		$s->repository()->reset();
		$this->assertArrayNotHasKey( 'joo_demo_theme_settings', $this->options );
	}

	public function test_a_corrupted_option_is_treated_as_empty(): void {
		$this->options['joo_demo_theme_settings'] = 'garbage';
		$this->assertTrue( $this->settings()->get( 'lazy.images' ) );
	}

	public function test_all_returns_every_registered_key(): void {
		$schema = new SettingsSchema();
		$this->assertSame( array_keys( $schema->all() ), array_keys( $this->settings()->all() ) );
	}

	public function test_schema_helpers(): void {
		$schema = new SettingsSchema();
		$this->assertSame( 1, $schema->version() );
		$this->assertNotEmpty( $schema->groups() );
		$this->assertContains( 'lazy.images', $schema->keysInGroup( 'lazy' ) );
		$this->assertTrue( $schema->defaults()['lazy.enabled'] );
		$this->assertNull( $schema->get( 'nope' ) );
	}

	public function test_option_name_is_derived_from_the_slug(): void {
		$this->assertSame( 'joo_demo_theme_settings', ( new SettingsRepository( 'demo-theme', new SettingsSchema() ) )->optionName() );
	}

	public function test_every_default_passes_its_own_sanitiser(): void {
		Functions\when( 'sanitize_text_field' )->alias( static fn( $v ) => trim( (string) $v ) );
		$sanitizer = new Sanitizer();
		$schema    = new SettingsSchema();
		foreach ( $schema->all() as $key => $definition ) {
			$definition['key'] = $key;
			$result            = $sanitizer->sanitize( $definition, $definition['default'] );
			$this->assertTrue( $result['ok'], "$key default must be valid: " . $result['error'] );
			$this->assertSame( $definition['default'], $result['value'], "$key default must survive sanitising" );
		}
	}
}
