<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\Enum\LocaleEnum;
use Ukolio\Model\Entity\Enum\NotificationTypeEnum;

/**
 * Payload for the notification-email queue (U-83). Carries everything the email worker needs to
 * render a localized message without touching the database again.
 *
 * @implements ArrayFactoryInterface<array{
 *     recipientEmail: string,
 *     recipientName: string,
 *     locale: value-of<LocaleEnum>,
 *     type: value-of<NotificationTypeEnum>,
 *     actorName: string|null,
 *     taskCode: string|null,
 *     taskName: string|null,
 *     projectId: int|null,
 *     statusName: string|null,
 *     dueDate: string|null,
 * }>
 */
final readonly class NotificationEmailQueueDto implements ArrayFactoryInterface
{
	public function __construct(
		public string $recipientEmail,
		public string $recipientName,
		public LocaleEnum $locale,
		public NotificationTypeEnum $type,
		public ?string $actorName,
		public ?string $taskCode,
		public ?string $taskName,
		public ?int $projectId,
		public ?string $statusName,
		public ?string $dueDate,
	) {
	}

	public static function fromArray(array $data): static
	{
		return new self(
			recipientEmail: $data['recipientEmail'],
			recipientName: $data['recipientName'],
			locale: LocaleEnum::from($data['locale']),
			type: NotificationTypeEnum::from($data['type']),
			actorName: $data['actorName'] ?? null,
			taskCode: $data['taskCode'] ?? null,
			taskName: $data['taskName'] ?? null,
			projectId: $data['projectId'] ?? null,
			statusName: $data['statusName'] ?? null,
			dueDate: $data['dueDate'] ?? null,
		);
	}
}
