<?php
/**
 * Capability-only adapter.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Adapters;

use JOOservices\ThemeCore\Compat\Detector;

/**
 * For plugins that need no behaviour change (Site Kit, TablePress, ACF, Akismet, Wordfence, Classic Editor, WPForms, Fluent Forms).
 *
 * @internal
 */
final class FlagAdapter extends AbstractAdapter {

	/**
	 * Plugin key.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Notice text.
	 *
	 * @var string
	 */
	private string $notice;

	/**
	 * Creates the adapter.
	 *
	 * @param Detector $detector Detector.
	 * @param string   $key      Plugin key.
	 * @param string   $notice   Compatibility tab text.
	 */
	public function __construct( Detector $detector, string $key, string $notice ) {
		parent::__construct( $detector );
		$this->key    = $key;
		$this->notice = $notice;
	}

	/**
	 * Key.
	 */
	public function key(): string {
		return $this->key;
	}

	/**
	 * Notice.
	 */
	public function notice(): string {
		return $this->notice;
	}
}
