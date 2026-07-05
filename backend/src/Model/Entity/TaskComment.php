<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use DateTimeImmutable;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Entity\Enum\ActorTypeEnum;
use Ukolio\Model\Repository\TaskCommentRepository;

#[Entity(repositoryClass: TaskCommentRepository::class)]
class TaskComment extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Task::class)]
		public readonly Task $task,
		#[ManyToOne(entityClass: User::class, name: 'author_id')]
		public readonly User $author,
		#[Column(type: Type::Text)]
		public string $body,
		#[ColumnEnum(enum: ActorTypeEnum::class, default: ActorTypeEnum::Human)]
		public readonly ActorTypeEnum $actorType = ActorTypeEnum::Human,
		#[Column(type: Type::String, size: 128, nullable: true)]
		public readonly ?string $mcpClientId = null,
		#[Column(type: Type::String, nullable: true)]
		public readonly ?string $mcpClientName = null,
		// Plain FK id, not a ManyToOne relation: the ORM can't eager-load a self-referential
		// relation (the self-join reuses the table alias), and we only ever need the id here.
		#[Column(type: Type::Int, size: 11, nullable: true)]
		public readonly ?int $parentCommentId = null,
		#[Column(type: Type::Timestamp, nullable: true)]
		public ?DateTimeImmutable $editedAt = null,
	) {
	}
}
