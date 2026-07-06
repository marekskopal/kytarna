<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\Enum\NotificationTypeEnum;
use Kytarna\Model\Entity\Notification;
use Kytarna\Model\Entity\User;

interface NotificationProviderInterface
{
	/** @param array<string, mixed> $data */
	public function create(
		User $recipient,
		int $workspaceId,
		NotificationTypeEnum $type,
		?int $lectureId,
		?int $courseId,
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
}
