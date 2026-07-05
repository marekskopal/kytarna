<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

use Ukolio\Model\Entity\TaskRelation;
use const DATE_ATOM;

final readonly class McpTaskRelationDto
{
	public function __construct(
		public int $id,
		public string $type,
		public int $sourceTaskId,
		public string $sourceTaskCode,
		public string $sourceTaskName,
		public int $targetTaskId,
		public string $targetTaskCode,
		public string $targetTaskName,
		public string $createdAt,
	) {
	}

	public static function fromEntity(TaskRelation $relation): self
	{
		return new self(
			id: $relation->id,
			type: $relation->type->value,
			sourceTaskId: $relation->sourceTask->id,
			sourceTaskCode: $relation->sourceTask->project->prefix . '-' . $relation->sourceTask->sequenceNumber,
			sourceTaskName: $relation->sourceTask->name,
			targetTaskId: $relation->targetTask->id,
			targetTaskCode: $relation->targetTask->project->prefix . '-' . $relation->targetTask->sequenceNumber,
			targetTaskName: $relation->targetTask->name,
			createdAt: $relation->createdAt->format(DATE_ATOM),
		);
	}
}
