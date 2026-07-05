<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

use Ukolio\Model\Entity\Status;

final readonly class McpStatusDto
{
	public function __construct(public int $id, public string $name, public string $type, public int $position, public string $color,)
	{
	}

	public static function fromEntity(Status $status): self
	{
		return new self(
			id: $status->id,
			name: $status->name,
			type: $status->type->value,
			position: $status->position,
			color: $status->color,
		);
	}
}
