<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\SongTab;
use const DATE_ATOM;

final readonly class SongTabDto
{
	public function __construct(
		public int $id,
		public int $songId,
		public string $name,
		public string $alphatexContent,
		public string $sourceType,
		public ?int $originalFileId,
		public ?int $tempo,
		public ?string $tuning,
		public ?int $trackCount,
		public string $createdAt,
		public string $updatedAt,
	) {
	}

	public static function fromEntity(SongTab $tab): self
	{
		return new self(
			id: $tab->id,
			songId: $tab->song->id,
			name: $tab->name,
			alphatexContent: $tab->alphatexContent,
			sourceType: $tab->sourceType->value,
			originalFileId: $tab->originalFile?->id,
			tempo: $tab->tempo,
			tuning: $tab->tuning,
			trackCount: $tab->trackCount,
			createdAt: $tab->createdAt->format(DATE_ATOM),
			updatedAt: $tab->updatedAt->format(DATE_ATOM),
		);
	}
}
