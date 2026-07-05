<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use DateTimeImmutable;
use Iterator;
use RuntimeException;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Priority;
use Kytario\Model\Entity\Project;
use Kytario\Model\Entity\Status;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Repository\Enum\ArchivedFilterEnum;
use Kytario\Model\Repository\Enum\OrderDirectionEnum;
use Kytario\Model\Repository\Enum\SubtaskFilterEnum;
use Kytario\Model\Repository\Enum\TaskOrderByEnum;
use Kytario\Model\Repository\TaskRecurrenceRepository;
use Kytario\Model\Repository\TaskRelationRepository;
use Kytario\Model\Repository\TaskRepository;
use Kytario\Model\Repository\TaskTagRepository;
use Kytario\Service\Actor\ActorContextInterface;
use Kytario\Service\Search\SearchIndexer;
use Kytario\Validator\TextFieldValidator;

final readonly class TaskProvider implements TaskProviderInterface
{
	public function __construct(
		private TaskRepository $taskRepository,
		private EventProviderInterface $eventProvider,
		private TaskFieldValueProviderInterface $taskFieldValueProvider,
		private TaskFileProviderInterface $taskFileProvider,
		private TaskRelationProviderInterface $taskRelationProvider,
		private TaskChecklistProviderInterface $taskChecklistProvider,
		private TaskWatcherProviderInterface $taskWatcherProvider,
		private TaskRecurrenceRepository $taskRecurrenceRepository,
		private TaskTagProviderInterface $taskTagProvider,
		private TaskTagRepository $taskTagRepository,
		private TaskRelationRepository $taskRelationRepository,
		private ActorContextInterface $actorContext,
		private TaskPositionManager $positionManager,
		private SearchIndexer $searchIndexer,
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
		SubtaskFilterEnum $subtaskFilter = SubtaskFilterEnum::All,
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
		?DateTimeImmutable $dueFrom = null,
		?DateTimeImmutable $dueTo = null,
	): Iterator {
		[$includeIds, $excludeIds] = $this->resolveSubtaskFilter($workspace, $subtaskFilter, $this->resolveTaskIdsByTags($tagIds));

		return $this->taskRepository->findInWorkspace(
			$workspace->id,
			$limit,
			$offset,
			$orderBy,
			$direction,
			$search,
			$statusIds,
			$onlyActive,
			$includeIds,
			$assigneeIds,
			$excludeIds,
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
		SubtaskFilterEnum $subtaskFilter = SubtaskFilterEnum::All,
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
		?DateTimeImmutable $dueFrom = null,
		?DateTimeImmutable $dueTo = null,
	): int {
		[$includeIds, $excludeIds] = $this->resolveSubtaskFilter($workspace, $subtaskFilter, $this->resolveTaskIdsByTags($tagIds));

		return $this->taskRepository->countInWorkspace(
			$workspace->id,
			$search,
			$statusIds,
			$onlyActive,
			$includeIds,
			$assigneeIds,
			$excludeIds,
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

	/**
	 * Combines the tag-based include list with the subtask hierarchy filter.
	 *
	 * @param list<int>|null $tagTaskIds
	 * @return array{0: list<int>|null, 1: list<int>|null} [includeIds, excludeIds]
	 */
	private function resolveSubtaskFilter(Workspace $workspace, SubtaskFilterEnum $filter, ?array $tagTaskIds): array
	{
		if ($filter === SubtaskFilterEnum::All) {
			return [$tagTaskIds, null];
		}

		$hierarchy = $this->taskRelationRepository->findParentChildIdsInWorkspace($workspace->id);

		if ($filter === SubtaskFilterEnum::HideSubtasks) {
			return [$tagTaskIds, $hierarchy['children']];
		}

		$parentIds = $hierarchy['parents'];
		$includeIds = $tagTaskIds === null ? $parentIds : array_values(array_intersect($tagTaskIds, $parentIds));

		return [$includeIds, null];
	}

	/**
	 * @param array<int, ?string>|null $fieldValues
	 * @param list<int>|null $tagIds
	 */
	public function createTask(
		User $author,
		Project $project,
		Status $status,
		string $name,
		?string $description,
		Priority $priority,
		?DateTimeImmutable $dueDate,
		?User $assignee = null,
		?array $fieldValues = null,
		?array $tagIds = null,
		?DateTimeImmutable $startDate = null,
	): Task {
		$name = TextFieldValidator::validateName($name, 'Task');
		$description = TextFieldValidator::validateDescription($description);
		self::assertDateOrder($startDate, $dueDate);

		if ($fieldValues !== null) {
			$this->taskFieldValueProvider->validateForProject($project, $fieldValues);
		}

		$position = $this->nextPosition($status);
		$sequenceNumber = $this->taskRepository->nextSequenceNumber($project->id);

		$now = new DateTimeImmutable();
		$task = new Task(
			project: $project,
			status: $status,
			assignee: $assignee,
			name: $name,
			description: $description,
			priority: $priority,
			dueDate: $dueDate,
			position: $position,
			sequenceNumber: $sequenceNumber,
			startDate: $startDate,
			createdByAgent: $this->actorContext->isAgent(),
		);
		$task->createdAt = $now;
		$task->updatedAt = $now;

		$this->taskRepository->persist($task);

		if ($fieldValues !== null) {
			$this->taskFieldValueProvider->persistForTask($task, $fieldValues);
		}

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

		$this->searchIndexer->queueUpsert($task->id);

		return $task;
	}

	public function duplicateTask(User $author, Task $task, ?string $name = null): Task
	{
		return $this->createTask(
			author: $author,
			project: $task->project,
			status: $task->status,
			name: $name ?? mb_substr($task->name, 0, TextFieldValidator::MaxNameLength - 7) . ' (copy)',
			description: $task->description,
			priority: $task->priority,
			dueDate: $task->dueDate,
			assignee: $task->assignee,
			fieldValues: $this->taskFieldValueProvider->findByTask($task),
			tagIds: $this->taskTagProvider->getTagIdsForTask($task),
			startDate: $task->startDate,
		);
	}

	/**
	 * @param array<int, ?string>|null $fieldValues
	 * @param list<int>|null $tagIds
	 */
	public function updateTask(
		User $author,
		Task $task,
		string $name,
		?string $description,
		Priority $priority,
		?DateTimeImmutable $dueDate,
		Status $status,
		?User $assignee,
		?array $fieldValues = null,
		?array $tagIds = null,
		bool $recordEvent = true,
		?DateTimeImmutable $startDate = null,
	): Task {
		$name = TextFieldValidator::validateName($name, 'Task');
		$description = TextFieldValidator::validateDescription($description);
		self::assertDateOrder($startDate, $dueDate);

		if ($fieldValues !== null) {
			$this->taskFieldValueProvider->validateForProject($task->project, $fieldValues);
		}

		$oldName = $task->name;
		$oldAssigneeId = $task->assignee?->id;
		$statusChanged = $task->status->id !== $status->id;

		$task->name = $name;
		$task->description = $description;
		$task->priority = $priority;
		$task->dueDate = $dueDate;
		$task->startDate = $startDate;
		$task->assignee = $assignee;
		if ($statusChanged) {
			$task->status = $status;
			$task->position = $this->positionManager->nextPosition($status);
		}
		$task->updatedAt = new DateTimeImmutable();
		$this->taskRepository->persist($task);

		$fieldChanges = $fieldValues !== null
			? $this->taskFieldValueProvider->persistForTask($task, $fieldValues)
			: [];

		$tagChanges = $tagIds !== null
			? $this->taskTagProvider->setTagsForTask($task->project->workspace, $task, $tagIds)
			: ['added' => [], 'removed' => []];

		if ($recordEvent) {
			$this->recordUpdateEvents($author, $task, $name, $oldName, $fieldChanges, $tagChanges);

			if ($assignee !== null && $assignee->id !== $oldAssigneeId) {
				$this->recordAssignedEvent($author, $task, $assignee);
			}
		}

		$this->searchIndexer->queueUpsert($task->id);

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

		$this->searchIndexer->queueUpsert($task->id);

		return $task;
	}

	/**
	 * @param list<array{fieldId: int, from: ?string, to: ?string}> $fieldChanges
	 * @param array{added: list<int>, removed: list<int>} $tagChanges
	 */
	private function recordUpdateEvents(
		User $author,
		Task $task,
		string $name,
		string $oldName,
		array $fieldChanges,
		array $tagChanges,
	): void
	{
		$metadata = ['name' => $name, 'oldName' => $oldName];
		if ($fieldChanges !== []) {
			$metadata['fieldChanges'] = $fieldChanges;
		}
		$this->eventProvider->recordEvent($author, $task->project, EventTypeEnum::TaskUpdated, $metadata, $task->id);

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

		$this->searchIndexer->queueUpsert($task->id);

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

		$this->searchIndexer->queueUpsert($task->id);

		return $task;
	}

	public function unassignTasksForUserInWorkspace(User $user, Workspace $workspace): void
	{
		$now = new DateTimeImmutable();
		foreach ($this->taskRepository->findByAssigneeInWorkspace($user->id, $workspace->id) as $task) {
			$task->assignee = null;
			$task->updatedAt = $now;
			$this->taskRepository->persist($task);
			$this->searchIndexer->queueUpsert($task->id);
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

		$taskId = $task->id;
		$this->taskFieldValueProvider->deleteAllForTask($task);
		$this->taskFileProvider->deleteAllForTask($author, $task);
		$this->taskRelationProvider->deleteAllForTask($task);
		$this->taskChecklistProvider->deleteAllForTask($task);
		$this->taskWatcherProvider->deleteAllForTask($task);
		// Delete the recurrence row directly (not via its provider) to avoid a provider cycle:
		// TaskRecurrenceProvider depends on TaskProvider for spawning occurrences.
		$recurrence = $this->taskRecurrenceRepository->findByTask($taskId);
		if ($recurrence !== null) {
			$this->taskRecurrenceRepository->delete($recurrence);
		}
		$this->taskTagProvider->deleteAllForTask($task);
		$this->taskRepository->delete($task);

		$this->searchIndexer->queueDelete($taskId);
	}

	public function nextPosition(Status $status): int
	{
		return $this->positionManager->nextPosition($status);
	}
}
