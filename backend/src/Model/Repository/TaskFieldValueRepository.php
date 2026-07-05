<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\TaskFieldValue;

/** @extends AbstractRepository<TaskFieldValue> */
final class TaskFieldValueRepository extends AbstractRepository
{
	/** @return Iterator<TaskFieldValue> */
	public function findByTask(int $taskId): Iterator
	{
		return $this->select()
			->where(['task_id' => $taskId])
			->fetchAll();
	}

	public function findOneByTaskAndField(int $taskId, int $fieldId): ?TaskFieldValue
	{
		return $this->findOne(['task_id' => $taskId, 'field_id' => $fieldId]);
	}

	/** @return Iterator<TaskFieldValue> */
	public function findByField(int $fieldId): Iterator
	{
		return $this->select()
			->where(['field_id' => $fieldId])
			->fetchAll();
	}
}
