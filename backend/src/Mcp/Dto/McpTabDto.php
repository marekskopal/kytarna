<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

use Kytarna\Model\Entity\Tab;

final readonly class McpTabDto
{
	public function __construct(
		public int $id,
		public int $lectureId,
		public string $name,
		public string $alphaTex,
		public string $sourceType,
		public ?int $originalFileId,
		public ?int $tempo,
		public ?string $tuning,
		public ?int $trackCount,
	) {
	}

	public static function fromEntity(Tab $tab): self
	{
		return new self(
			id: $tab->id,
			lectureId: $tab->lecture->id,
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
