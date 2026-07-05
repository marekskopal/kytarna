<?php

declare(strict_types=1);

namespace Kytario\Model\Repository;

use Iterator;
use Kytario\Model\Entity\SavedView;
use MarekSkopal\ORM\Repository\AbstractRepository;

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
