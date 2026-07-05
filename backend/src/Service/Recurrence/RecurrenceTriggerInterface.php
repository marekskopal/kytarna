<?php

declare(strict_types=1);

namespace Ukolio\Service\Recurrence;

use Ukolio\Model\Entity\Event;

interface RecurrenceTriggerInterface
{
	/** Spawn-on-complete hook: enqueues the next occurrence when a recurring task is moved to Finish. */
	public function onEvent(Event $event): void;
}
