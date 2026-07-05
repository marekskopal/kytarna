<?php

declare(strict_types=1);

namespace Kytario\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Kytario\Model\Entity\ProjectField;

/** @extends AbstractRepository<ProjectField> */
final class ProjectFieldRepository extends AbstractRepository
{
	/** @return Iterator<ProjectField> */
	public function findByProject(int $projectId): Iterator
	{
		return $this->select()
			->where(['project_id' => $projectId])
			->orderBy('position', 'ASC')
			->fetchAll();
	}

	/** @return Iterator<ProjectField> */
	public function findByField(int $fieldId): Iterator
	{
		return $this->select()
			->where(['field_id' => $fieldId])
			->fetchAll();
	}
}
