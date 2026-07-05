<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use RuntimeException;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskChecklistItem;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\TaskChecklistItemRepository;

final readonly class TaskChecklistProvider implements TaskChecklistProviderInterface
{
	private const int MaxTextLength = 500;

	public function __construct(private TaskChecklistItemRepository $taskChecklistItemRepository)
	{
	}

	/** @return list<TaskChecklistItem> */
	public function findByTask(Task $task): array
	{
		$result = [];
		foreach ($this->taskChecklistItemRepository->findByTask($task->id) as $item) {
			$result[] = $item;
		}
		return $result;
	}

	public function getItem(int $itemId): ?TaskChecklistItem
	{
		return $this->taskChecklistItemRepository->findOneById($itemId);
	}

	public function createItem(Task $task, string $text, ?DateTimeImmutable $dueDate = null, ?User $assignee = null): TaskChecklistItem
	{
		$normalized = $this->normalizeText($text);

		$now = new DateTimeImmutable();
		$item = new TaskChecklistItem(
			task: $task,
			text: $normalized,
			position: $this->nextPosition($task),
			dueDate: $dueDate,
			assignee: $assignee,
		);
		$item->createdAt = $now;
		$item->updatedAt = $now;

		$this->taskChecklistItemRepository->persist($item);

		return $item;
	}

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
	): TaskChecklistItem {
		if ($text !== null) {
			$item->text = $this->normalizeText($text);
		}
		if ($dueDateProvided) {
			$item->dueDate = $dueDate;
		}
		if ($assigneeProvided) {
			$item->assignee = $assignee;
		}
		if ($checkedProvided) {
			$this->applyChecked($item, $actor, $checked);
		}

		$item->updatedAt = new DateTimeImmutable();
		$this->taskChecklistItemRepository->persist($item);

		return $item;
	}

	public function setChecked(TaskChecklistItem $item, User $actor, bool $checked): TaskChecklistItem
	{
		$this->applyChecked($item, $actor, $checked);
		$item->updatedAt = new DateTimeImmutable();
		$this->taskChecklistItemRepository->persist($item);

		return $item;
	}

	public function moveItem(TaskChecklistItem $item, int $newPosition): TaskChecklistItem
	{
		$siblings = $this->findByTask($item->task);
		$ordered = array_values(array_filter($siblings, static fn (TaskChecklistItem $i): bool => $i->id !== $item->id));

		$target = max(0, min($newPosition, count($ordered)));
		array_splice($ordered, $target, 0, [$item]);

		$now = new DateTimeImmutable();
		foreach ($ordered as $position => $sibling) {
			if ($sibling->position !== $position) {
				$sibling->position = $position;
				$sibling->updatedAt = $now;
				$this->taskChecklistItemRepository->persist($sibling);
			}
		}

		return $item;
	}

	public function deleteItem(TaskChecklistItem $item): void
	{
		$this->taskChecklistItemRepository->delete($item);
	}

	public function deleteAllForTask(Task $task): void
	{
		foreach ($this->taskChecklistItemRepository->findByTask($task->id) as $item) {
			$this->taskChecklistItemRepository->delete($item);
		}
	}

	/**
	 * @param list<int> $taskIds
	 * @return array<int, array{total: int, done: int}>
	 */
	public function getCounts(array $taskIds): array
	{
		$counts = [];
		foreach ($this->taskChecklistItemRepository->findByTasks($taskIds) as $item) {
			$taskId = $item->task->id;
			$counts[$taskId] ??= ['total' => 0, 'done' => 0];
			$counts[$taskId]['total']++;
			if ($item->checkedAt !== null) {
				$counts[$taskId]['done']++;
			}
		}

		return $counts;
	}

	private function applyChecked(TaskChecklistItem $item, User $actor, bool $checked): void
	{
		if ($checked) {
			$item->checkedAt ??= new DateTimeImmutable();
			$item->checkedBy ??= $actor;
		} else {
			$item->checkedAt = null;
			$item->checkedBy = null;
		}
	}

	private function nextPosition(Task $task): int
	{
		$max = -1;
		foreach ($this->taskChecklistItemRepository->findByTask($task->id) as $item) {
			$max = max($max, $item->position);
		}

		return $max + 1;
	}

	private function normalizeText(string $text): string
	{
		$trimmed = trim($text);
		if ($trimmed === '') {
			throw new RuntimeException('Checklist item text cannot be empty.');
		}
		if (mb_strlen($trimmed) > self::MaxTextLength) {
			throw new RuntimeException(sprintf('Checklist item text is too long (max %d characters).', self::MaxTextLength));
		}

		return $trimmed;
	}
}
