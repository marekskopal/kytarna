<?php

declare(strict_types=1);

namespace Kytario\Model\Repository;

use Iterator;
use Kytario\Model\Entity\TaskTag;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<TaskTag> */
final class TaskTagRepository extends AbstractRepository
{
	/** @return Iterator<TaskTag> */
	public function findByTask(int $taskId): Iterator
	{
		return $this->select()
			->where(['task_id' => $taskId])
			->fetchAll();
	}

	/**
	 * @param list<int> $tagIds
	 * @return list<int> distinct task ids that have ANY of the given tags
	 */
	public function findTaskIdsByTagIds(array $tagIds): array
	{
		if ($tagIds === []) {
			return [];
		}
		$result = [];
		foreach ($this->select()->where(['tag_id', 'IN', $tagIds])->fetchAll() as $row) {
			$result[$row->task->id] = true;
		}
		return array_keys($result);
	}
}
