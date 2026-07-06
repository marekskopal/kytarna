<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\Tag;
use const DATE_ATOM;

final readonly class TagDto
{
	public function __construct(
		public int $id,
		public int $workspaceId,
		public string $name,
		public string $color,
		public string $createdAt,
		public string $updatedAt,
	) {
	}

	public static function fromEntity(Tag $tag): self
	{
		return new self(
			id: $tag->id,
			workspaceId: $tag->workspace->id,
			name: $tag->name,
			color: $tag->color,
			createdAt: $tag->createdAt->format(DATE_ATOM),
			updatedAt: $tag->updatedAt->format(DATE_ATOM),
		);
	}
}
