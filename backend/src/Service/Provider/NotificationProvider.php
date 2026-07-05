<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use DateTimeImmutable;
use Kytario\Model\Entity\Enum\NotificationTypeEnum;
use Kytario\Model\Entity\Notification;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\NotificationRepository;
use const JSON_THROW_ON_ERROR;

final readonly class NotificationProvider implements NotificationProviderInterface
{
	public function __construct(private NotificationRepository $notificationRepository)
	{
	}

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
	): Notification {
		$now = new DateTimeImmutable();
		$notification = new Notification(
			user: $recipient,
			workspaceId: $workspaceId,
			type: $type,
			lectureId: $lectureId,
			courseId: $courseId,
			actorId: $actorId,
			actorName: $actorName,
			data: $data === [] ? null : json_encode($data, JSON_THROW_ON_ERROR),
		);
		$notification->createdAt = $now;
		$notification->updatedAt = $now;
		$this->notificationRepository->persist($notification);

		return $notification;
	}

	/** @return list<Notification> */
	public function listForUser(User $user, int $limit, int $offset, bool $unreadOnly): array
	{
		$result = [];
		foreach ($this->notificationRepository->findForUser($user->id, $limit, $offset, $unreadOnly) as $notification) {
			$result[] = $notification;
		}
		return $result;
	}

	public function unreadCount(User $user): int
	{
		return $this->notificationRepository->countUnread($user->id);
	}

	public function getNotification(int $id): ?Notification
	{
		return $this->notificationRepository->findOneById($id);
	}

	public function markRead(Notification $notification): void
	{
		if ($notification->readAt !== null) {
			return;
		}

		$now = new DateTimeImmutable();
		$notification->readAt = $now;
		$notification->updatedAt = $now;
		$this->notificationRepository->persist($notification);
	}

	public function markAllRead(User $user): int
	{
		$now = new DateTimeImmutable();
		$count = 0;
		foreach ($this->notificationRepository->findUnreadForUser($user->id) as $notification) {
			$notification->readAt = $now;
			$notification->updatedAt = $now;
			$this->notificationRepository->persist($notification);
			$count++;
		}
		return $count;
	}

	public function delete(Notification $notification): void
	{
		$this->notificationRepository->delete($notification);
	}
}
