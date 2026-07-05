<?php

declare(strict_types=1);

namespace Kytario\Dto;

final readonly class NotificationListDto
{
	/** @param list<NotificationDto> $notifications */
	public function __construct(public array $notifications, public int $unreadCount,)
	{
	}
}
