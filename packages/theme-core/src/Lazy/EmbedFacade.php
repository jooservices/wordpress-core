<?php
/**
 * Click-to-load facades for YouTube and Vimeo embeds.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Lazy;

use JOOservices\ThemeCore\Config\Settings;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * Replaces provider iframes by a <joo-embed> element that holds a normal link (the no-JS fallback). The
 * iframe is created on click, from the privacy-friendly domain, so no third-party request happens before.
 *
 * @internal
 */
final class EmbedFacade {

	/**
	 * Policy.
	 *
	 * @var Policy
	 */
	private Policy $policy;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Creates the service.
	 *
	 * @param Policy   $policy   Policy.
	 * @param Settings $settings Settings.
	 */
	public function __construct( Policy $policy, Settings $settings ) {
		$this->policy   = $policy;
		$this->settings = $settings;
	}

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_filter( 'embed_oembed_html', array( $this, 'filter' ), 20, 4 );
	}

	/**
	 * Provider definitions: pattern (named group id), watch URL, thumbnail URL.
	 *
	 * @return array<string, array{pattern: string, watch: string, thumb: string}>
	 */
	public function providers(): array {
		$providers = array(
			'youtube' => array(
				'pattern' => '#(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^\#]*&)?v=|embed/|shorts/|live/)|youtu\.be/)(?P<id>[A-Za-z0-9_-]{11})#',
				'watch'   => 'https://www.youtube.com/watch?v=%s',
				'thumb'   => 'https://i.ytimg.com/vi/%s/hqdefault.jpg',
			),
			'vimeo'   => array(
				'pattern' => '#(?:player\.)?vimeo\.com/(?:video/)?(?P<id>\d{5,12})#',
				'watch'   => 'https://vimeo.com/%s',
				'thumb'   => '',
			),
		);
		$filtered  = Hooks::filter( 'joo/lazy/embed_providers', $providers );
		return is_array( $filtered ) ? $filtered : $providers;
	}

	/**
	 * Filter callback for embed_oembed_html.
	 *
	 * @param string               $html    Embed HTML.
	 * @param string               $url     Embedded URL.
	 * @param array<string, mixed> $attr    Embed attributes.
	 * @param int                  $post_id Post id.
	 */
	public function filter( string $html, string $url, array $attr, int $post_id ): string {
		unset( $attr, $post_id );
		if ( ! $this->policy->decide( 'iframe' )->lazy ) {
			return $html;
		}
		foreach ( $this->providers() as $name => $provider ) {
			if ( 1 !== preg_match( $provider['pattern'], $url, $m ) ) {
				continue;
			}
			$id = (string) $m['id'];
			return $this->markup( (string) $name, $id, $provider, $url, $html );
		}
		return $html;
	}

	/**
	 * Builds the facade markup.
	 *
	 * @param string                                             $name     Provider name.
	 * @param string                                             $id       Video id.
	 * @param array{pattern: string, watch: string, thumb: string} $provider Provider.
	 * @param string                                             $url      Original URL.
	 * @param string                                             $html     Original embed HTML.
	 */
	private function markup( string $name, string $id, array $provider, string $url, string $html ): string {
		$title = 'Video';
		if ( 1 === preg_match( '/<iframe[^>]*\stitle="([^"]*)"/i', $html, $t ) && '' !== trim( $t[1] ) ) {
			$title = html_entity_decode( $t[1], ENT_QUOTES, 'UTF-8' );
		}
		$start = 0;
		if ( 1 === preg_match( '/[?&#](?:t|start)=(\d+)/', $url, $s ) ) {
			$start = (int) $s[1];
		}
		$watch = sprintf( $provider['watch'], rawurlencode( $id ) ) . ( $start > 0 && 'youtube' === $name ? '&t=' . $start . 's' : '' );
		$thumb = '';
		if ( 'remote' === (string) $this->settings->get( 'lazy.embed_thumbnails', 'local' ) && '' !== $provider['thumb'] ) {
			$thumb = '<img class="joo-embed__thumb" src="' . esc_url( sprintf( $provider['thumb'], rawurlencode( $id ) ) ) . '" alt="" width="480" height="360" loading="lazy" decoding="async">';
		}
		wp_enqueue_script_module( 'joo-embed' );
		return '<joo-embed class="joo-embed" provider="' . esc_attr( $name ) . '" video="' . esc_attr( $id ) . '" start="' . esc_attr( (string) $start ) . '" title="' . esc_attr( $title ) . '">'
			. '<a class="joo-embed__link" href="' . esc_url( $watch ) . '" rel="noopener">' . $thumb
			. '<span class="joo-embed__play" aria-hidden="true">&#9654;</span>'
			. '<span class="joo-embed__label">' . esc_html( sprintf( /* translators: %s: video title. */ __( 'Play video: %s', 'joo-core' ), $title ) ) . '</span></a></joo-embed>';
	}
}
