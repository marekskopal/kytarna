<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository;

use Iterator;
use Kytarna\Model\Entity\Notification;
use MarekSkopal\ORM\Query\Expression\RawExpression;
use MarekSkopal\ORM\Repository\AbstractRepository;

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
}
