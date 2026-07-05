<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Entity\Enum\FieldTypeEnum;
use Ukolio\Model\Repository\FieldRepository;

#[Entity(repositoryClass: FieldRepository::class)]
class Field extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Workspace::class)]
		public readonly Workspace $workspace,
		#[Column(type: Type::String)]
		public string $name,
		#[ColumnEnum(enum: FieldTypeEnum::class)]
		public FieldTypeEnum $type,
		#[Column(type: Type::Boolean)]
		public bool $required,
		#[Column(type: Type::Text, nullable: true)]
		public ?string $defaultValue,
		#[Column(type: Type::Text, nullable: true)]
		public ?string $options,
	) {
	}
}
