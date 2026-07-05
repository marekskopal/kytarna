<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use RuntimeException;

final class PriorityInUseException extends RuntimeException
{
	public function __construct(public readonly int $dependentTaskCount, ?string $priorityName = null)
	{
		$name = $priorityName ?? 'this priority';

		parent::__construct('Cannot delete ' . $name . ' — ' . $dependentTaskCount . ' task(s) still reference it. Reassign them first.');
	}
}
