<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

use Kytarna\Model\Entity\SongProgressEntry;

final readonly class McpSongProgressEntryDto
{
	public function __construct(
		public int $id,
		public int $songId,
		public string $practicedAt,
		public ?string $note,
		public ?int $tempoBpm,
		public ?int $durationMinutes,
	) {
	}

	public static function fromEntity(SongProgressEntry $entry): self
	{
		return new self(
			id: $entry->id,
			songId: $entry->song->id,
			practicedAt: $entry->practicedAt->format('Y-m-d'),
			note: $entry->note,
			tempoBpm: $entry->tempoBpm,
			durationMinutes: $entry->durationMinutes,
		);
	}
}
