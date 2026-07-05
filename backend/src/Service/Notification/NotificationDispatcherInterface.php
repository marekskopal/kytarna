<?php

declare(strict_types=1);

namespace Ukolio\Service\Notification;

use Ukolio\Model\Entity\Enum\NotificationTypeEnum;
use Ukolio\Model\Entity\Event;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\User;

interface NotificationDispatcherInterface
{
	/** Fan out notifications for a freshly recorded event. Best-effort: never throws. */
	public function onEvent(Event $event): void;

	/** Emit a single due-date reminder (used by the notifications:due-tick cron). */
	public function dispatchDueReminder(Task $task, NotificationTypeEnum $type, User $recipient): void;
}
