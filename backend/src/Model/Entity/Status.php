<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Entity\Enum\StatusTypeEnum;
use Ukolio\Model\Repository\StatusRepository;

#[Entity(repositoryClass: StatusRepository::class)]
class Status extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Workflow::class)]
		public readonly Workflow $workflow,
		#[Column(type: Type::String)]
		public string $name,
		#[Column(type: Type::String, size: 7)]
		public string $color,
		#[Column(type: Type::Int)]
		public int $position,
		#[ColumnEnum(enum: StatusTypeEnum::class)]
		public StatusTypeEnum $type,
	) {
	}
}
