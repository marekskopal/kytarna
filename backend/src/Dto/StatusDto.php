<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\Status;

final readonly class StatusDto
{
	public function __construct(
		public int $id,
		public int $workflowId,
		public string $name,
		public string $color,
		public int $position,
		public string $type,
	) {
	}

	public static function fromEntity(Status $status): self
	{
		return new self(
			id: $status->id,
			workflowId: $status->workflow->id,
			name: $status->name,
			color: $status->color,
			position: $status->position,
			type: $status->type->value,
		);
	}
}
