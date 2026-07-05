<?php

declare(strict_types=1);

namespace Kytario\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Kytario\Model\Entity\TaskFile;

/** @extends AbstractRepository<TaskFile> */
final class TaskFileRepository extends AbstractRepository
{
	/** @return Iterator<TaskFile> */
	public function findByTask(int $taskId): Iterator
	{
		return $this->select()
			->where(['task_id' => $taskId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	public function findOneById(int $id): ?TaskFile
	{
		return $this->findOne(['id' => $id]);
	}

	/** @return Iterator<TaskFile> */
	public function findByUploader(int $userId): Iterator
	{
		return $this->select()
			->where(['uploaded_by_user_id' => $userId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
