<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

use Kytario\Model\Entity\Task;

final readonly class McpTaskDto
{
	/** @param list<int> $tagIds */
	public function __construct(
		public int $id,
		public string $code,
		public int $projectId,
		public int $statusId,
		public string $statusName,
		public ?int $assigneeId,
		public string $name,
		public ?string $description,
		public ?string $dueDate,
		public ?string $startDate,
		public int $position,
		public int $sequenceNumber,
		public bool $archived,
		public ?string $archivedAt,
		public array $tagIds,
	) {
	}

	/** @param list<int> $tagIds */
	public static function fromEntity(Task $task, array $tagIds = []): self
	{
		return new self(
			id: $task->id,
			code: $task->project->prefix . '-' . $task->sequenceNumber,
			projectId: $task->project->id,
			statusId: $task->status->id,
			statusName: $task->status->name,
			assigneeId: $task->assignee?->id,
			name: $task->name,
			description: $task->description,
			dueDate: $task->dueDate?->format('Y-m-d'),
			startDate: $task->startDate?->format('Y-m-d'),
			position: $task->position,
			sequenceNumber: $task->sequenceNumber,
			archived: $task->archivedAt !== null,
			archivedAt: $task->archivedAt?->format('Y-m-d H:i:s'),
			tagIds: $tagIds,
		);
	}
}
