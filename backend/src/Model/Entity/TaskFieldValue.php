<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Repository\TaskFieldValueRepository;

#[Entity(repositoryClass: TaskFieldValueRepository::class)]
class TaskFieldValue extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Task::class)]
		public readonly Task $task,
		#[ManyToOne(entityClass: Field::class)]
		public readonly Field $field,
		#[Column(type: Type::Text, nullable: true)]
		public ?string $value,
	) {
	}
}
