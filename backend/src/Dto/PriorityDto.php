<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\Priority;
use const DATE_ATOM;

final readonly class PriorityDto
{
	public function __construct(
		public int $id,
		public int $workspaceId,
		public string $name,
		public string $color,
		public int $position,
		public bool $isDefault,
		public string $createdAt,
		public string $updatedAt,
	) {
	}

	public static function fromEntity(Priority $priority): self
	{
		return new self(
			id: $priority->id,
			workspaceId: $priority->workspace->id,
			name: $priority->name,
			color: $priority->color,
			position: $priority->position,
			isDefault: $priority->isDefault,
			createdAt: $priority->createdAt->format(DATE_ATOM),
			updatedAt: $priority->updatedAt->format(DATE_ATOM),
		);
	}
}
