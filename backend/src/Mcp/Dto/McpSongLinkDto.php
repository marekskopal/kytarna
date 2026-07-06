<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

use Kytarna\Model\Entity\SongLink;

final readonly class McpSongLinkDto
{
	public function __construct(
		public int $id,
		public int $songId,
		public string $url,
		public ?string $label,
		public string $kind,
		public ?int $timestampSeconds,
	) {
	}

	public static function fromEntity(SongLink $link): self
	{
		return new self(
			id: $link->id,
			songId: $link->song->id,
			url: $link->url,
			label: $link->label,
			kind: $link->kind->value,
			timestampSeconds: $link->timestampSeconds,
		);
	}
}
