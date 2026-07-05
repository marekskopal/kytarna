<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\Field;

/** @extends AbstractRepository<Field> */
final class FieldRepository extends AbstractRepository
{
	/** @return Iterator<Field> */
	public function findByWorkspace(int $workspaceId): Iterator
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('name', 'ASC')
			->fetchAll();
	}

	public function findOneByWorkspaceAndId(int $workspaceId, int $fieldId): ?Field
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'id' => $fieldId]);
	}

	public function findOneByWorkspaceAndName(int $workspaceId, string $name): ?Field
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'name' => $name]);
	}
}
