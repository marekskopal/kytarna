<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use EmptyIterator;
use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\TaskChecklistItem;

/** @extends AbstractRepository<TaskChecklistItem> */
final class TaskChecklistItemRepository extends AbstractRepository
{
	/** @return Iterator<TaskChecklistItem> */
	public function findByTask(int $taskId): Iterator
	{
		return $this->select()
			->where(['task_id' => $taskId])
			->orderBy('position', 'ASC')
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	public function findOneById(int $id): ?TaskChecklistItem
	{
		return $this->findOne(['id' => $id]);
	}

	/**
	 * @param list<int> $taskIds
	 * @return Iterator<TaskChecklistItem>
	 */
	public function findByTasks(array $taskIds): Iterator
	{
		if ($taskIds === []) {
			return new EmptyIterator();
		}

		return $this->select()
			->where(['task_id', 'IN', $taskIds])
			->fetchAll();
	}
}
