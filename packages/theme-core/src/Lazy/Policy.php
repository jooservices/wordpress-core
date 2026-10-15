<?php
/**
 * Lazy loading policy: the single decision point.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Lazy;

use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * Decides whether an image, iframe, fragment, section, comment list or terminal should be lazy.
 *
 * Order: module off, request context, per-post override, type switch, yield to third-party plugins,
 * eager count / LCP, filter joo/lazy/should_lazy.
 *
 * @api
 */
final class Policy {

	/**
	 * Setting key per type.
	 */
	private const TYPE_KEYS = array(
		'image'    => 'lazy.images',
		'iframe'   => 'lazy.iframes',
		'fragment' => 'lazy.fragments',
		'section'  => 'lazy.sections',
		'comments' => 'lazy.comments',
		'terminal' => 'lazy.terminal',
	);

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Returns true for requests where lazy loading must not apply (feeds, REST, AJAX, CLI, previews, embeds).
	 *
	 * @var callable
	 */
	private $contextProbe;

	/**
	 * Returns the per-post override (default|on|off) for the current singular post.
	 *
	 * @var callable
	 */
	private $overrideProbe;

	/**
	 * Depth of fragment rendering (REST fragments render below-the-fold HTML that should be lazy).
	 *
	 * @var int
	 */
	private int $fragmentDepth = 0;

	/**
	 * Creates the policy.
	 *
	 * @param Settings      $settings      Settings.
	 * @param callable|null $contextProbe  Request context probe (tests).
	 * @param callable|null $overrideProbe Post override probe (tests).
	 */
	public function __construct( Settings $settings, ?callable $contextProbe = null, ?callable $overrideProbe = null ) {
		$this->settings      = $settings;
		$this->contextProbe  = $contextProbe ?? array( $this, 'excludedContext' );
		$this->overrideProbe = $overrideProbe ?? array( $this, 'postOverride' );
	}

	/**
	 * Decides for one item.
	 *
	 * @param string               $type One of image, iframe, fragment, section, comments, terminal.
	 * @param array<string, mixed> $ctx  Context: index (0-based position among images), lcp (bool).
	 */
	public function decide( string $type, array $ctx = array() ): Decision {
		if ( ! (bool) $this->settings->get( 'lazy.enabled', true ) || ! (bool) $this->settings->get( 'modules.lazy.enabled', true ) ) {
			return $this->final( new Decision( false, 'module-off' ), $type, $ctx );
		}
		if ( 0 === $this->fragmentDepth && (bool) ( $this->contextProbe )() ) {
			return $this->final( new Decision( false, 'context' ), $type, $ctx );
		}

		$override = (string) ( $this->overrideProbe )();
		if ( 'off' === $override ) {
			return $this->final( new Decision( false, 'post-off' ), $type, $ctx );
		}
		$forced = 'on' === $override;

		$key = self::TYPE_KEYS[ $type ] ?? null;
		if ( ! $forced && null !== $key && ! (bool) $this->settings->get( $key, true ) ) {
			return $this->final( new Decision( false, 'type-off' ), $type, $ctx );
		}

		if ( in_array( $type, array( 'image', 'iframe' ), true ) && (bool) $this->settings->get( 'lazy.yield_to_plugins', true ) ) {
			$provider = Hooks::filter( 'joo/lazy/provider', null );
			if ( is_string( $provider ) && '' !== $provider ) {
				return $this->final( new Decision( false, 'yield:' . $provider ), $type, $ctx );
			}
		}

		if ( 'image' === $type ) {
			if ( ! empty( $ctx['lcp'] ) ) {
				return $this->final( new Decision( false, 'lcp' ), $type, $ctx );
			}
			if ( isset( $ctx['index'] ) && (int) $ctx['index'] < (int) $this->settings->get( 'lazy.eager_count', 1 ) ) {
				return $this->final( new Decision( false, 'eager' ), $type, $ctx );
			}
		}

		return $this->final( new Decision( true, $forced ? 'post-on' : 'default' ), $type, $ctx );
	}

	/**
	 * Marks the start of fragment rendering (REST fragments are always below the fold, so images inside are lazy).
	 */
	public function enterFragment(): void {
		++$this->fragmentDepth;
	}

	/**
	 * Marks the end of fragment rendering.
	 */
	public function leaveFragment(): void {
		$this->fragmentDepth = max( 0, $this->fragmentDepth - 1 );
	}

	/**
	 * Runs a callable as a fragment renderer.
	 *
	 * @param callable $callback Renderer.
	 * @return mixed
	 */
	public function inFragment( callable $callback ) {
		$this->enterFragment();
		try {
			return $callback();
		} finally {
			$this->leaveFragment();
		}
	}

	/**
	 * Default context probe.
	 */
	public function excludedContext(): bool {
		return is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) || is_preview() || is_customize_preview() || is_embed();
	}

	/**
	 * Default per-post override probe (singular views only).
	 */
	public function postOverride(): string {
		if ( ! is_singular() ) {
			return 'default';
		}
		$value = get_post_meta( (int) get_queried_object_id(), '_joo_lazy_override', true );
		return in_array( $value, array( 'on', 'off' ), true ) ? (string) $value : 'default';
	}

	/**
	 * Applies the final filter.
	 *
	 * @param Decision             $decision Decision so far.
	 * @param string               $type     Type.
	 * @param array<string, mixed> $ctx      Context.
	 */
	private function final( Decision $decision, string $type, array $ctx ): Decision {
		$lazy = Hooks::filter( 'joo/lazy/should_lazy', $decision->lazy, $type, $ctx );
		return (bool) $lazy === $decision->lazy ? $decision : new Decision( (bool) $lazy, 'filter' );
	}
}
