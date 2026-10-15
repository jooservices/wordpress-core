<?php
/**
 * Base adapter.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat\Adapters;

use JOOservices\ThemeCore\Compat\AdapterInterface;
use JOOservices\ThemeCore\Compat\Detector;
use JOOservices\ThemeCore\Compat\Signatures;

/**
 * Detection and capabilities from the signature table.
 *
 * @internal
 */
abstract class AbstractAdapter implements AdapterInterface {

	/**
	 * Detector.
	 *
	 * @var Detector
	 */
	protected Detector $detector;

	/**
	 * Creates the adapter.
	 *
	 * @param Detector $detector Detector.
	 */
	public function __construct( Detector $detector ) {
		$this->detector = $detector;
	}

	/**
	 * Active?
	 */
	public function supports(): bool {
		return $this->detector->active( $this->key() );
	}

	/**
	 * Nothing to register by default.
	 */
	public function boot(): void {
	}

	/**
	 * Capabilities from the signature.
	 *
	 * @return list<string>
	 */
	public function capabilities(): array {
		return Signatures::all()[ $this->key() ]['provides'] ?? array();
	}

	/**
	 * Default notice.
	 */
	public function notice(): string {
		return '';
	}
}
