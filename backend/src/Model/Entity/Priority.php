<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Repository\PriorityRepository;

#[Entity(repositoryClass: PriorityRepository::class)]
class Priority extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Workspace::class)]
		public readonly Workspace $workspace,
		#[Column(type: Type::String)]
		public string $name,
		#[Column(type: Type::String, size: 7)]
		public string $color,
		#[Column(type: Type::Int)]
		public int $position,
		#[Column(type: Type::Boolean, default: false)]
		public bool $isDefault = false,
	) {
	}
}
