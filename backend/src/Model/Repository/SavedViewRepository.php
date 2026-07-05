<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\SavedView;

/** @extends AbstractRepository<SavedView> */
final class SavedViewRepository extends AbstractRepository
{
	/** @return Iterator<SavedView> */
	public function findByWorkspaceAndUser(int $workspaceId, int $userId): Iterator
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->where(['user_id' => $userId])
			->orderBy('name', 'ASC')
			->fetchAll();
	}

	public function findOneByIdForUser(int $id, int $userId): ?SavedView
	{
		return $this->findOne(['id' => $id, 'user_id' => $userId]);
	}

	public function findOneByWorkspaceUserName(int $workspaceId, int $userId, string $name): ?SavedView
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'user_id' => $userId, 'name' => $name]);
	}
}
