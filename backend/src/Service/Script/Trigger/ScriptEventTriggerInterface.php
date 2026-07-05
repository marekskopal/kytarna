<?php

declare(strict_types=1);

namespace Kytario\Service\Script\Trigger;

use Kytario\Model\Entity\Event;

interface ScriptEventTriggerInterface
{
	/** Dispatch runs for any active Event-trigger scripts in the event's workspace that subscribe to its type. */
	public function onEvent(Event $event): void;
}
