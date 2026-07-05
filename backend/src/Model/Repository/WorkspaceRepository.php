<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\Workspace;

/** @extends AbstractRepository<Workspace> */
final class WorkspaceRepository extends AbstractRepository
{
	public function findWorkspaceById(int $workspaceId): ?Workspace
	{
		return $this->findOne(['id' => $workspaceId]);
	}

	/** @return Iterator<Workspace> */
	public function findAllWorkspaces(): Iterator
	{
		return $this->select()->orderBy('id', 'ASC')->fetchAll();
	}

	/** @return Iterator<Workspace> */
	public function findByOwner(int $ownerId): Iterator
	{
		return $this->select()->where(['owner_id' => $ownerId])->fetchAll();
	}
}
