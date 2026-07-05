<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\TaskTemplate;

/** @extends AbstractRepository<TaskTemplate> */
final class TaskTemplateRepository extends AbstractRepository
{
	/** @return Iterator<TaskTemplate> */
	public function findByWorkspace(int $workspaceId): Iterator
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('name', 'ASC')
			->fetchAll();
	}

	public function findOneById(int $id): ?TaskTemplate
	{
		return $this->findOne(['id' => $id]);
	}

	public function findOneByIdAndWorkspace(int $id, int $workspaceId): ?TaskTemplate
	{
		return $this->findOne(['id' => $id, 'workspace_id' => $workspaceId]);
	}

	public function findOneByWorkspaceAndName(int $workspaceId, string $name): ?TaskTemplate
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'name' => $name]);
	}
}
