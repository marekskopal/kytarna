<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Project;
use Kytario\Model\Entity\Status;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Repository\Enum\ArchivedFilterEnum;
use Kytario\Model\Repository\Enum\OrderDirectionEnum;
use Kytario\Model\Repository\Enum\TaskOrderByEnum;
use Kytario\Model\Repository\TaskRepository;
use Kytario\Model\Repository\TaskTagRepository;
use Kytario\Service\Actor\ActorContextInterface;
use Kytario\Validator\TextFieldValidator;
use RuntimeException;

final readonly class TaskProvider implements TaskProviderInterface
{
	public function __construct(
		private TaskRepository $taskRepository,
		private EventProviderInterface $eventProvider,
		private TaskFileProviderInterface $taskFileProvider,
		private TaskWatcherProviderInterface $taskWatcherProvider,
		private TaskTagProviderInterface $taskTagProvider,
		private TaskTagRepository $taskTagRepository,
		private ActorContextInterface $actorContext,
		private TaskPositionManager $positionManager,
	) {
	}

	public function getTask(int $taskId): ?Task
	{
		return $this->taskRepository->findById($taskId);
	}

	/** @return Iterator<Task> */
	public function getTasksByProject(Project $project, bool $includeArchived = true): Iterator
	{
		return $this->taskRepository->findByProject($project->id, $includeArchived);
	}

	/**
	 * @param list<int>|null $statusIds
	 * @param list<int>|null $tagIds
	 * @param list<int>|null $assigneeIds
	 * @return Iterator<Task>
	 */
	public function getTasksInWorkspace(
		Workspace $workspace,
		int $limit,
		int $offset,
		TaskOrderByEnum $orderBy,
		OrderDirectionEnum $direction,
		?string $search,
		?array $statusIds,
		bool $onlyActive,
		?array $tagIds = null,
		?array $assigneeIds = null,
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
		?DateTimeImmutable $dueFrom = null,
		?DateTimeImmutable $dueTo = null,
	): Iterator {
		return $this->taskRepository->findInWorkspace(
			$workspace->id,
			$limit,
			$offset,
			$orderBy,
			$direction,
			$search,
			$statusIds,
			$onlyActive,
			$this->resolveTaskIdsByTags($tagIds),
			$assigneeIds,
			null,
			$archived,
			$dueFrom,
			$dueTo,
		);
	}

	/**
	 * @param list<int>|null $statusIds
	 * @param list<int>|null $tagIds
	 * @param list<int>|null $assigneeIds
	 */
	public function countTasksInWorkspace(
		Workspace $workspace,
		?string $search,
		?array $statusIds,
		bool $onlyActive,
		?array $tagIds = null,
		?array $assigneeIds = null,
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
		?DateTimeImmutable $dueFrom = null,
		?DateTimeImmutable $dueTo = null,
	): int {
		return $this->taskRepository->countInWorkspace(
			$workspace->id,
			$search,
			$statusIds,
			$onlyActive,
			$this->resolveTaskIdsByTags($tagIds),
			$assigneeIds,
			null,
			$archived,
			$dueFrom,
			$dueTo,
		);
	}

	/** Guards the start ≤ due invariant; throws so the controller answers 422 and MCP surfaces a tool error. */
	private static function assertDateOrder(?DateTimeImmutable $startDate, ?DateTimeImmutable $dueDate): void
	{
		if ($startDate !== null && $dueDate !== null && $startDate > $dueDate) {
			throw new RuntimeException('Start date must not be after due date.');
		}
	}

	/**
	 * @param list<int>|null $tagIds
	 * @return list<int>|null null = no tag filter; [] = no matches
	 */
	private function resolveTaskIdsByTags(?array $tagIds): ?array
	{
		if ($tagIds === null || $tagIds === []) {
			return null;
		}
		return $this->taskTagRepository->findTaskIdsByTagIds($tagIds);
	}

	/** @param list<int>|null $tagIds */
	public function createTask(
		User $author,
		Project $project,
		Status $status,
		string $name,
		?string $description,
		?DateTimeImmutable $dueDate,
		?User $assignee = null,
		?array $tagIds = null,
		?DateTimeImmutable $startDate = null,
	): Task {
		$name = TextFieldValidator::validateName($name, 'Task');
		$description = TextFieldValidator::validateDescription($description);
		self::assertDateOrder($startDate, $dueDate);

		$position = $this->nextPosition($status);
		$sequenceNumber = $this->taskRepository->nextSequenceNumber($project->id);

		$now = new DateTimeImmutable();
		$task = new Task(
			project: $project,
			status: $status,
			assignee: $assignee,
			name: $name,
			description: $description,
			dueDate: $dueDate,
			position: $position,
			sequenceNumber: $sequenceNumber,
			startDate: $startDate,
			createdByAgent: $this->actorContext->isAgent(),
		);
		$task->createdAt = $now;
		$task->updatedAt = $now;

		$this->taskRepository->persist($task);

		if ($tagIds !== null) {
			$tagChanges = $this->taskTagProvider->setTagsForTask($project->workspace, $task, $tagIds);
			if ($tagChanges['added'] !== [] || $tagChanges['removed'] !== []) {
				$this->eventProvider->recordEvent(
					$author,
					$project,
					EventTypeEnum::TaskTagsUpdated,
					['taskName' => $task->name, 'added' => $tagChanges['added'], 'removed' => $tagChanges['removed']],
					$task->id,
				);
			}
		}

		$this->eventProvider->recordEvent(
			$author,
			$project,
			EventTypeEnum::TaskCreated,
			['name' => $name, 'statusId' => $status->id, 'statusName' => $status->name],
			$task->id,
		);

		if ($assignee !== null) {
			$this->recordAssignedEvent($author, $task, $assignee);
		}

		return $task;
	}

	/** @param list<int>|null $tagIds */
	public function updateTask(
		User $author,
		Task $task,
		string $name,
		?string $description,
		?DateTimeImmutable $dueDate,
		Status $status,
		?User $assignee,
		?array $tagIds = null,
		bool $recordEvent = true,
		?DateTimeImmutable $startDate = null,
	): Task {
		$name = TextFieldValidator::validateName($name, 'Task');
		$description = TextFieldValidator::validateDescription($description);
		self::assertDateOrder($startDate, $dueDate);

		$oldName = $task->name;
		$oldAssigneeId = $task->assignee?->id;
		$statusChanged = $task->status->id !== $status->id;

		$task->name = $name;
		$task->description = $description;
		$task->dueDate = $dueDate;
		$task->startDate = $startDate;
		$task->assignee = $assignee;
		if ($statusChanged) {
			$task->status = $status;
			$task->position = $this->positionManager->nextPosition($status);
		}
		$task->updatedAt = new DateTimeImmutable();
		$this->taskRepository->persist($task);

		$tagChanges = $tagIds !== null
			? $this->taskTagProvider->setTagsForTask($task->project->workspace, $task, $tagIds)
			: ['added' => [], 'removed' => []];

		if ($recordEvent) {
			$this->recordUpdateEvents($author, $task, $name, $oldName, $tagChanges);

			if ($assignee !== null && $assignee->id !== $oldAssigneeId) {
				$this->recordAssignedEvent($author, $task, $assignee);
			}
		}

		return $task;
	}

	/** Records a dedicated TaskAssigned event so the notification fan-out (U-83) can ping the assignee. */
	private function recordAssignedEvent(User $author, Task $task, User $assignee): void
	{
		$this->eventProvider->recordEvent(
			$author,
			$task->project,
			EventTypeEnum::TaskAssigned,
			['assigneeId' => $assignee->id, 'assigneeName' => $assignee->name, 'taskName' => $task->name],
			$task->id,
		);
	}

	public function moveTask(User $author, Task $task, Status $newStatus, int $newPosition, bool $recordEvent = true): Task
	{
		$fromStatus = $task->status;
		$fromPosition = $task->position;
		$sameColumn = $fromStatus->id === $newStatus->id;

		if ($sameColumn) {
			$this->positionManager->reorderWithinColumn($task, $newPosition);
		} else {
			$this->positionManager->closeGapInOldColumn($task);
			$this->positionManager->openSlotInNewColumn($newStatus, $newPosition);
			$task->status = $newStatus;
			$task->position = $newPosition;
		}
		$task->updatedAt = new DateTimeImmutable();
		$this->taskRepository->persist($task);

		if ($recordEvent) {
			$this->recordMoveEvent($author, $task, $fromStatus, $newStatus, $fromPosition, $newPosition);
		}

		return $task;
	}

	/** @param array{added: list<int>, removed: list<int>} $tagChanges */
	private function recordUpdateEvents(User $author, Task $task, string $name, string $oldName, array $tagChanges): void
	{
		$this->eventProvider->recordEvent(
			$author,
			$task->project,
			EventTypeEnum::TaskUpdated,
			['name' => $name, 'oldName' => $oldName],
			$task->id,
		);

		if ($tagChanges['added'] === [] && $tagChanges['removed'] === []) {
			return;
		}
		$this->eventProvider->recordEvent(
			$author,
			$task->project,
			EventTypeEnum::TaskTagsUpdated,
			['taskName' => $task->name, 'added' => $tagChanges['added'], 'removed' => $tagChanges['removed']],
			$task->id,
		);
	}

	private function recordMoveEvent(
		User $author,
		Task $task,
		Status $fromStatus,
		Status $newStatus,
		int $fromPosition,
		int $newPosition,
	): void
	{
		$this->eventProvider->recordEvent(
			$author,
			$task->project,
			EventTypeEnum::TaskMoved,
			[
				'fromStatusId' => $fromStatus->id,
				'fromStatusName' => $fromStatus->name,
				'toStatusId' => $newStatus->id,
				'toStatusName' => $newStatus->name,
				'fromPosition' => $fromPosition,
				'toPosition' => $newPosition,
				'taskName' => $task->name,
			],
			$task->id,
		);
	}

	public function archiveTask(User $author, Task $task): Task
	{
		if ($task->archivedAt !== null) {
			return $task;
		}

		$task->archivedAt = new DateTimeImmutable();
		$task->updatedAt = new DateTimeImmutable();
		$this->taskRepository->persist($task);

		$this->eventProvider->recordEvent(
			$author,
			$task->project,
			EventTypeEnum::TaskArchived,
			['name' => $task->name, 'statusId' => $task->status->id, 'statusName' => $task->status->name],
			$task->id,
		);

		return $task;
	}

	public function unarchiveTask(User $author, Task $task): Task
	{
		if ($task->archivedAt === null) {
			return $task;
		}

		$task->archivedAt = null;
		$task->updatedAt = new DateTimeImmutable();
		$this->taskRepository->persist($task);

		$this->eventProvider->recordEvent(
			$author,
			$task->project,
			EventTypeEnum::TaskUnarchived,
			['name' => $task->name, 'statusId' => $task->status->id, 'statusName' => $task->status->name],
			$task->id,
		);

		return $task;
	}

	public function unassignTasksForUserInWorkspace(User $user, Workspace $workspace): void
	{
		$now = new DateTimeImmutable();
		foreach ($this->taskRepository->findByAssigneeInWorkspace($user->id, $workspace->id) as $task) {
			$task->assignee = null;
			$task->updatedAt = $now;
			$this->taskRepository->persist($task);
		}
	}

	public function deleteTask(User $author, Task $task, bool $recordEvent = true): void
	{
		if ($recordEvent) {
			$this->eventProvider->recordEvent(
				$author,
				$task->project,
				EventTypeEnum::TaskDeleted,
				['name' => $task->name],
				$task->id,
			);
		}

		$this->taskFileProvider->deleteAllForTask($author, $task);
		$this->taskWatcherProvider->deleteAllForTask($task);
		$this->taskTagProvider->deleteAllForTask($task);
		$this->taskRepository->delete($task);
	}

	public function nextPosition(Status $status): int
	{
		return $this->positionManager->nextPosition($status);
	}
}
