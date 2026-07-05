<?php

declare(strict_types=1);

namespace Kytario\Model\Entity;

use Kytario\Model\Repository\ProjectRepository;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;

#[Entity(repositoryClass: ProjectRepository::class)]
class Project extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Workspace::class)]
		public readonly Workspace $workspace,
		#[Column(type: Type::String)]
		public string $name,
		#[Column(type: Type::String, size: 16)]
		public string $prefix,
		#[Column(type: Type::Text, nullable: true)]
		public ?string $description = null,
	) {
	}
}
