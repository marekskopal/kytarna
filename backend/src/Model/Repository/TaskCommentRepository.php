<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\TaskComment;

/** @extends AbstractRepository<TaskComment> */
final class TaskCommentRepository extends AbstractRepository
{
	/** @return Iterator<TaskComment> */
	public function findByTask(int $taskId): Iterator
	{
		return $this->select()
			->where(['task_id' => $taskId])
			->orderBy('created_at', 'ASC')
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	public function findOneById(int $id): ?TaskComment
	{
		return $this->findOne(['id' => $id]);
	}

	/** @return Iterator<TaskComment> */
	public function findReplies(int $parentCommentId): Iterator
	{
		return $this->select()
			->where(['parent_comment_id' => $parentCommentId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	/** @return Iterator<TaskComment> */
	public function findByAuthor(int $userId): Iterator
	{
		return $this->select()
			->where(['author_id' => $userId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
