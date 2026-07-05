<?php

declare(strict_types=1);

namespace Kytario\Service\Recurrence;

use Kytario\Model\Entity\Event;

interface RecurrenceTriggerInterface
{
	/** Spawn-on-complete hook: enqueues the next occurrence when a recurring task is moved to Finish. */
	public function onEvent(Event $event): void;
}
