<?php
/**
 * Container resolution failure (for example a circular dependency).
 *
 * @package JOOservices\ThemeCore
 */

declare(strict_types=1);

namespace JOOservices\ThemeCore\Container;

/**
 * Thrown when a service cannot be built.
 *
 * @internal
 */
final class ContainerException extends \RuntimeException {
}
