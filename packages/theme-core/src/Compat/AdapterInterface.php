<?php
/**
 * Plugin adapter contract.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Compat;

/**
 * An adapter changes core behaviour for one plugin. It must not depend on the plugin's code at load time.
 *
 * @internal
 */
interface AdapterInterface {

	/**
	 * Plugin key (see Signatures).
	 */
	public function key(): string;

	/**
	 * Whether the plugin is active and the adapter applies.
	 */
	public function supports(): bool;

	/**
	 * Registers hooks (called only when supports() is true, wrapped in try/catch by the registry).
	 */
	public function boot(): void;

	/**
	 * Capabilities the plugin provides.
	 *
	 * @return list<string>
	 */
	public function capabilities(): array;

	/**
	 * Text for the Compatibility tab.
	 */
	public function notice(): string;
}
