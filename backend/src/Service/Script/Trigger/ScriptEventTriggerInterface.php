<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Trigger;

use Ukolio\Model\Entity\Event;

interface ScriptEventTriggerInterface
{
	/** Dispatch runs for any active Event-trigger scripts in the event's workspace that subscribe to its type. */
	public function onEvent(Event $event): void;
}
