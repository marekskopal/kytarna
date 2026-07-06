<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Iterator;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Workflow;
use Kytarna\Model\Entity\Workspace;

interface WorkflowProviderInterface
{
	public function getWorkflow(int $workflowId): ?Workflow;

	public function getWorkflowByCourse(Course $course): ?Workflow;

	/** @return Iterator<Workflow> */
	public function getWorkflowsInWorkspace(Workspace $workspace): Iterator;

	public function createDefaultWorkflow(Course $course): Workflow;

	public function updateWorkflow(Workflow $workflow, string $name): Workflow;
}
