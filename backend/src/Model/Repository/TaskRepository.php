<?php

declare(strict_types=1);

namespace Kytario\Model\Repository;

use DateTimeImmutable;
use EmptyIterator;
use Iterator;
use Kytario\Model\Entity\Enum\StatusTypeEnum;
use Kytario\Model\Entity\Task;
use Kytario\Model\Repository\Enum\ArchivedFilterEnum;
use Kytario\Model\Repository\Enum\OrderDirectionEnum;
use Kytario\Model\Repository\Enum\TaskOrderByEnum;
use MarekSkopal\ORM\Query\Expression\RawExpression;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<Task> */
final class TaskRepository extends AbstractRepository
{
	public function findById(int $taskId): ?Task
	{
		return $this->findOne(['id' => $taskId]);
	}

	/** LIKE treats %/_ as wildcards; escape them so user input only ever matches literally. */
	private static function escapeLikePattern(string $value): string
	{
		return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
	}

	public function findByProjectAndSequence(int $projectId, int $sequenceNumber): ?Task
	{
		return $this->findOne(['project_id' => $projectId, 'sequence_number' => $sequenceNumber]);
	}

	/**
	 * @param list<int> $taskIds
	 * @return Iterator<Task>
	 */
	public function findByIds(array $taskIds): Iterator
	{
		if ($taskIds === []) {
			return new EmptyIterator();
		}
		return $this->select()
			->where(['id', 'IN', $taskIds])
			->fetchAll();
	}

	public function nextSequenceNumber(int $projectId): int
	{
		$max = 0;
		foreach ($this->findByProject($projectId) as $task) {
			if ($task->sequenceNumber > $max) {
				$max = $task->sequenceNumber;
			}
		}
		return $max + 1;
	}

	/** @return Iterator<Task> */
	public function findByProject(int $projectId, bool $includeArchived = true): Iterator
	{
		$select = $this->select()
			->where(['project_id' => $projectId]);

		if (!$includeArchived) {
			$this->applyArchivedFilter($select, ArchivedFilterEnum::Active);
		}

		return $select
			->orderBy('status_id', 'ASC')
			->orderBy('position', 'ASC')
			->fetchAll();
	}

	/** @return Iterator<Task> */
	public function findByStatus(int $statusId): Iterator
	{
		return $this->select()
			->where(['status_id' => $statusId])
			->orderBy('position', 'ASC')
			->fetchAll();
	}

	/**
	 * @param list<int>|null $statusIds
	 * @param list<int>|null $assigneeIds
	 * @param list<int>|null $taskIdsFilter restrict to these IDs; pass [] to force an empty result
	 * @param list<int>|null $excludeTaskIds drop these IDs from the result
	 * @return Iterator<Task>
	 */
	public function findInWorkspace(
		int $workspaceId,
		int $limit,
		int $offset,
		TaskOrderByEnum $orderBy,
		OrderDirectionEnum $direction,
		?string $search,
		?array $statusIds,
		bool $onlyActive,
		?array $taskIdsFilter = null,
		?array $assigneeIds = null,
		?array $excludeTaskIds = null,
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
		?DateTimeImmutable $dueFrom = null,
		?DateTimeImmutable $dueTo = null,
	): Iterator {
		if ($taskIdsFilter !== null && $taskIdsFilter === []) {
			return new EmptyIterator();
		}

		$select = $this->buildWorkspaceSelect(
			$workspaceId,
			$search,
			$statusIds,
			$onlyActive,
			$taskIdsFilter,
			$assigneeIds,
			$excludeTaskIds,
			$archived,
			$dueFrom,
			$dueTo,
		);

		$select->orderBy($orderBy->value, $direction->value);

		// Secondary deterministic order so equal-key rows stay stable across pages.
		if ($orderBy !== TaskOrderByEnum::CreatedAt) {
			$select->orderBy('created_at', OrderDirectionEnum::Desc->value);
		}
		$select->orderBy('id', OrderDirectionEnum::Desc->value);

		return $select
			->limit($limit)
			->offset($offset)
			->fetchAll();
	}

