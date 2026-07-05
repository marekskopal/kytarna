<?php

declare(strict_types=1);

namespace Kytario\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Kytario\Model\Entity\TaskWatcher;

/** @extends AbstractRepository<TaskWatcher> */
final class TaskWatcherRepository extends AbstractRepository
{
	/** @return Iterator<TaskWatcher> */
	public function findByTask(int $taskId): Iterator
	{
		return $this->select()
			->where(['task_id' => $taskId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	public function findByTaskAndUser(int $taskId, int $userId): ?TaskWatcher
	{
		return $this->findOne(['task_id' => $taskId, 'user_id' => $userId]);
	}
}
