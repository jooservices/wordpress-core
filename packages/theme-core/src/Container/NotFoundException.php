<?php
/**
 * Unknown service id.
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Container;

/**
 * Thrown by Container::get() for an unknown id.
 *
 * @api
 */
final class NotFoundException extends \RuntimeException {
}
