<?php

declare(strict_types=1);

namespace Kytario\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Kytario\Model\Entity\WorkspaceUser;

/** @extends AbstractRepository<WorkspaceUser> */
final class WorkspaceUserRepository extends AbstractRepository
{
	/** @return Iterator<WorkspaceUser> */
	public function findByUser(int $userId): Iterator
	{
		return $this->select()
			->where(['user_id' => $userId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	/** @return Iterator<WorkspaceUser> */
	public function findByWorkspace(int $workspaceId): Iterator
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	public function findMembership(int $userId, int $workspaceId): ?WorkspaceUser
	{
		return $this->findOne(['user_id' => $userId, 'workspace_id' => $workspaceId]);
	}
}
