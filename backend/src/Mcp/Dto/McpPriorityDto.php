<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

use Ukolio\Model\Entity\Priority;

final readonly class McpPriorityDto
{
	public function __construct(
		public int $id,
		public int $workspaceId,
		public string $name,
		public string $color,
		public int $position,
		public bool $isDefault,
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
		);
	}
}
