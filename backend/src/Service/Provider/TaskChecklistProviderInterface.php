<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskChecklistItem;
use Ukolio\Model\Entity\User;

interface TaskChecklistProviderInterface
{
	/** @return list<TaskChecklistItem> */
	public function findByTask(Task $task): array;

	public function getItem(int $itemId): ?TaskChecklistItem;

	public function createItem(Task $task, string $text, ?DateTimeImmutable $dueDate = null, ?User $assignee = null): TaskChecklistItem;

	/**
	 * Partial update. Pass the `*Provided` flag together with the value to change a field;
	 * leave the flag false (and value null) to keep the current value.
	 */
	public function updateItem(
		TaskChecklistItem $item,
		User $actor,
		?string $text = null,
		bool $dueDateProvided = false,
		?DateTimeImmutable $dueDate = null,
		bool $assigneeProvided = false,
		?User $assignee = null,
		bool $checkedProvided = false,
		bool $checked = false,
	): TaskChecklistItem;

	public function setChecked(TaskChecklistItem $item, User $actor, bool $checked): TaskChecklistItem;

	public function moveItem(TaskChecklistItem $item, int $newPosition): TaskChecklistItem;

	public function deleteItem(TaskChecklistItem $item): void;

	public function deleteAllForTask(Task $task): void;

	/**
	 * @param list<int> $taskIds
	 * @return array<int, array{total: int, done: int}>
	 */
	public function getCounts(array $taskIds): array;
}
