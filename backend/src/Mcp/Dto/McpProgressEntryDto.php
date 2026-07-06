<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

use Kytarna\Model\Entity\ProgressEntry;

final readonly class McpProgressEntryDto
{
	public function __construct(
		public int $id,
		public int $lectureId,
		public string $practicedAt,
		public ?string $note,
		public ?int $tempoBpm,
		public ?int $durationMinutes,
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
		);
	}
}
