<?php
/**
 * Translated strings for the JavaScript runtime.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\I18n;

/**
 * The `i18n` map of the `joo-config` JSON (keys used by `t(key, fallback)` in the view modules).
 *
 * @api
 */
final class JsStrings {

	/**
	 * Hooks WordPress.
	 */
	public function hook(): void {
		add_filter( 'joo/assets/runtime_config', array( $this, 'filter' ) );
	}

	/**
	 * Adds the map to the runtime configuration.
	 *
	 * @param mixed $data Runtime configuration.
	 * @return mixed
	 */
	public function filter( $data ) {
		if ( is_array( $data ) ) {
			$data['i18n'] = array_merge( (array) ( $data['i18n'] ?? array() ), self::map() );
		}
		return $data;
	}

	/**
	 * Key => translated string.
	 *
	 * @return array<string, string>
	 */
	public static function map(): array {
		return array(
			'lazy.retry'                => __( 'Retry', 'joo-core' ),
			'retry'                     => __( 'Retry', 'joo-core' ),
			'load_failed'               => __( 'Could not load this page.', 'joo-core' ),
			'page_of'                   => __( 'Page {page} of {total}', 'joo-core' ),
			'present'                   => __( 'Present', 'joo-core' ),
			'toc.title'                 => __( 'On this page', 'joo-core' ),
			'did_you_mean'              => __( 'Did you mean', 'joo-core' ),
			'terminal.close'            => __( 'Close terminal', 'joo-core' ),
			'terminal.input'            => __( 'Terminal command', 'joo-core' ),
			'terminal.lang_unavailable' => __( 'This page has no translation. Use the language switcher.', 'joo-core' ),
			'terminal.blocked'          => __( 'Blocked: not a link on this site.', 'joo-core' ),
			'terminal.banner'           => __( 'read-only shell · nothing is sent anywhere', 'joo-core' ),
			'terminal.hint'             => __( 'Type ', 'joo-core' ),
			'terminal.loading'          => __( 'loading file system…', 'joo-core' ),
			'terminal.failed'           => __( 'The terminal could not be loaded.', 'joo-core' ),
		);
	}
}
