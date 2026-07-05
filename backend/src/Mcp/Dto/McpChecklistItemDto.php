<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

use Ukolio\Model\Entity\TaskChecklistItem;

final readonly class McpChecklistItemDto
{
	public function __construct(
		public int $id,
		public int $taskId,
		public string $text,
		public int $position,
		public bool $checked,
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
			dueDate: $item->dueDate?->format('Y-m-d'),
			assigneeId: $item->assignee?->id,
			assigneeName: $item->assignee?->name,
		);
	}
}
