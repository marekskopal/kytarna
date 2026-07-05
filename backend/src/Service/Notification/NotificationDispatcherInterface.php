<?php

declare(strict_types=1);

namespace Kytario\Service\Notification;

use Kytario\Model\Entity\Event;

interface NotificationDispatcherInterface
{
	/** Fan out notifications for a freshly recorded event. Best-effort: never throws. */
	public function onEvent(Event $event): void;
}
