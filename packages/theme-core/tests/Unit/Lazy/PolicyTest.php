<?php
/**
 * Lazy policy decision matrix.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Tests\Unit\Lazy;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use JOOservices\TestKit\UnitTestCase;
use JOOservices\ThemeCore\Config\Manifest;
use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Config\SettingsRepository;
use JOOservices\ThemeCore\Config\SettingsSchema;
use JOOservices\ThemeCore\Lazy\Policy;

final class PolicyTest extends UnitTestCase {

	/**
	 * @param array<string, mixed> $saved Saved overrides keyed by dotted setting key.
	 */
	private function policy( array $saved = array(), bool $excluded = false, string $override = 'default' ): Policy {
		Functions\when( 'get_option' )->justReturn( $saved );
		$schema   = new SettingsSchema();
		$settings = new Settings( $schema, new Manifest(), new SettingsRepository( 'demo', $schema ) );
		return new Policy( $settings, static fn() => $excluded, static fn() => $override );
	}

	/**
	 * @return array<string, array{0: array<string,mixed>, 1: string, 2: array<string,mixed>, 3: bool, 4: string, 5: bool, 6: string}>
	 */
	public static function matrix(): array {
		return array(
			// saved settings, type, ctx, excluded context, override, expected lazy, expected reason.
			'defaults: image below the fold'          => array( array(), 'image', array( 'index' => 3 ), false, 'default', true, 'default' ),
			'defaults: first image is eager'          => array( array(), 'image', array( 'index' => 0 ), false, 'default', false, 'eager' ),
			'eager count 2: second image eager'       => array( array( 'lazy.eager_count' => 2 ), 'image', array( 'index' => 1 ), false, 'default', false, 'eager' ),
			'eager count 2: third image lazy'         => array( array( 'lazy.eager_count' => 2 ), 'image', array( 'index' => 2 ), false, 'default', true, 'default' ),
			'eager count 0: first image lazy'         => array( array( 'lazy.eager_count' => 0 ), 'image', array( 'index' => 0 ), false, 'default', true, 'default' ),
			'lcp image is never lazy'                 => array(
				array(),
				'image',
				array(
					'index' => 9,
					'lcp'   => true,
				),
				false,
				'default',
				false,
				'lcp',
			),
			'master switch off'                       => array( array( 'lazy.enabled' => false ), 'image', array( 'index' => 9 ), false, 'default', false, 'module-off' ),
			'module disabled'                         => array( array( 'modules.lazy.enabled' => false ), 'fragment', array(), false, 'default', false, 'module-off' ),
			'feeds, REST, ajax, previews'             => array( array(), 'image', array( 'index' => 9 ), true, 'default', false, 'context' ),
			'post override off beats everything'      => array( array(), 'image', array( 'index' => 9 ), false, 'off', false, 'post-off' ),
			'post override on beats the type switch'  => array( array( 'lazy.images' => false ), 'image', array( 'index' => 9 ), false, 'on', true, 'post-on' ),
			'post override on cannot beat module off' => array( array( 'lazy.enabled' => false ), 'image', array( 'index' => 9 ), false, 'on', false, 'module-off' ),
			'images switch off'                       => array( array( 'lazy.images' => false ), 'image', array( 'index' => 9 ), false, 'default', false, 'type-off' ),
			'iframes switch off'                      => array( array( 'lazy.iframes' => false ), 'iframe', array(), false, 'default', false, 'type-off' ),
			'fragments switch off'                    => array( array( 'lazy.fragments' => false ), 'fragment', array(), false, 'default', false, 'type-off' ),
			'sections switch off'                     => array( array( 'lazy.sections' => false ), 'section', array(), false, 'default', false, 'type-off' ),
			'comments switch off'                     => array( array( 'lazy.comments' => false ), 'comments', array(), false, 'default', false, 'type-off' ),
			'terminal switch off'                     => array( array( 'lazy.terminal' => false ), 'terminal', array(), false, 'default', false, 'type-off' ),
			'images off does not disable iframes'     => array( array( 'lazy.images' => false ), 'iframe', array(), false, 'default', true, 'default' ),
			'fragments are lazy by default'           => array( array(), 'fragment', array(), false, 'default', true, 'default' ),
			'sections are lazy by default'            => array( array(), 'section', array(), false, 'default', true, 'default' ),
			'comments are lazy by default'            => array( array(), 'comments', array(), false, 'default', true, 'default' ),
			'terminal is lazy by default'             => array( array(), 'terminal', array(), false, 'default', true, 'default' ),
			'eager count does not apply to fragments' => array( array(), 'fragment', array( 'index' => 0 ), false, 'default', true, 'default' ),
			'unknown type falls back to lazy'         => array( array(), 'mystery', array(), false, 'default', true, 'default' ),
		);
	}

	/**
	 * @dataProvider matrix
	 * @param array<string, mixed> $saved    Saved settings.
	 * @param array<string, mixed> $ctx      Context.
	 */
	public function test_decision_matrix( array $saved, string $type, array $ctx, bool $excluded, string $override, bool $lazy, string $reason ): void {
		$decision = $this->policy( $saved, $excluded, $override )->decide( $type, $ctx );
		$this->assertSame( $lazy, $decision->lazy, 'lazy' );
		$this->assertSame( $reason, $decision->reason, 'reason' );
	}

	public function test_a_lazy_plugin_makes_images_and_iframes_yield_but_not_fragments(): void {
		Filters\expectApplied( 'joo/lazy/provider' )->andReturn( 'wp-rocket' );
		$policy = $this->policy();
		$this->assertSame( 'yield:wp-rocket', $policy->decide( 'image', array( 'index' => 5 ) )->reason );
		$this->assertSame( 'yield:wp-rocket', $policy->decide( 'iframe' )->reason );
		$this->assertTrue( $policy->decide( 'fragment' )->lazy );
	}

	public function test_yield_can_be_switched_off(): void {
		Filters\expectApplied( 'joo/lazy/provider' )->andReturn( 'wp-rocket' );
		$decision = $this->policy( array( 'lazy.yield_to_plugins' => false ) )->decide( 'image', array( 'index' => 5 ) );
		$this->assertTrue( $decision->lazy );
	}

	public function test_the_final_filter_can_flip_a_decision(): void {
		Filters\expectApplied( 'joo/lazy/should_lazy' )->andReturnUsing( static fn( $lazy, $type ) => 'image' === $type ? false : $lazy );
		$policy  = $this->policy();
		$flipped = $policy->decide( 'image', array( 'index' => 5 ) );
		$this->assertFalse( $flipped->lazy );
		$this->assertSame( 'filter', $flipped->reason );
		$this->assertTrue( $policy->decide( 'fragment' )->lazy );
	}

	public function test_fragment_rendering_ignores_the_rest_context_and_nests(): void {
		$policy = $this->policy( array(), true );
		$this->assertSame( 'context', $policy->decide( 'image', array( 'index' => 4 ) )->reason );
		$seen = $policy->inFragment(
			function () use ( $policy ) {
				$inner = $policy->inFragment( fn() => $policy->decide( 'image', array( 'index' => 4 ) ) );
				return array( $inner, $policy->decide( 'image', array( 'index' => 4 ) ) );
			}
		);
		$this->assertTrue( $seen[0]->lazy );
		$this->assertTrue( $seen[1]->lazy, 'still inside the outer fragment after the inner one ended' );
		$this->assertSame( 'context', $policy->decide( 'image', array( 'index' => 4 ) )->reason, 'context applies again afterwards' );
	}

	public function test_fragment_depth_never_goes_negative(): void {
		$policy = $this->policy( array(), true );
		$policy->leaveFragment();
		$policy->leaveFragment();
		$this->assertSame( 'context', $policy->decide( 'fragment' )->reason );
	}

	public function test_default_context_and_override_probes_use_wordpress_conditionals(): void {
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'is_customize_preview' )->justReturn( false );
		Functions\when( 'is_embed' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( array() );
		$schema = new SettingsSchema();
		$policy = new Policy( new Settings( $schema, new Manifest(), new SettingsRepository( 'demo', $schema ) ) );
		$this->assertTrue( $policy->excludedContext(), 'embeds are excluded' );
		Functions\when( 'is_singular' )->justReturn( false );
		$this->assertSame( 'default', $policy->postOverride() );
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_queried_object_id' )->justReturn( 7 );
		Functions\when( 'get_post_meta' )->justReturn( 'off' );
		$this->assertSame( 'off', $policy->postOverride() );
		Functions\when( 'get_post_meta' )->justReturn( 'bogus' );
		$this->assertSame( 'default', $policy->postOverride() );
	}
}
