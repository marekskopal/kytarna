<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Query\Expression\RawExpression;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\Enum\NotificationTypeEnum;
use Ukolio\Model\Entity\Notification;

/** @extends AbstractRepository<Notification> */
final class NotificationRepository extends AbstractRepository
{
	// The ORM where-builder has no IS NULL operator (a null value binds as `col = ?`, which never
	// matches), so the unread filter is expressed as a parenthesised raw predicate compared to 1.
	private const string UnreadPredicate = '(read_at IS NULL)';

	public function findOneById(int $id): ?Notification
	{
		return $this->findOne(['id' => $id]);
	}

	/** @return Iterator<Notification> */
	public function findForUser(int $userId, int $limit, int $offset, bool $unreadOnly): Iterator
	{
		$select = $this->select()
			->where(['user_id' => $userId]);

		if ($unreadOnly) {
			$select->where([new RawExpression(self::UnreadPredicate), '=', 1]);
		}

		return $select
			->orderBy('id', 'DESC')
			->limit($limit)
			->offset($offset)
			->fetchAll();
	}

	/** @return Iterator<Notification> */
	public function findUnreadForUser(int $userId): Iterator
	{
		return $this->select()
			->where(['user_id' => $userId])
			->where([new RawExpression(self::UnreadPredicate), '=', 1])
			->fetchAll();
	}

	public function countUnread(int $userId): int
	{
		return $this->select()
			->where(['user_id' => $userId])
			->where([new RawExpression(self::UnreadPredicate), '=', 1])
			->count();
	}

	/** True if a notification of this type for this task+user was already created at/after the given moment. */
	public function existsSince(int $userId, int $taskId, NotificationTypeEnum $type, string $sinceDateTime): bool
	{
		return $this->select()
			->where(['user_id' => $userId])
			->where(['task_id' => $taskId])
			->where(['type' => $type->value])
			->where(['created_at', '>=', $sinceDateTime])
			->count() > 0;
	}
}
