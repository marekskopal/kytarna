<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity;

use Kytarna\Model\Entity\Enum\TabSourceTypeEnum;
use Kytarna\Model\Repository\SongTabRepository;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;

#[Entity(repositoryClass: SongTabRepository::class)]
class SongTab extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Song::class)]
		public readonly Song $song,
		#[Column(type: Type::String)]
		public string $name,
		#[Column(type: Type::Text)]
		public string $alphatexContent,
		#[ColumnEnum(enum: TabSourceTypeEnum::class)]
		public TabSourceTypeEnum $sourceType,
		#[ManyToOne(entityClass: SongFile::class, name: 'original_file_id', nullable: true)]
		public ?SongFile $originalFile = null,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $tempo = null,
		#[Column(type: Type::String, nullable: true)]
		public ?string $tuning = null,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $trackCount = null,
	) {
	}
}
