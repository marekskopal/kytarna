<?php

declare(strict_types=1);

namespace Kytario\Model\Entity;

use Kytario\Model\Repository\WorkflowRepository;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;

#[Entity(repositoryClass: WorkflowRepository::class)]
class Workflow extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Course::class)]
		public readonly Course $course,
		#[Column(type: Type::String)]
		public string $name,
	) {
	}
}
