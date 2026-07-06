<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity;

use Kytarna\Model\Entity\Enum\TabSourceTypeEnum;
use Kytarna\Model\Repository\TabRepository;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;

#[Entity(repositoryClass: TabRepository::class)]
class Tab extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Lecture::class)]
		public readonly Lecture $lecture,
		#[Column(type: Type::String)]
		public string $name,
		#[Column(type: Type::Text)]
		public string $alphatexContent,
		#[ColumnEnum(enum: TabSourceTypeEnum::class)]
		public TabSourceTypeEnum $sourceType,
		#[ManyToOne(entityClass: LectureFile::class, name: 'original_file_id', nullable: true)]
		public ?LectureFile $originalFile = null,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $tempo = null,
		#[Column(type: Type::String, nullable: true)]
		public ?string $tuning = null,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $trackCount = null,
	) {
	}
}
