<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

use Kytario\Model\Entity\Task;

final readonly class McpTaskDto
{
	/**
	 * @param list<McpTaskFieldValueDto> $fieldValues
	 * @param list<int> $tagIds
	 */
	public function __construct(
		public int $id,
		public string $code,
		public int $projectId,
		public int $statusId,
		public string $statusName,
		public ?int $assigneeId,
		public string $name,
		public ?string $description,
		public int $priorityId,
		public string $priorityName,
		public ?string $dueDate,
		public ?string $startDate,
		public int $position,
		public int $sequenceNumber,
		public bool $archived,
		public ?string $archivedAt,
		public array $fieldValues,
		public array $tagIds,
	) {
	}

	/**
	 * @param array<int, ?string> $fieldValues
	 * @param list<int> $tagIds
	 */
	public static function fromEntity(Task $task, array $fieldValues = [], array $tagIds = []): self
	{
		$values = [];
		foreach ($fieldValues as $fieldId => $value) {
			$values[] = new McpTaskFieldValueDto(fieldId: $fieldId, value: $value);
		}

		return new self(
			id: $task->id,
			code: $task->project->prefix . '-' . $task->sequenceNumber,
			projectId: $task->project->id,
			statusId: $task->status->id,
			statusName: $task->status->name,
			assigneeId: $task->assignee?->id,
			name: $task->name,
			description: $task->description,
			priorityId: $task->priority->id,
			priorityName: $task->priority->name,
			dueDate: $task->dueDate?->format('Y-m-d'),
			startDate: $task->startDate?->format('Y-m-d'),
			position: $task->position,
			sequenceNumber: $task->sequenceNumber,
			archived: $task->archivedAt !== null,
			archivedAt: $task->archivedAt?->format('Y-m-d H:i:s'),
			fieldValues: $values,
			tagIds: $tagIds,
		);
	}
}
