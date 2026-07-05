<?php

declare(strict_types=1);

namespace Kytario\Dto;

use Kytario\Model\Entity\Notification;
use const DATE_ATOM;

final readonly class NotificationDto
{
	/** @param array<string, mixed> $data */
	public function __construct(
		public int $id,
		public string $type,
		public ?int $taskId,
		public ?int $projectId,
		public ?int $actorId,
		public ?string $actorName,
		public array $data,
		public bool $read,
		public string $createdAt,
	) {
	}

	public static function fromEntity(Notification $notification): self
	{
		$data = [];
		$decoded = $notification->data !== null ? json_decode($notification->data, true) : null;
		if (is_array($decoded)) {
			foreach ($decoded as $key => $value) {
				$data[(string) $key] = $value;
			}
		}

		return new self(
			id: $notification->id,
			type: $notification->type->value,
			taskId: $notification->taskId,
			projectId: $notification->projectId,
			actorId: $notification->actorId,
			actorName: $notification->actorName,
			data: $data,
			read: $notification->readAt !== null,
			createdAt: $notification->createdAt->format(DATE_ATOM),
		);
	}
}
