<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Repository\TaskTemplateRepository;

#[Entity(repositoryClass: TaskTemplateRepository::class)]
class TaskTemplate extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Workspace::class)]
		public readonly Workspace $workspace,
		#[Column(type: Type::String)]
		public string $name,
		#[Column(type: Type::Text)]
		public string $payload,
	) {
	}
}
