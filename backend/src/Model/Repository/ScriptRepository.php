<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\Enum\ScriptTriggerEnum;
use Ukolio\Model\Entity\Script;

/** @extends AbstractRepository<Script> */
final class ScriptRepository extends AbstractRepository
{
	/** @return Iterator<Script> */
	public function findByWorkspace(int $workspaceId): Iterator
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('name', 'ASC')
			->fetchAll();
	}

	public function findById(int $id): ?Script
	{
		return $this->findOne(['id' => $id]);
	}

	public function findOneByWorkspaceAndId(int $workspaceId, int $id): ?Script
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'id' => $id]);
	}

	public function findOneByWorkspaceAndName(int $workspaceId, string $name): ?Script
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'name' => $name]);
	}

	public function countByWorkspace(int $workspaceId): int
	{
		return $this->select()->where(['workspace_id' => $workspaceId])->count();
	}

	public function countByWorkspaceAndTrigger(int $workspaceId, ScriptTriggerEnum $trigger): int
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->where(['trigger' => $trigger->value])
			->count();
	}

	/** @return Iterator<Script> */
	public function findActiveByTrigger(ScriptTriggerEnum $trigger): Iterator
	{
		return $this->select()
			->where(['trigger' => $trigger->value])
			->where(['active' => true])
			->fetchAll();
	}

	/** @return Iterator<Script> */
	public function findActiveByWorkspaceAndTrigger(int $workspaceId, ScriptTriggerEnum $trigger): Iterator
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->where(['trigger' => $trigger->value])
			->where(['active' => true])
			->fetchAll();
	}
}
