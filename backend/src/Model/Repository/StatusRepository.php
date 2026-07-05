<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\Status;

/** @extends AbstractRepository<Status> */
final class StatusRepository extends AbstractRepository
{
	public function findById(int $statusId): ?Status
	{
		return $this->findOne(['id' => $statusId]);
	}

	/** @return Iterator<Status> */
	public function findByWorkflow(int $workflowId): Iterator
	{
		return $this->select()
			->where(['workflow_id' => $workflowId])
			->orderBy('position', 'ASC')
			->fetchAll();
	}
}
