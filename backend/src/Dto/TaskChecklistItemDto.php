<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\TaskChecklistItem;

final readonly class TaskChecklistItemDto
{
	public function __construct(
		public int $id,
		public int $taskId,
		public string $text,
		public int $position,
		public bool $checked,
		public ?int $checkedById,
		public ?string $checkedByName,
		public ?string $dueDate,
		public ?int $assigneeId,
		public ?string $assigneeName,
	) {
	}

	public static function fromEntity(TaskChecklistItem $item): self
	{
		return new self(
			id: $item->id,
			taskId: $item->task->id,
			text: $item->text,
			position: $item->position,
			checked: $item->checkedAt !== null,
			checkedById: $item->checkedBy?->id,
			checkedByName: $item->checkedBy?->name,
			dueDate: $item->dueDate?->format('Y-m-d'),
			assigneeId: $item->assignee?->id,
			assigneeName: $item->assignee?->name,
		);
	}
}
