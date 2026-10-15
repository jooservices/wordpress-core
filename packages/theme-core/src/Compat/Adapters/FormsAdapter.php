<?php
/**
 * Contact Form 7 adapter.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Adapters;

use JOOservices\ThemeCore\Compat\Forms;
use JOOservices\ThemeCore\Support\Hooks;

/**
 * Loads Contact Form 7 CSS and JS only on pages with a form (switchable with `joo/compat/forms_conditional`).
 *
 * @internal
 */
final class FormsAdapter extends AbstractAdapter {

	/**
	 * Key.
	 */
	public function key(): string {
		return 'cf7';
	}

	/**
	 * Notice.
	 */
	public function notice(): string {
		return __( 'Contact Form 7 assets load only on pages that contain a form.', 'joo-core' );
	}

	/**
	 * Hooks.
	 */
	public function boot(): void {
		add_filter( 'wpcf7_load_js', array( $this, 'load' ) );
		add_filter( 'wpcf7_load_css', array( $this, 'load' ) );
	}

	/**
	 * Filter: whether to load Contact Form 7 assets.
	 *
	 * @param mixed $load Current decision.
	 */
	public function load( $load ): bool {
		if ( ! (bool) Hooks::filter( 'joo/compat/forms_conditional', true ) ) {
			return (bool) $load;
		}
		return (bool) $load && Forms::pageNeedsForm();
	}
}
