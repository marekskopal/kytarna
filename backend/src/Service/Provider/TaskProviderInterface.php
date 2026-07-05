<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use DateTimeImmutable;
use Iterator;
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

interface TaskProviderInterface
{
	public function getTask(int $taskId): ?Task;

	/** @return Iterator<Task> */
	public function getTasksByProject(Project $project, bool $includeArchived = true): Iterator;

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
	): Iterator;

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
	): int;

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
	): Task;

	public function duplicateTask(User $author, Task $task, ?string $name = null): Task;

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
	): Task;

	public function moveTask(User $author, Task $task, Status $newStatus, int $newPosition, bool $recordEvent = true): Task;

	public function archiveTask(User $author, Task $task): Task;

	public function unarchiveTask(User $author, Task $task): Task;

	public function deleteTask(User $author, Task $task, bool $recordEvent = true): void;

	public function nextPosition(Status $status): int;

	public function unassignTasksForUserInWorkspace(User $user, Workspace $workspace): void;
}
