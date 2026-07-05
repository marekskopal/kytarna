<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Repository\WorkflowRepository;

#[Entity(repositoryClass: WorkflowRepository::class)]
class Workflow extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Project::class)]
		public readonly Project $project,
		#[Column(type: Type::String)]
		public string $name,
	) {
	}
}
