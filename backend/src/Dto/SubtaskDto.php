<?php

declare(strict_types=1);

namespace Kytario\Dto;

use Kytario\Model\Entity\TaskRelation;

/**
 * A child task as seen from its parent. `startStatusId`/`finishStatusId` belong to the child's
 * own project workflow so the UI can toggle done/undone with a plain move call.
 */
final readonly class SubtaskDto
{
	public function __construct(
		public int $taskId,
		public int $relationId,
		public string $code,
		public string $name,
		public int $projectId,
		public int $statusId,
		public string $statusName,
		public string $statusColor,
		public string $statusType,
		public int $priorityId,
		public string $priorityName,
		public int $priorityPosition,
		public ?string $dueDate,
		public ?string $startDate,
		public ?int $assigneeId,
		public ?int $startStatusId,
		public ?int $finishStatusId,
	) {
	}

	public static function fromRelation(TaskRelation $relation, ?int $startStatusId, ?int $finishStatusId): self
	{
		$child = $relation->targetTask;

		return new self(
			taskId: $child->id,
			relationId: $relation->id,
			code: $child->project->prefix . '-' . $child->sequenceNumber,
			name: $child->name,
			projectId: $child->project->id,
			statusId: $child->status->id,
			statusName: $child->status->name,
			statusColor: $child->status->color,
			statusType: $child->status->type->value,
			priorityId: $child->priority->id,
			priorityName: $child->priority->name,
			priorityPosition: $child->priority->position,
			dueDate: $child->dueDate?->format('Y-m-d'),
			startDate: $child->startDate?->format('Y-m-d'),
			assigneeId: $child->assignee?->id,
			startStatusId: $startStatusId,
			finishStatusId: $finishStatusId,
		);
	}
}
