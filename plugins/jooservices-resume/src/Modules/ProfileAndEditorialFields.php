<?php
/**
 * Profile and editorial fields module.
 *
 * @package JOOservices\Resume
 */

declare(strict_types=1);

namespace JOOservices\Resume\Modules;

use JOOservices\Resume\Meta\Featured;
use JOOservices\Resume\Meta\TermMeta;
use JOOservices\Resume\Meta\UserMeta;

/**
 * Registers the profile, topic and featured-post fields owned by the Resume plugin.
 */
final class ProfileAndEditorialFields {

	/**
	 * Hooks the module's field managers into WordPress.
	 */
	public function hook(): void {
		( new TermMeta() )->hook();
		( new UserMeta() )->hook();
		( new Featured() )->hook();
	}
}
