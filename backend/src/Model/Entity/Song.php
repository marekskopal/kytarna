<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity;

use DateTimeImmutable;
use Kytarna\Model\Entity\Enum\DifficultyEnum;
use Kytarna\Model\Entity\Enum\LearningStatusEnum;
use Kytarna\Model\Repository\SongRepository;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;

/**
 * A workspace-level song. It lives standalone in the workspace library, or is attached to a `course`
 * (where it appears on that course's board like a lecture). When attached it is assigned a per-course
 * `sequenceNumber` for a `PREFIX-N` code; standalone songs have neither.
 */
#[Entity(repositoryClass: SongRepository::class)]
class Song extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Workspace::class)]
		public readonly Workspace $workspace,
		#[ColumnEnum(enum: LearningStatusEnum::class)]
		public LearningStatusEnum $status,
		#[Column(type: Type::String)]
		public string $name,
		#[Column(type: Type::Int)]
		public int $position,
		#[ManyToOne(entityClass: Course::class, nullable: true)]
		public ?Course $course = null,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $sequenceNumber = null,
		#[Column(type: Type::Text, nullable: true)]
		public ?string $description = null,
		#[Column(type: Type::String, nullable: true)]
		public ?string $tuning = null,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $capo = null,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $targetTempoBpm = null,
		#[ColumnEnum(enum: DifficultyEnum::class, nullable: true)]
		public ?DifficultyEnum $difficulty = null,
		#[Column(type: Type::String, nullable: true)]
		public ?string $authorName = null,
		#[Column(type: Type::String, nullable: true)]
		public ?string $albumName = null,
		#[Column(type: Type::String, size: 512, nullable: true)]
		public ?string $coverImageKey = null,
		#[Column(type: Type::String, nullable: true)]
		public ?string $coverImageMimeType = null,
		#[Column(type: Type::Boolean, default: false)]
		public bool $createdByAgent = false,
		#[Column(type: Type::Timestamp, nullable: true)]
		public ?DateTimeImmutable $archivedAt = null,
	) {
	}
}
