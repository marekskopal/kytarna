<?php

declare(strict_types=1);

namespace Kytario\Model\Entity;

use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use Kytario\Model\Repository\TaskTagRepository;

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
