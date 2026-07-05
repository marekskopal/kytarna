<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use DateTimeImmutable;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Repository\TaskChecklistItemRepository;

#[Entity(repositoryClass: TaskChecklistItemRepository::class)]
class TaskChecklistItem extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Task::class)]
		public readonly Task $task,
		#[Column(type: Type::String, size: 500)]
		public string $text,
		#[Column(type: Type::Int)]
		public int $position,
		#[Column(type: Type::Timestamp, nullable: true)]
		public ?DateTimeImmutable $checkedAt = null,
		#[ManyToOne(entityClass: User::class, name: 'checked_by_id', nullable: true)]
		public ?User $checkedBy = null,
		#[Column(type: Type::Date, nullable: true)]
		public ?DateTimeImmutable $dueDate = null,
		#[ManyToOne(entityClass: User::class, name: 'assignee_id', nullable: true)]
		public ?User $assignee = null,
	) {
	}
}
