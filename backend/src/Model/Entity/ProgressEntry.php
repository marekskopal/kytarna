<?php

declare(strict_types=1);

namespace Kytario\Model\Entity;

use DateTimeImmutable;
use Kytario\Model\Repository\ProgressEntryRepository;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;

#[Entity(repositoryClass: ProgressEntryRepository::class)]
class ProgressEntry extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Lecture::class)]
		public readonly Lecture $lecture,
		#[Column(type: Type::Date)]
		public DateTimeImmutable $practicedAt,
		#[Column(type: Type::Text, nullable: true)]
		public ?string $note = null,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $tempoBpm = null,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $durationMinutes = null,
	) {
	}
}
