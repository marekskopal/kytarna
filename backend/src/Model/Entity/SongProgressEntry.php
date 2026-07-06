<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity;

use DateTimeImmutable;
use Kytarna\Model\Repository\SongProgressEntryRepository;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;

#[Entity(repositoryClass: SongProgressEntryRepository::class)]
class SongProgressEntry extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Song::class)]
		public readonly Song $song,
		#[ManyToOne(entityClass: User::class)]
		public readonly User $user,
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
