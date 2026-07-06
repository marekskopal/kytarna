<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\ProgressEntry;
use const DATE_ATOM;

final readonly class ProgressEntryDto
{
	public function __construct(
		public int $id,
		public int $lectureId,
		public string $practicedAt,
		public ?string $note,
		public ?int $tempoBpm,
		public ?int $durationMinutes,
		public string $createdAt,
		public string $updatedAt,
	) {
	}

	public static function fromEntity(ProgressEntry $entry): self
	{
		return new self(
			id: $entry->id,
			lectureId: $entry->lecture->id,
			practicedAt: $entry->practicedAt->format('Y-m-d'),
			note: $entry->note,
			tempoBpm: $entry->tempoBpm,
			durationMinutes: $entry->durationMinutes,
			createdAt: $entry->createdAt->format(DATE_ATOM),
			updatedAt: $entry->updatedAt->format(DATE_ATOM),
		);
	}
}
