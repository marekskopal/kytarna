<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Iterator;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Workflow;
use Ukolio\Model\Entity\Workspace;

interface WorkflowProviderInterface
{
	public function getWorkflow(int $workflowId): ?Workflow;

	public function getWorkflowByProject(Project $project): ?Workflow;

	/** @return Iterator<Workflow> */
	public function getWorkflowsInWorkspace(Workspace $workspace): Iterator;

	public function createDefaultWorkflow(Project $project): Workflow;

	public function updateWorkflow(Workflow $workflow, string $name): Workflow;
}
