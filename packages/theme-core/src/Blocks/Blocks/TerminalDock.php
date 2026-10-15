<?php
/**
 * Block joo/terminal-dock.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Blocks\Blocks;

use JOOservices\ThemeCore\Blocks\DynamicBlock;
use JOOservices\ThemeCore\Config\Settings;

/**
 * The docked terminal: a tab plus an empty panel; the terminal itself loads on the first open.
 *
 * @internal
 */
final class TerminalDock extends DynamicBlock {

	/**
	 * Block name.
	 */
	public static function name(): string {
		return 'joo/terminal-dock';
	}

	/**
	 * Data.
	 *
	 * @param array<string, mixed> $attrs Attributes.
	 * @param \WP_Block            $block Block.
	 * @return array<string, mixed>
	 */
	protected function data( array $attrs, \WP_Block $block ): array {
		unset( $attrs, $block );
		/** @var Settings $settings */
		$settings = $this->container->get( Settings::class );
		$enabled  = (bool) $settings->get( 'terminal.enabled', true ) && (bool) $settings->get( 'features.terminal', true );
		if ( $enabled && is_404() && (bool) $settings->get( 'terminal.hide_on_404', true ) ) {
			$enabled = false;
		}
		return array(
			'enabled'     => $enabled,
			'openDefault' => (bool) $settings->get( 'terminal.open_by_default', false ),
			'_class'      => 'joo-term',
		);
	}
}
