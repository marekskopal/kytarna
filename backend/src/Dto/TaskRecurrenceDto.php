<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\TaskRecurrence;

final readonly class TaskRecurrenceDto
{
	public function __construct(
		public int $id,
		public int $taskId,
		public string $cadence,
		public int $interval,
		public ?int $weekday,
		public ?int $dayOfMonth,
		public ?string $cronExpression,
		public string $anchorDate,
		public string $endType,
		public ?string $endDate,
		public ?int $maxOccurrences,
		public int $occurrenceCount,
		public ?string $nextRunAt,
		public ?string $lastSpawnedAt,
		public bool $active,
	) {
	}

	public static function fromEntity(TaskRecurrence $recurrence): self
	{
		return new self(
			id: $recurrence->id,
			taskId: $recurrence->task->id,
			cadence: $recurrence->cadence->value,
			interval: $recurrence->interval,
			weekday: $recurrence->weekday,
			dayOfMonth: $recurrence->dayOfMonth,
			cronExpression: $recurrence->cronExpression,
			anchorDate: $recurrence->anchorDate->format('Y-m-d'),
			endType: $recurrence->endType->value,
			endDate: $recurrence->endDate?->format('Y-m-d'),
			maxOccurrences: $recurrence->maxOccurrences,
			occurrenceCount: $recurrence->occurrenceCount,
			nextRunAt: $recurrence->nextRunAt?->format('Y-m-d H:i:s'),
			lastSpawnedAt: $recurrence->lastSpawnedAt?->format('Y-m-d H:i:s'),
			active: $recurrence->active,
		);
	}
}
