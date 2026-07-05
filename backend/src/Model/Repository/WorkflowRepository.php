<?php

declare(strict_types=1);

namespace Kytario\Model\Repository;

use Iterator;
use Kytario\Model\Entity\Workflow;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<Workflow> */
final class WorkflowRepository extends AbstractRepository
{
	public function findById(int $workflowId): ?Workflow
	{
		return $this->findOne(['id' => $workflowId]);
	}

	public function findByCourse(int $courseId): ?Workflow
	{
		return $this->findOne(['course_id' => $courseId]);
	}

	/** @return Iterator<Workflow> */
	public function findByWorkspace(int $workspaceId): Iterator
	{
		return $this->select()
			->where(['course.workspace_id' => $workspaceId])
			->orderBy('course.name', 'ASC')
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
