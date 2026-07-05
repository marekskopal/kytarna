<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Iterator;
use Kytario\Model\Entity\Project;
use Kytario\Model\Entity\Workflow;
use Kytario\Model\Entity\Workspace;

interface WorkflowProviderInterface
{
	public function getWorkflow(int $workflowId): ?Workflow;

	public function getWorkflowByProject(Project $project): ?Workflow;

	/** @return Iterator<Workflow> */
	public function getWorkflowsInWorkspace(Workspace $workspace): Iterator;

	public function createDefaultWorkflow(Project $project): Workflow;

	public function updateWorkflow(Workflow $workflow, string $name): Workflow;
}
