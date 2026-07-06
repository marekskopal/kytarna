<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

use Kytarna\Model\Entity\SongTab;

final readonly class McpSongTabDto
{
	public function __construct(
		public int $id,
		public int $songId,
		public string $name,
		public string $alphaTex,
		public string $sourceType,
		public ?int $originalFileId,
		public ?int $tempo,
		public ?string $tuning,
		public ?int $trackCount,
	) {
	}

	public static function fromEntity(SongTab $tab): self
	{
		return new self(
			id: $tab->id,
			songId: $tab->song->id,
			name: $tab->name,
			alphaTex: $tab->alphatexContent,
			sourceType: $tab->sourceType->value,
			originalFileId: $tab->originalFile?->id,
			tempo: $tab->tempo,
			tuning: $tab->tuning,
			trackCount: $tab->trackCount,
		);
	}
}
