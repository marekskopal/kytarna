<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use Ukolio\Model\Entity\Enum\TaskRelationTypeEnum;
use Ukolio\Model\Repository\TaskRelationRepository;

#[Entity(repositoryClass: TaskRelationRepository::class)]
class TaskRelation extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Task::class)]
		public readonly Task $sourceTask,
		#[ManyToOne(entityClass: Task::class)]
		public readonly Task $targetTask,
		#[ColumnEnum(enum: TaskRelationTypeEnum::class)]
		public readonly TaskRelationTypeEnum $type,
		#[ManyToOne(entityClass: User::class, nullable: true)]
		public readonly ?User $createdBy,
	) {
	}
}
