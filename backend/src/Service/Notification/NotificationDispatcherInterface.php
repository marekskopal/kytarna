<?php

declare(strict_types=1);

namespace Kytarna\Service\Notification;

use Kytarna\Model\Entity\Event;

interface NotificationDispatcherInterface
{
	/** Fan out notifications for a freshly recorded event. Best-effort: never throws. */
	public function onEvent(Event $event): void;
}
