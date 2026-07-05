<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Repository\ProjectFieldRepository;

#[Entity(repositoryClass: ProjectFieldRepository::class)]
class ProjectField extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Project::class)]
		public readonly Project $project,
		#[ManyToOne(entityClass: Field::class)]
		public readonly Field $field,
		#[Column(type: Type::Int)]
		public int $position,
	) {
	}
}
