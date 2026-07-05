<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\Enum\NotificationTypeEnum;
use Ukolio\Model\Entity\Notification;
use Ukolio\Model\Entity\User;

interface NotificationProviderInterface
{
	/** @param array<string, mixed> $data */
	public function create(
		User $recipient,
		int $workspaceId,
		NotificationTypeEnum $type,
		?int $taskId,
		?int $projectId,
		?int $actorId,
		?string $actorName,
		array $data,
	): Notification;

	/** @return list<Notification> */
	public function listForUser(User $user, int $limit, int $offset, bool $unreadOnly): array;

	public function unreadCount(User $user): int;

	public function getNotification(int $id): ?Notification;

	public function markRead(Notification $notification): void;

	/** @return int number of notifications marked read */
	public function markAllRead(User $user): int;

	public function delete(Notification $notification): void;

	/** True if a Due reminder of this type for this task+user was already created today. */
	public function dueReminderExistsToday(int $userId, int $taskId, NotificationTypeEnum $type): bool;
}
