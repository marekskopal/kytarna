<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\Tag;

/** @extends AbstractRepository<Tag> */
final class TagRepository extends AbstractRepository
{
	/** @return Iterator<Tag> */
	public function findByWorkspace(int $workspaceId): Iterator
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('name', 'ASC')
			->fetchAll();
	}

	public function findOneByWorkspaceAndId(int $workspaceId, int $tagId): ?Tag
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'id' => $tagId]);
	}

	public function findOneByWorkspaceAndName(int $workspaceId, string $name): ?Tag
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'name' => $name]);
	}
}
