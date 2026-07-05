<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\ScriptVariable;

/** @extends AbstractRepository<ScriptVariable> */
final class ScriptVariableRepository extends AbstractRepository
{
	/** @return Iterator<ScriptVariable> */
	public function findByWorkspace(int $workspaceId): Iterator
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('key', 'ASC')
			->fetchAll();
	}

	public function findOneByWorkspaceAndKey(int $workspaceId, string $key): ?ScriptVariable
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'key' => $key]);
	}

	public function findOneByWorkspaceAndId(int $workspaceId, int $id): ?ScriptVariable
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'id' => $id]);
	}
}
