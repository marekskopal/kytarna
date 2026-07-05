<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use Ukolio\Model\Repository\TaskTagRepository;

#[Entity(repositoryClass: TaskTagRepository::class)]
class TaskTag extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Task::class)]
		public readonly Task $task,
		#[ManyToOne(entityClass: Tag::class)]
		public readonly Tag $tag,
	) {
	}
}
