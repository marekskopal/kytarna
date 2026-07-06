<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository;

use Iterator;
use Kytarna\Model\Entity\Enum\ActorTypeEnum;
use Kytarna\Model\Entity\Enum\EventTypeEnum;
use Kytarna\Model\Entity\Event;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<Event> */
final class EventRepository extends AbstractRepository
{
	/** @return Iterator<Event> */
	public function findByCourse(int $courseId, int $limit = 100, int $offset = 0): Iterator
	{
		return $this->select()
			->where(['course_id' => $courseId])
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
	 * Workspace-scoped event lookup with optional course/lecture/type narrowing, newest first.
	 *
	 * @return Iterator<Event>
	 */
	public function findByWorkspaceFiltered(
		int $workspaceId,
		?int $courseId,
		?int $lectureId,
		?EventTypeEnum $type,
		int $limit,
		int $offset,
	): Iterator {
		$select = $this->select()
			->where(['workspace_id' => $workspaceId]);

		if ($courseId !== null) {
			$select->where(['course_id' => $courseId]);
		}

		if ($lectureId !== null) {
			$select->where(['lecture_id' => $lectureId]);
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
