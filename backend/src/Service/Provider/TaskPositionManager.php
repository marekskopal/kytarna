<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Ukolio\Model\Entity\Status;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Repository\TaskRepository;

/**
 * Encapsulates the per-status position bookkeeping for tasks.
 * Extracted from TaskProvider to keep that class focused on lifecycle / events.
 */
final readonly class TaskPositionManager
{
	public function __construct(private TaskRepository $taskRepository)
	{
	}

	public function nextPosition(Status $status): int
	{
		$tasks = iterator_to_array($this->taskRepository->findByStatus($status->id), false);
		if ($tasks === []) {
			return 0;
		}
		$max = 0;
		foreach ($tasks as $t) {
			if ($t->position > $max) {
				$max = $t->position;
			}
		}
		return $max + 1;
	}

	public function reorderWithinColumn(Task $task, int $newPosition): void
	{
		$oldPosition = $task->position;
		if ($oldPosition === $newPosition) {
			return;
		}
		$now = new DateTimeImmutable();
		foreach ($this->taskRepository->findByStatus($task->status->id) as $sibling) {
			if ($sibling->id === $task->id) {
				continue;
			}
			$shifted = $this->shiftSiblingForReorder($sibling, $oldPosition, $newPosition);
			if (!$shifted) {
				continue;
			}

			$sibling->updatedAt = $now;
			$this->taskRepository->persist($sibling);
		}
		$task->position = $newPosition;
	}

	public function closeGapInOldColumn(Task $task): void
	{
		$now = new DateTimeImmutable();
		foreach ($this->taskRepository->findByStatus($task->status->id) as $sibling) {
			if ($sibling->id === $task->id || $sibling->position <= $task->position) {
				continue;
			}
			$sibling->position--;
			$sibling->updatedAt = $now;
			$this->taskRepository->persist($sibling);
		}
	}

	public function openSlotInNewColumn(Status $newStatus, int $newPosition): void
	{
		$now = new DateTimeImmutable();
		foreach ($this->taskRepository->findByStatus($newStatus->id) as $sibling) {
			if ($sibling->position < $newPosition) {
				continue;
			}
			$sibling->position++;
			$sibling->updatedAt = $now;
			$this->taskRepository->persist($sibling);
		}
	}

	private function shiftSiblingForReorder(Task $sibling, int $oldPosition, int $newPosition): bool
	{
		if ($oldPosition < $newPosition) {
			if ($sibling->position > $oldPosition && $sibling->position <= $newPosition) {
				$sibling->position--;
				return true;
			}
			return false;
		}
		if ($sibling->position >= $newPosition && $sibling->position < $oldPosition) {
			$sibling->position++;
			return true;
		}
		return false;
	}
}
