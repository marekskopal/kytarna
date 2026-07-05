<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use DateTimeImmutable;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Entity\Enum\NotificationTypeEnum;
use Ukolio\Model\Repository\NotificationRepository;

/**
 * A per-user, in-app notification (U-83). The recipient is the only ORM relation; task/project/actor
 * are denormalised plain ints (mirroring Event.taskId) so deleting a task never blocks and the
 * notification survives. `data` holds a small JSON blob (taskCode, taskName, statusName, …) that the
 * frontend renders via i18n, keeping the message locale-agnostic.
 */
#[Entity(repositoryClass: NotificationRepository::class)]
class Notification extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: User::class)]
		public readonly User $user,
		#[Column(type: Type::Int, size: 11)]
		public int $workspaceId,
		#[ColumnEnum(enum: NotificationTypeEnum::class)]
		public NotificationTypeEnum $type,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $taskId = null,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $projectId = null,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $actorId = null,
		#[Column(type: Type::String, nullable: true)]
		public ?string $actorName = null,
		#[Column(type: Type::Text, nullable: true)]
		public ?string $data = null,
		#[Column(type: Type::Timestamp, nullable: true)]
		public ?DateTimeImmutable $readAt = null,
	) {
	}
}
