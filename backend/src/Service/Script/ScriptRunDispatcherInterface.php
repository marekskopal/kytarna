<?php

declare(strict_types=1);

namespace Ukolio\Service\Script;

use Ukolio\Model\Entity\Enum\ScriptTriggerEnum;
use Ukolio\Model\Entity\Script;

interface ScriptRunDispatcherInterface
{
	/**
	 * Enqueue a script for asynchronous execution in the v8js worker.
	 *
	 * @param array<string, mixed>|null $event
	 */
	public function dispatch(Script $script, ScriptTriggerEnum $triggerType, ?array $event = null, ?string $scheduledAt = null): void;
}
