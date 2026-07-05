<?php

declare(strict_types=1);

namespace Kytario\Model\Entity\Enum;

enum NotificationTypeEnum: string
{
	case TaskAssigned = 'TaskAssigned';
	case TaskComment = 'TaskComment';
	case TaskMention = 'TaskMention';
	case TaskMoved = 'TaskMoved';
	case DueSoon = 'DueSoon';
	case DueToday = 'DueToday';

	/**
	 * Directed / time-critical notifications also go out by email; the noisier watcher
	 * pings (a comment on, or a move of, a watched task) stay in-app only to spare inboxes.
	 */
	public function isEmailable(): bool
	{
		return match ($this) {
			self::TaskMention, self::TaskAssigned, self::DueSoon, self::DueToday => true,
			self::TaskComment, self::TaskMoved => false,
		};
	}
}
