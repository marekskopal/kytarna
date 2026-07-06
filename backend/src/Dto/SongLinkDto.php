<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\SongLink;
use const DATE_ATOM;

final readonly class SongLinkDto
{
	public function __construct(
		public int $id,
		public int $songId,
		public string $url,
		public ?string $label,
		public string $kind,
		public ?int $timestampSeconds,
		public string $createdAt,
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
			createdAt: $link->createdAt->format(DATE_ATOM),
		);
	}
}
