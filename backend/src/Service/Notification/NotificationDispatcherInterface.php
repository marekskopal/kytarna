<?php

declare(strict_types=1);

namespace Kytario\Service\Notification;

use Kytario\Model\Entity\Enum\NotificationTypeEnum;
use Kytario\Model\Entity\Event;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\User;

interface NotificationDispatcherInterface
{
	/** Fan out notifications for a freshly recorded event. Best-effort: never throws. */
	public function onEvent(Event $event): void;

	/** Emit a single due-date reminder (used by the notifications:due-tick cron). */
	public function dispatchDueReminder(Task $task, NotificationTypeEnum $type, User $recipient): void;
}
