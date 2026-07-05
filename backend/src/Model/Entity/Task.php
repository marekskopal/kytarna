<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use DateTimeImmutable;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Repository\TaskRepository;

#[Entity(repositoryClass: TaskRepository::class)]
class Task extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Project::class)]
		public readonly Project $project,
		#[ManyToOne(entityClass: Status::class)]
		public Status $status,
		#[ManyToOne(entityClass: User::class, nullable: true)]
		public ?User $assignee,
		#[Column(type: Type::String)]
		public string $name,
		#[Column(type: Type::Text, nullable: true)]
		public ?string $description,
		#[ManyToOne(entityClass: Priority::class)]
		public Priority $priority,
		#[Column(type: Type::Date, nullable: true)]
		public ?DateTimeImmutable $dueDate,
		#[Column(type: Type::Int)]
		public int $position,
		#[Column(type: Type::Int)]
		public int $sequenceNumber,
		#[Column(type: Type::Date, nullable: true)]
		public ?DateTimeImmutable $startDate = null,
		#[Column(type: Type::Boolean, default: false)]
		public bool $createdByAgent = false,
		#[Column(type: Type::Timestamp, nullable: true)]
		public ?DateTimeImmutable $archivedAt = null,
	) {
	}
}