	/**
	 * @param list<int>|null $statusIds
	 * @param list<int>|null $assigneeIds
	 * @param list<int>|null $taskIdsFilter
	 * @param list<int>|null $excludeTaskIds
	 */
	public function countInWorkspace(
		int $workspaceId,
		?string $search,
		?array $statusIds,
		bool $onlyActive,
		?array $taskIdsFilter = null,
		?array $assigneeIds = null,
		?array $excludeTaskIds = null,
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
		?DateTimeImmutable $dueFrom = null,
		?DateTimeImmutable $dueTo = null,
	): int {
		if ($taskIdsFilter !== null && $taskIdsFilter === []) {
			return 0;
		}
		return $this->buildWorkspaceSelect(
			$workspaceId,
			$search,
			$statusIds,
			$onlyActive,
			$taskIdsFilter,
			$assigneeIds,
			$excludeTaskIds,
			$archived,
			$dueFrom,
			$dueTo,
		)
			->count();
	}

	/**
	 * Active (non-archived), non-finished tasks due on a specific day. Backs the due-date reminder
	 * cron (U-83). `archived_at` lives only on the tasks table, so the raw IS NULL predicate is
	 * unambiguous even with the status join.
	 *
	 * @return Iterator<Task>
	 */
	public function findDueOn(DateTimeImmutable $date): Iterator
	{
		return $this->select()
			->where(['due_date' => $date->format('Y-m-d')])
			->where(['status.type', '!=', StatusTypeEnum::Finish])
			->where([new RawExpression('(archived_at IS NULL)'), '=', 1])
			->fetchAll();
	}

	/** @return Iterator<Task> */
	public function findByAssigneeInWorkspace(int $userId, int $workspaceId): Iterator
	{
		return $this->select()
			->where(['assignee_id' => $userId])
			->where(['project.workspace_id' => $workspaceId])
			->fetchAll();
	}

	/**
	 * @param list<int>|null $statusIds
	 * @param list<int>|null $assigneeIds
	 * @param list<int>|null $taskIdsFilter
	 * @param list<int>|null $excludeTaskIds
	 * @return Select<Task>
	 */
	private function buildWorkspaceSelect(
		int $workspaceId,
		?string $search,
		?array $statusIds,
		bool $onlyActive,
		?array $taskIdsFilter = null,
		?array $assigneeIds = null,
		?array $excludeTaskIds = null,
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
		?DateTimeImmutable $dueFrom = null,
		?DateTimeImmutable $dueTo = null,
	): Select {
		$select = $this->select()
			->where(['project.workspace_id' => $workspaceId]);

		$this->applyArchivedFilter($select, $archived);

		if ($search !== null && $search !== '') {
			$select->where(['name', 'LIKE', '%' . self::escapeLikePattern($search) . '%']);
		}
		// Inclusive due-date range (DATE column). Tasks with a null due_date never match a range bound.
		if ($dueFrom !== null) {
			$select->where(['due_date', '>=', $dueFrom->format('Y-m-d')]);
		}
		if ($dueTo !== null) {
			$select->where(['due_date', '<=', $dueTo->format('Y-m-d')]);
		}
		if ($statusIds !== null && $statusIds !== []) {
			$select->where(['status_id', 'IN', $statusIds]);
		}
		if ($onlyActive) {
			$select->where(['status.type', '!=', StatusTypeEnum::Finish]);
		}
		if ($taskIdsFilter !== null && $taskIdsFilter !== []) {
			$select->where(['id', 'IN', $taskIdsFilter]);
		}
		if ($assigneeIds !== null && $assigneeIds !== []) {
			$select->where(['assignee_id', 'IN', $assigneeIds]);
		}
		if ($excludeTaskIds !== null && $excludeTaskIds !== []) {
			$select->where(['id', 'NOT IN', $excludeTaskIds]);
		}

		return $select;
	}

	/**
	 * The ORM's where-builder has no IS NULL operator (a null value binds as `col = ?`, which never
	 * matches), so the archived filter is expressed as a parenthesised raw predicate compared to 1.
	 * `archived_at` exists only on the tasks table, so the unqualified reference is unambiguous even
	 * when project/status are joined.
	 *
	 * @param Select<Task> $select
	 */
	private function applyArchivedFilter(Select $select, ArchivedFilterEnum $archived): void
	{
		if ($archived === ArchivedFilterEnum::All) {
			return;
		}

		$predicate = $archived === ArchivedFilterEnum::Active
			? 'archived_at IS NULL'
			: 'archived_at IS NOT NULL';

		$select->where([new RawExpression('(' . $predicate . ')'), '=', 1]);
	}
}
