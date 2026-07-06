<?php

declare(strict_types=1);

namespace Kytarna\Service\Tab\Exception;

use RuntimeException;

/** Thrown when the tab-service is unreachable or returns an unexpected response. */
final class TabServiceException extends RuntimeException
{
}
