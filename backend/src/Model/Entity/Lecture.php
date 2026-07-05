<?php

declare(strict_types=1);

namespace Kytario\Model\Entity;

use DateTimeImmutable;
use Kytario\Model\Repository\LectureRepository;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;

#[Entity(repositoryClass: LectureRepository::class)]
class Lecture extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Course::class)]
		public readonly Course $course,
		#[ManyToOne(entityClass: Status::class)]
		public Status $status,
		#[Column(type: Type::String)]
		public string $name,
		#[Column(type: Type::Text, nullable: true)]
		public ?string $description,
		#[Column(type: Type::Int)]
		public int $position,
		#[Column(type: Type::Int)]
		public int $sequenceNumber,
		#[Column(type: Type::Date, nullable: true)]
		public ?DateTimeImmutable $startDate = null,
		#[Column(type: Type::Boolean, default: false)]
		public bool $createdByAgent = false,
		#[Column(type: Type::Timestamp, nullable: true)]
		public ?DateTimeImmutable $archivedAt = null,
	) {
	}
}
