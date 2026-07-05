<?php

declare(strict_types=1);

namespace Kytario\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Kytario\Model\Entity\Enum\ActorTypeEnum;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Event;

/** @extends AbstractRepository<Event> */
final class EventRepository extends AbstractRepository
{
	/** @return Iterator<Event> */
	public function findByProject(int $projectId, int $limit = 100, int $offset = 0): Iterator
	{
		return $this->select()
			->where(['project_id' => $projectId])
			->orderBy('id', 'DESC')
			->limit($limit)
			->offset($offset)
			->fetchAll();
	}

	/** @return Iterator<Event> */
	public function findByWorkspace(int $workspaceId, ?ActorTypeEnum $actorType, int $limit, int $offset): Iterator
	{
		$select = $this->select()
			->where(['workspace_id' => $workspaceId]);

		if ($actorType !== null) {
			$select->where(['actor_type' => $actorType->value]);
		}

		return $select
			->orderBy('id', 'DESC')
			->limit($limit)
			->offset($offset)
			->fetchAll();
	}

	public function countByWorkspaceSince(int $workspaceId, int $sinceTimestamp): int
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->where(['created_at', '>=', date('Y-m-d H:i:s', $sinceTimestamp)])
			->count();
	}

	public function countByWorkspaceTypeSince(int $workspaceId, EventTypeEnum $type, int $sinceTimestamp): int
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->where(['type' => $type->value])
			->where(['created_at', '>=', date('Y-m-d H:i:s', $sinceTimestamp)])
			->count();
	}

	/**
	 * Workspace-scoped event lookup with optional project/task/type narrowing, newest first.
	 *
	 * @return Iterator<Event>
	 */
	public function findByWorkspaceFiltered(
		int $workspaceId,
		?int $projectId,
		?int $taskId,
		?EventTypeEnum $type,
		int $limit,
		int $offset,
	): Iterator {
		$select = $this->select()
			->where(['workspace_id' => $workspaceId]);

		if ($projectId !== null) {
			$select->where(['project_id' => $projectId]);
		}

		if ($taskId !== null) {
			$select->where(['task_id' => $taskId]);
		}

		if ($type !== null) {
			$select->where(['type' => $type->value]);
		}

		return $select
			->orderBy('id', 'DESC')
			->limit($limit)
			->offset($offset)
			->fetchAll();
	}

	/** @return Iterator<Event> */
	public function findByAuthor(int $userId): Iterator
	{
		return $this->select()
			->where(['author_id' => $userId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
