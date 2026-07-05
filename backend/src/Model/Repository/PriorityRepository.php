<?php

declare(strict_types=1);

namespace Kytario\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Kytario\Model\Entity\Priority;

/** @extends AbstractRepository<Priority> */
final class PriorityRepository extends AbstractRepository
{
	/** @return Iterator<Priority> */
	public function findByWorkspace(int $workspaceId): Iterator
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('position', 'ASC')
			->fetchAll();
	}

	public function findOneByWorkspaceAndId(int $workspaceId, int $priorityId): ?Priority
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'id' => $priorityId]);
	}

	public function findOneByWorkspaceAndName(int $workspaceId, string $name): ?Priority
	{
		foreach ($this->findByWorkspace($workspaceId) as $priority) {
			if (mb_strtolower($priority->name) === mb_strtolower(trim($name))) {
				return $priority;
			}
		}
		return null;
	}

	public function findDefaultForWorkspace(int $workspaceId): ?Priority
	{
		foreach ($this->findByWorkspace($workspaceId) as $priority) {
			if ($priority->isDefault) {
				return $priority;
			}
		}
		return null;
	}
}
