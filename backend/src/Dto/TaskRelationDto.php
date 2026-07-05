<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\Enum\TaskRelationTypeEnum;
use Ukolio\Model\Entity\TaskRelation;
use const DATE_ATOM;

final readonly class TaskRelationDto
{
	public function __construct(
		public int $id,
		public string $type,
		public string $direction,
		public string $labelKey,
		public int $otherTaskId,
		public string $otherTaskCode,
		public string $otherTaskName,
		public int $otherTaskProjectId,
		public string $otherTaskProjectName,
		public int $otherTaskStatusId,
		public string $otherTaskStatusName,
		public string $otherTaskStatusColor,
		public string $createdAt,
		public ?int $createdByUserId,
		public ?string $createdByUserName,
	) {
	}

	public static function fromEntity(TaskRelation $relation, bool $forSourceSide): self
	{
		$other = $forSourceSide ? $relation->targetTask : $relation->sourceTask;
		$direction = $forSourceSide ? 'outgoing' : 'incoming';

		return new self(
			id: $relation->id,
			type: $relation->type->value,
			direction: $direction,
			labelKey: self::resolveLabelKey($relation->type, $forSourceSide),
			otherTaskId: $other->id,
			otherTaskCode: $other->project->prefix . '-' . $other->sequenceNumber,
			otherTaskName: $other->name,
			otherTaskProjectId: $other->project->id,
			otherTaskProjectName: $other->project->name,
			otherTaskStatusId: $other->status->id,
			otherTaskStatusName: $other->status->name,
			otherTaskStatusColor: $other->status->color,
			createdAt: $relation->createdAt->format(DATE_ATOM),
			createdByUserId: $relation->createdBy?->id,
			createdByUserName: $relation->createdBy?->name,
		);
	}

	private static function resolveLabelKey(TaskRelationTypeEnum $type, bool $forSourceSide): string
	{
		if ($type === TaskRelationTypeEnum::Related) {
			return 'app.taskRelations.label.Related';
		}

		if ($forSourceSide) {
			return match ($type) {
				TaskRelationTypeEnum::Duplicates => 'app.taskRelations.label.DuplicateOf',
				TaskRelationTypeEnum::Parent => 'app.taskRelations.label.Subtask',
				TaskRelationTypeEnum::DependsOn => 'app.taskRelations.label.DependsOn',
			};
		}

		return match ($type) {
			TaskRelationTypeEnum::Duplicates => 'app.taskRelations.label.DuplicatedBy',
			TaskRelationTypeEnum::Parent => 'app.taskRelations.label.Parent',
			TaskRelationTypeEnum::DependsOn => 'app.taskRelations.label.RequiredFor',
		};
	}
}
